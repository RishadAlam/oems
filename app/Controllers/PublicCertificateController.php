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
use Throwable;

final class PublicCertificateController extends Controller
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

    public function show(Request $request): Response
    {
        $token = $request->route('token');
        $verification = is_scalar($token) ? $this->certificates->verify((string) $token) : null;
        $available = $verification !== null;
        if ($available) {
            $verification['completion_display'] = $this->date($verification['completion_date'] ?? null);
            $verification['issued_display'] = $this->date($verification['issued_at'] ?? null);
        }
        $response = $this->render('certificates/verify', [
            'pageTitle' => $available ? 'Certificate verified' : 'Certificate unavailable',
            'verification' => $verification,
        ], 'public');

        return $available ? $response : Response::html($response->body(), 404);
    }

    private function date(mixed $value): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return 'Date unavailable';
        }
        try {
            return (new DateTimeImmutable((string) $value, new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka'))))->format('F j, Y');
        } catch (Throwable) {
            return 'Date unavailable';
        }
    }
}
