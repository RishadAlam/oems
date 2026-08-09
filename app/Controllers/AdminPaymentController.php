<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\PaymentRepositoryInterface;
use OEMS\App\Services\RegistrationService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use Throwable;

final class AdminPaymentController extends Controller
{
    private const STATUSES = ['pending', 'paid', 'failed', 'refunded', 'all'];
    private const REVIEW_INTENT_TTL = 300;
    private const REVIEW_INTENTS_SESSION_KEY = 'payment_review_intents';

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly PaymentRepositoryInterface $payments,
        private readonly RegistrationService $registrationService,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request, false);
        $page = $this->positiveInt($request->query('page')) ?? 1;
        $perPage = $this->positiveInt($request->query('per_page')) ?? 25;
        $perPage = min(50, $perPage);
        $total = $this->payments->countForAdmin($filters);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return $this->render('admin/payments/index', [
            'pageTitle' => 'Payment review',
            'payments' => $this->payments->forAdmin($filters, $perPage, ($page - 1) * $perPage),
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => $lastPage,
        ], 'dashboard');
    }

    public function show(Request $request): Response
    {
        $paymentId = $this->positiveInt($request->route('id'));
        $payment = $paymentId === null ? null : $this->payments->findForAdmin($paymentId);

        return $payment === null
            ? $this->notFound()
            : $this->renderDetail($payment, $request, returnFilters: $this->filters($request, false));
    }

    public function verify(Request $request): Response
    {
        return $this->settle($request, 'paid');
    }

    public function reject(Request $request): Response
    {
        return $this->settle($request, 'failed');
    }

    private function settle(Request $request, string $target): Response
    {
        $paymentId = $this->positiveInt($request->route('id'));
        $payment = $paymentId === null ? null : $this->payments->findForAdmin($paymentId);
        if ($payment === null) {
            return $this->notFound();
        }

        $administratorId = $this->auth->id();
        if ($administratorId === null) {
            return Response::html('Forbidden', 403);
        }

        if ($request->input('confirm_review') === '1') {
            return $this->confirmSettlement($request, $payment, $administratorId, $target);
        }

        if (($payment['payment_status'] ?? null) !== 'pending') {
            return $this->performSettlement($request, $paymentId, $administratorId, $target, null);
        }

        $rawNote = $request->input('note');
        if ($rawNote !== null && !is_scalar($rawNote)) {
            return $this->noteError($request, $paymentId, 'Enter a note of no more than 500 characters.', '');
        }

        $note = $rawNote === null ? null : trim((string) $rawNote);
        if ($note !== null && mb_strlen($note) > 500) {
            return $this->noteError(
                $request,
                $paymentId,
                'Enter a note of no more than 500 characters.',
                mb_substr($note, 0, 500),
            );
        }
        $note = $note === '' ? null : $note;

        $filters = $this->filters($request, true);
        $token = bin2hex(random_bytes(32));
        $intents = $this->activeReviewIntents();
        $intents[hash('sha256', $token)] = [
            'administrator_id' => $administratorId,
            'payment_id' => $paymentId,
            'target' => $target,
            'note' => $note,
            'filters' => $filters,
            'evidence' => $this->paymentEvidenceFingerprint($payment),
            'expires_at' => time() + self::REVIEW_INTENT_TTL,
        ];
        $this->session->put(self::REVIEW_INTENTS_SESSION_KEY, array_slice($intents, -5, null, true));

        return $this->renderDetail($payment, $request, returnFilters: $filters, confirmation: [
            'token' => $token,
            'target' => $target,
            'note' => $note,
            'cancelUrl' => $this->destinationForFilters($paymentId, $filters),
        ]);
    }

    private function confirmSettlement(Request $request, array $payment, int $administratorId, string $target): Response
    {
        $paymentId = (int) $payment['id'];
        $rawToken = $request->input('review_intent');
        $token = is_scalar($rawToken) ? (string) $rawToken : '';
        $intents = $this->activeReviewIntents();
        $intentKey = preg_match('/^[a-f0-9]{64}$/D', $token) === 1 ? hash('sha256', $token) : '';
        $intent = $intentKey === '' ? null : ($intents[$intentKey] ?? null);

        $valid = is_array($intent)
            && (int) ($intent['administrator_id'] ?? 0) === $administratorId
            && (int) ($intent['payment_id'] ?? 0) === $paymentId
            && ($intent['target'] ?? null) === $target
            && is_string($intent['evidence'] ?? null)
            && hash_equals((string) $intent['evidence'], $this->paymentEvidenceFingerprint($payment));

        if (!$valid) {
            $response = $this->renderDetail(
                $payment,
                $request,
                'This confirmation is invalid or has expired. Review the current evidence and begin again.',
            );

            return Response::html($response->body(), 409);
        }

        unset($intents[$intentKey]);
        $this->session->put(self::REVIEW_INTENTS_SESSION_KEY, $intents);
        $filters = is_array($intent['filters'] ?? null) ? $intent['filters'] : [];

        return $this->performSettlement(
            $request,
            $paymentId,
            $administratorId,
            $target,
            is_string($intent['note'] ?? null) ? $intent['note'] : null,
            $filters,
        );
    }

    private function performSettlement(
        Request $request,
        int $paymentId,
        int $administratorId,
        string $target,
        ?string $note,
        ?array $returnFilters = null,
    ): Response
    {
        $result = $target === 'paid'
            ? $this->registrationService->verifyPayment($administratorId, $paymentId, $note)
            : $this->registrationService->rejectPayment($administratorId, $paymentId, $note);
        $destination = $returnFilters === null
            ? $this->destination($request, $paymentId)
            : $this->destinationForFilters($paymentId, $returnFilters);

        if (($result['success'] ?? false) === true) {
            return $this->redirectWith(
                $destination,
                'success',
                $target === 'paid'
                    ? 'Payment verified. Registration confirmed and ticket issued.'
                    : 'Payment rejected. Registration cancelled and seat released.',
            );
        }

        $current = $this->payments->findForAdmin($paymentId);
        $opposite = $target === 'paid' ? 'failed' : 'paid';
        if (($current['payment_status'] ?? null) === $opposite) {
            $response = $this->renderDetail(
                $current,
                $request,
                $target === 'paid'
                    ? 'This payment was already rejected and cannot be verified.'
                    : 'This payment was already verified and cannot be rejected.',
            );

            return Response::html($response->body(), 409);
        }

        return $this->redirectWith($destination, 'error', $this->firstError($result['errors'] ?? []));
    }

    private function noteError(Request $request, int $paymentId, string $message, string $note): Response
    {
        $this->session->flash('errors', ['note' => [$message]]);
        $this->session->flash('old', ['note' => $note]);

        return Response::redirect($this->destination($request, $paymentId));
    }

    private function renderDetail(
        array $payment,
        Request $request,
        ?string $actionError = null,
        ?array $returnFilters = null,
        ?array $confirmation = null,
    ): Response
    {
        return $this->render('admin/payments/show', [
            'pageTitle' => 'Payment ' . (string) ($payment['transaction_reference'] ?? ('#' . $payment['id'])),
            'payment' => $payment,
            'paymentAge' => $this->age((string) ($payment['created_at'] ?? '')),
            'returnFilters' => $returnFilters ?? $this->filters($request, true),
            'actionError' => $actionError,
            'confirmation' => $confirmation,
        ], 'dashboard');
    }

    private function filters(Request $request, bool $input): array
    {
        $read = static fn (string $key): mixed => $input ? $request->input($key) : $request->query($key);
        $requestedStatus = $read('status');
        $status = is_scalar($requestedStatus) ? mb_strtolower(trim((string) $requestedStatus)) : 'pending';
        $status = in_array($status, self::STATUSES, true) ? $status : 'pending';
        $rawSearch = $read('search');
        $search = is_scalar($rawSearch) ? trim((string) $rawSearch) : '';
        if (mb_strlen($search) > 120) {
            $search = '';
        }

        $filters = ['status' => $status, 'search' => $search];
        $page = $this->positiveInt($read('page'));
        $perPage = $this->positiveInt($read('per_page'));
        if ($page !== null) {
            $filters['page'] = $page;
        }
        if ($perPage !== null && $perPage <= 50) {
            $filters['per_page'] = $perPage;
        }

        return $filters;
    }

    private function destination(Request $request, int $paymentId): string
    {
        return $this->destinationForFilters($paymentId, $this->filters($request, true));
    }

    private function destinationForFilters(int $paymentId, array $returnFilters): string
    {
        $filters = array_filter(
            $returnFilters,
            static fn (mixed $value): bool => $value !== '' && $value !== null && $value !== 'pending',
        );
        $query = http_build_query($filters, '', '&', PHP_QUERY_RFC3986);

        return '/admin/payments/' . $paymentId . ($query === '' ? '' : '?' . $query);
    }

    private function activeReviewIntents(): array
    {
        $stored = $this->session->get(self::REVIEW_INTENTS_SESSION_KEY, []);
        if (!is_array($stored)) {
            return [];
        }

        $now = time();
        $active = array_filter(
            $stored,
            static fn (mixed $intent): bool => is_array($intent) && (int) ($intent['expires_at'] ?? 0) >= $now,
        );
        if (count($active) !== count($stored)) {
            $this->session->put(self::REVIEW_INTENTS_SESSION_KEY, $active);
        }

        return $active;
    }

    private function paymentEvidenceFingerprint(array $payment): string
    {
        $evidence = [];
        foreach ([
            'id',
            'registration_id',
            'participant_id',
            'participant_name',
            'event_id',
            'event_title',
            'amount',
            'currency',
            'transaction_reference',
            'payment_status',
        ] as $field) {
            $evidence[$field] = is_scalar($payment[$field] ?? null) ? (string) $payment[$field] : '';
        }

        return hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR));
    }

    private function age(string $timestamp): string
    {
        if ($timestamp === '') {
            return 'Time unavailable';
        }

        try {
            $timezone = new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka'));
            $created = new DateTimeImmutable($timestamp, $timezone);
            $seconds = max(0, (new DateTimeImmutable('now', $timezone))->getTimestamp() - $created->getTimestamp());
        } catch (Throwable) {
            return 'Time unavailable';
        }

        foreach ([[86400, 'day'], [3600, 'hour'], [60, 'minute']] as [$size, $label]) {
            if ($seconds >= $size) {
                $value = intdiv($seconds, $size);

                return $value . ' ' . $label . ($value === 1 ? '' : 's') . ' ago';
            }
        }

        return 'Less than a minute ago';
    }

    private function firstError(array $errors): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_scalar($messages[0])) {
                return (string) $messages[0];
            }
        }

        return 'The payment could not be updated.';
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_scalar($value) && preg_match('/^[1-9][0-9]*$/', (string) $value) === 1
            ? (int) $value
            : null;
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
    }
}
