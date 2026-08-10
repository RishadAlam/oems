<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Services\CertificateService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use RuntimeException;
use Throwable;

final class ParticipantCertificateController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly CertificateService $certificates,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $participantId = $this->auth->id();
        if ($participantId === null) {
            return Response::redirect('/login');
        }

        return $this->render('participant/certificates/index', [
            'pageTitle' => 'My certificates',
            'certificates' => array_map(fn (array $certificate): array => $this->present($certificate), $this->certificates->forParticipant($participantId)),
        ], 'dashboard');
    }

    public function issue(Request $request): Response
    {
        $participantId = $this->auth->id();
        $registrationId = $this->positiveId($request->route('id'));
        if ($participantId === null) {
            return Response::redirect('/login');
        }
        if ($registrationId === null) {
            return $this->redirectWith('/participant/certificates', 'error', 'The certificate request is invalid.');
        }
        $result = $this->certificates->issue($participantId, $registrationId);
        if (!($result['success'] ?? false)) {
            $message = $result['errors']['certificate'][0] ?? 'The certificate could not be issued.';

            return $this->redirectWith('/participant/certificates', 'error', is_scalar($message) ? (string) $message : 'The certificate could not be issued.');
        }

        return $this->redirectWith(
            '/participant/certificates',
            'success',
            !empty($result['created']) ? 'Your attendance certificate is ready.' : 'Your certificate is already available.',
        );
    }

    public function pdf(Request $request): Response
    {
        $participantId = $this->auth->id();
        $certificateId = $this->positiveId($request->route('id'));
        if ($participantId === null || $certificateId === null) {
            return $this->notFound();
        }
        $download = $this->certificates->download($participantId, $certificateId);
        if ($download === null) {
            return $this->notFound();
        }
        $number = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($download['certificate']['certificate_number'] ?? 'OEMS-certificate')) ?? 'OEMS-certificate';
        $number = trim($number, '.-') ?: 'OEMS-certificate';
        try {
            return Response::file((string) $download['path'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $number . '.pdf"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (RuntimeException) {
            return $this->notFound();
        }
    }

    private function present(array $certificate): array
    {
        return array_merge($certificate, [
            'issued_display' => $this->date($certificate['issued_at'] ?? null),
            'completion_display' => $this->date($certificate['completion_date'] ?? null, 'M j, Y'),
        ]);
    }

    private function date(mixed $value, string $format = 'M j, Y, g:i A'): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return 'Date unavailable';
        }
        try {
            return (new DateTimeImmutable((string) $value, new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka'))))->format($format);
        } catch (Throwable) {
            return 'Date unavailable';
        }
    }

    private function positiveId(mixed $value): ?int
    {
        return is_scalar($value) && preg_match('/\A[1-9][0-9]*\z/', (string) $value) === 1 ? (int) $value : null;
    }

    private function notFound(): Response
    {
        $response = $this->render('errors/404', ['pageTitle' => 'Certificate not found'], 'dashboard');

        return Response::html($response->body(), 404);
    }
}
