<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\TicketRepositoryInterface;
use OEMS\App\Services\TicketArtifactService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use RuntimeException;

final class ParticipantTicketController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly TicketRepositoryInterface $tickets,
        private readonly TicketArtifactService $artifacts,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login');
        }

        return $this->render('participant/tickets/index', [
            'pageTitle' => 'My tickets',
            'tickets' => array_map($this->presentTicket(...), $this->tickets->forParticipant($userId)),
        ], 'dashboard');
    }

    public function show(Request $request): Response
    {
        $ticket = $this->ownedTicket($request);

        if ($ticket === null) {
            return $this->notFound();
        }

        return $this->render('participant/tickets/show', [
            'pageTitle' => 'Ticket ' . (string) $ticket['ticket_number'],
            'ticket' => $this->presentTicket($ticket),
        ], 'dashboard');
    }

    public function qr(Request $request): Response
    {
        return $this->artifactResponse($request, 'qr');
    }

    public function pdf(Request $request): Response
    {
        return $this->artifactResponse($request, 'pdf');
    }

    private function artifactResponse(Request $request, string $format): Response
    {
        $ticket = $this->ownedTicket($request);
        $pathKey = $format === 'qr' ? 'qr_path' : 'pdf_path';
        $path = $ticket[$pathKey] ?? null;

        if ($ticket === null || !is_string($path)) {
            return $this->notFound();
        }

        $resolved = $this->artifacts->resolvePublicPath($path);
        if ($resolved === null) {
            return $this->notFound();
        }

        $ticketNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($ticket['ticket_number'] ?? 'ticket')) ?? 'ticket';
        $ticketNumber = trim($ticketNumber, '.-') ?: 'ticket';
        $isQr = $format === 'qr';
        $filename = $ticketNumber . ($isQr ? '-qr.png' : '.pdf');

        try {
            return Response::file($resolved, 200, [
                'Content-Type' => $isQr ? 'image/png' : 'application/pdf',
                'Content-Disposition' => ($isQr ? 'inline' : 'attachment') . '; filename="' . $filename . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (RuntimeException) {
            return $this->notFound();
        }
    }

    private function ownedTicket(Request $request): ?array
    {
        $id = $this->positiveId($request->route('id'));
        $userId = $this->auth->id();

        return $id === null || $userId === null ? null : $this->tickets->findForParticipant($userId, $id);
    }

    private function presentTicket(array $ticket): array
    {
        $issued = trim((string) ($ticket['issued_at'] ?? ''));
        $starts = trim((string) ($ticket['event_start_date'] ?? ''));

        return array_merge($ticket, [
            'ticket_status' => (string) ($ticket['ticket_status'] ?? $ticket['status'] ?? 'valid'),
            'issued_display' => $issued === '' ? 'Issue date unavailable' : $this->date($issued)->format('M j, Y, g:i A'),
            'event_start_display' => $starts === '' ? 'Schedule unavailable' : $this->date($starts)->format('M j, Y, g:i A'),
        ]);
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_scalar($value) || preg_match('/^[1-9][0-9]*$/', (string) $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $value,
            new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka')),
        );
    }

    private function notFound(): Response
    {
        $response = $this->render('errors/404', ['pageTitle' => 'Ticket not found'], 'dashboard');

        return Response::html($response->body(), 404);
    }
}
