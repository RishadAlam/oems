<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\PaymentRepositoryInterface;
use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\App\Contracts\TicketRepositoryInterface;
use OEMS\App\Services\RegistrationService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class ParticipantRegistrationController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly EventRepositoryInterface $events,
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly PaymentRepositoryInterface $payments,
        private readonly TicketRepositoryInterface $tickets,
        private readonly RegistrationService $registrationService,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        $registrations = array_map(
            fn (array $registration): array => $this->presentRegistration($registration),
            $this->registrations->forParticipant($userId),
        );

        return $this->render('participant/registrations/index', [
            'pageTitle' => 'My registrations',
            'registrations' => $registrations,
        ], 'dashboard');
    }

    public function create(Request $request): Response
    {
        $event = $this->eventFromSlug($request);

        if ($event === null) {
            return $this->notFound();
        }

        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login');
        }

        $existing = $this->registrations->findForParticipantEvent($userId, (int) $event['id']);
        if ($existing !== null && in_array((string) ($existing['registration_status'] ?? $existing['status'] ?? ''), ['pending', 'confirmed'], true)) {
            return Response::redirect('/participant/registrations/' . (int) $existing['id']);
        }

        if (!$this->eventAcceptsRegistration($event)) {
            return $this->redirectWith('/events/' . rawurlencode((string) $event['slug']), 'error', 'This event is not available for registration.');
        }

        $isFree = (float) $event['ticket_price'] <= 0;

        return $this->render('participant/registrations/register', [
            'pageTitle' => 'Register for ' . (string) $event['title'],
            'event' => $this->presentEvent($event),
            'isFree' => $isFree,
            'manualPayment' => $isFree
                ? null
                : $this->manualPaymentPresentation($this->payments->findActiveMethodBySlug('manual')),
        ], 'dashboard');
    }

    public function store(Request $request): Response
    {
        $event = $this->eventFromSlug($request);

        if ($event === null) {
            return $this->notFound();
        }

        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login');
        }

        if (!$this->eventAcceptsRegistration($event)) {
            return $this->redirectWith('/events/' . rawurlencode((string) $event['slug']), 'error', 'This event is not available for registration.');
        }

        $paymentDetails = [
            'channel' => is_scalar($request->input('channel')) ? (string) $request->input('channel') : '',
            'transaction_reference' => is_scalar($request->input('transaction_reference'))
                ? (string) $request->input('transaction_reference')
                : '',
        ];
        $result = $this->registrationService->register($userId, (int) $event['id'], $paymentDetails);

        if (!$result['success']) {
            return $this->redirectWithErrors(
                '/participant/events/' . rawurlencode((string) $event['slug']) . '/register',
                is_array($result['errors'] ?? null) ? $result['errors'] : ['registration' => ['The registration could not be completed.']],
                ['channel' => is_scalar($paymentDetails['channel'] ?? null) ? (string) $paymentDetails['channel'] : ''],
            );
        }

        $registrationId = (int) ($result['registration']['id'] ?? 0);
        if ($registrationId <= 0) {
            return $this->redirectWith('/participant/registrations', 'error', 'The registration could not be loaded.');
        }

        return $this->redirectWith(
            '/participant/registrations/' . $registrationId,
            'success',
            (float) $event['ticket_price'] <= 0
                ? 'Your registration is confirmed.'
                : 'Your payment reference was submitted for review.',
        );
    }

    public function show(Request $request): Response
    {
        $registration = $this->ownedRegistration($request);

        if ($registration === null) {
            return $this->notFound();
        }

        $registrationId = (int) $registration['id'];
        $userId = $this->auth->id();
        $payment = $this->payments->findForRegistration($registrationId);
        $ticket = $this->tickets->findForRegistration($registrationId);
        $cancellationState = $userId === null
            ? ['allowed' => false, 'code' => 'not_found', 'reason' => null]
            : $this->registrationService->cancellationState($userId, $registrationId);

        return $this->render('participant/registrations/show', [
            'pageTitle' => 'Registration ' . (string) $registration['registration_number'],
            'registration' => $this->presentRegistration($registration, $payment, $ticket, $cancellationState),
        ], 'dashboard');
    }

    public function cancel(Request $request): Response
    {
        $registration = $this->ownedRegistration($request);

        if ($registration === null) {
            return $this->notFound();
        }

        $userId = $this->auth->id();
        $registrationId = (int) $registration['id'];
        if ($userId === null) {
            return Response::redirect('/login');
        }

        $result = $this->registrationService->cancel(
            $userId,
            $registrationId,
            is_scalar($request->input('reason')) ? (string) $request->input('reason') : '',
        );

        if (!$result['success']) {
            return $this->redirectWithErrors(
                '/participant/registrations/' . $registrationId,
                is_array($result['errors'] ?? null) ? $result['errors'] : ['registration' => ['The registration could not be cancelled.']],
            );
        }

        return $this->redirectWith('/participant/registrations/' . $registrationId, 'success', 'Your registration was cancelled.');
    }

    private function eventFromSlug(Request $request): ?array
    {
        $value = $request->route('slug');
        $slug = is_scalar($value) ? mb_strtolower(trim((string) $value)) : '';

        if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        $event = $this->events->findPublishedBySlug($slug);

        return $event !== null
            && ($event['status'] ?? null) === 'published'
            && empty($event['deleted_at'])
                ? $event
                : null;
    }

    private function eventAcceptsRegistration(array $event): bool
    {
        $now = new DateTimeImmutable('now', $this->timezone());

        return (int) ($event['available_seats'] ?? 0) > 0
            && $this->date((string) ($event['registration_deadline'] ?? '')) > $now
            && $this->date((string) ($event['start_date'] ?? '')) > $now;
    }

    private function ownedRegistration(Request $request): ?array
    {
        $id = $this->positiveId($request->route('id'));
        $userId = $this->auth->id();

        return $id === null || $userId === null ? null : $this->registrations->findForParticipant($userId, $id);
    }

    private function presentEvent(array $event): array
    {
        $amount = (float) ($event['ticket_price'] ?? 0);

        return array_merge($event, [
            'start_display' => $this->date((string) $event['start_date'])->format('M j, Y, g:i A'),
            'total_display' => $this->currency($amount, (string) ($event['currency'] ?? 'BDT')),
        ]);
    }

    private function manualPaymentPresentation(?array $method): ?array
    {
        if ($method === null) {
            return null;
        }

        $configuration = is_array($method['configuration'] ?? null) ? $method['configuration'] : [];
        $presentation = [
            'name' => $this->boundedText($method['name'] ?? null, 100),
            'account_title' => $this->boundedText($configuration['account_title'] ?? null, 120),
            'account_identifier' => $this->boundedText($configuration['account_identifier'] ?? null, 120),
            'instructions' => $this->boundedText($configuration['instructions'] ?? null, 500),
        ];

        return array_filter($presentation, static fn (string $value): bool => $value !== '');
    }

    private function boundedText(mixed $value, int $limit): string
    {
        return is_scalar($value) ? mb_substr(trim((string) $value), 0, $limit) : '';
    }

    private function presentRegistration(
        array $registration,
        ?array $payment = null,
        ?array $ticket = null,
        ?array $cancellationState = null,
    ): array
    {
        $registrationId = (int) ($registration['id'] ?? 0);
        $payment ??= $registrationId > 0 ? $this->payments->findForRegistration($registrationId) : null;
        $ticket ??= $registrationId > 0 ? $this->tickets->findForRegistration($registrationId) : null;
        $status = (string) ($registration['registration_status'] ?? $registration['status'] ?? 'pending');
        $paymentStatus = (string) ($payment['payment_status'] ?? $payment['status'] ?? 'not_required');
        $start = trim((string) ($registration['event_start_date'] ?? ''));
        $cancellationState ??= ['allowed' => false, 'code' => 'not_loaded', 'reason' => null];

        return array_merge($registration, [
            'registration_status' => $status,
            'payment' => $payment,
            'payment_status' => $paymentStatus,
            'ticket' => $ticket,
            'amount_display' => $this->currency((float) ($registration['amount'] ?? 0), (string) ($registration['currency'] ?? 'BDT')),
            'registered_display' => $this->date((string) $registration['registered_at'])->format('M j, Y, g:i A'),
            'event_start_display' => $start === '' ? 'Schedule unavailable' : $this->date($start)->format('M j, Y, g:i A'),
            'can_cancel' => (bool) ($cancellationState['allowed'] ?? false),
            'cancellation_state' => $cancellationState,
        ]);
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_scalar($value) || preg_match('/^[1-9][0-9]*$/', (string) $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function notFound(): Response
    {
        $response = $this->render('errors/404', ['pageTitle' => 'Registration not found'], 'dashboard');

        return Response::html($response->body(), 404);
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, $this->timezone());
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka'));
    }

    private function currency(float $amount, string $currency): string
    {
        $formatted = number_format($amount, floor($amount) === $amount ? 0 : 2);

        return match (strtoupper($currency)) {
            'BDT' => '৳' . $formatted,
            'USD' => '$' . $formatted,
            default => $formatted . ' ' . strtoupper($currency),
        };
    }
}
