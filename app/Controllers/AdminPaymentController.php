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

        $administratorId = $this->auth->id();
        if ($administratorId === null) {
            return Response::html('Forbidden', 403);
        }

        $result = $target === 'paid'
            ? $this->registrationService->verifyPayment($administratorId, $paymentId, $note)
            : $this->registrationService->rejectPayment($administratorId, $paymentId, $note);
        $destination = $this->destination($request, $paymentId);

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
    ): Response
    {
        return $this->render('admin/payments/show', [
            'pageTitle' => 'Payment ' . (string) ($payment['transaction_reference'] ?? ('#' . $payment['id'])),
            'payment' => $payment,
            'paymentAge' => $this->age((string) ($payment['created_at'] ?? '')),
            'returnFilters' => $returnFilters ?? $this->filters($request, true),
            'actionError' => $actionError,
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
        $filters = array_filter(
            $this->filters($request, true),
            static fn (mixed $value): bool => $value !== '' && $value !== null && $value !== 'pending',
        );
        $query = http_build_query($filters, '', '&', PHP_QUERY_RFC3986);

        return '/admin/payments/' . $paymentId . ($query === '' ? '' : '?' . $query);
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
