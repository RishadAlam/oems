<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\AuthService;
use OEMS\App\Services\AccountMailer;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\Validator;
use OEMS\Core\View;

final class AuthController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly AuthService $authService,
        private readonly AccountMailer $accountMailer,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function showLogin(Request $request): Response
    {
        return $this->render('auth/login', [
            'pageTitle' => 'Welcome back',
            'returnTo' => $this->safeLoginReturnTo($request->query('return_to')),
        ], 'auth');
    }

    public function login(Request $request): Response
    {
        $data = $request->only(['email', 'password', 'remember', 'return_to']);
        $returnTo = $this->safeLoginReturnTo($data['return_to'] ?? null);
        $errors = Validator::validate($data, [
            'email' => 'required|email|max:190',
            'password' => 'required|string',
        ]);

        if ($errors !== []) {
            return $this->redirectWithErrors($this->loginLocation($returnTo), $errors, ['email' => $data['email'] ?? '']);
        }

        $result = $this->authService->attempt(
            (string) $data['email'],
            (string) $data['password'],
            isset($data['remember']) && $data['remember'] === '1',
            $request->ip(),
            (string) $request->header('User-Agent', ''),
        );

        if (!$result['success']) {
            return $this->redirectWithErrors($this->loginLocation($returnTo), $result['errors'], ['email' => $data['email']]);
        }

        $headers = [];

        if (is_string($result['remember_cookie'] ?? null)) {
            $headers['Set-Cookie'] = $this->rememberCookie((string) $result['remember_cookie'], time() + 2592000);
        }

        return Response::redirect($returnTo ?? '/dashboard', 302, $headers);
    }

    public function showRegister(Request $request): Response
    {
        return $this->render('auth/register', ['pageTitle' => 'Create your account'], 'auth');
    }

    public function register(Request $request): Response
    {
        $data = $request->only(['name', 'email', 'password', 'password_confirmation', 'role', 'terms']);
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:190',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:participant,organizer',
            'terms' => 'required|in:1',
        ]);

        if ($errors !== []) {
            return $this->redirectWithErrors('/register', $errors, [
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? '',
                'role' => $data['role'] ?? 'participant',
            ]);
        }

        $result = $this->authService->register($data);

        if (!$result['success']) {
            return $this->redirectWithErrors('/register', $result['errors'], [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
            ]);
        }

        $this->accountMailer->sendVerification(
            (int) $result['user_id'],
            strtolower(trim((string) $data['email'])),
            trim((string) $data['name']),
            (string) $result['verification_token'],
        );

        $this->session->flash('success', 'Account created. Verify your email to continue.');

        if ((bool) $this->config->get('debug', false)) {
            $this->session->flash(
                'development_link',
                '/verify-email/' . rawurlencode((string) $result['verification_token']),
            );
        }

        return Response::redirect('/login');
    }

    public function verifyEmail(Request $request): Response
    {
        $verified = $this->authService->verifyEmail((string) $request->route('token'));

        return $verified
            ? $this->redirectWith('/login', 'success', 'Email verified. You can now sign in.')
            : $this->redirectWith('/login', 'error', 'This verification link is invalid or has already been used.');
    }

    public function showForgotPassword(Request $request): Response
    {
        return $this->render('auth/forgot-password', ['pageTitle' => 'Reset your password'], 'auth');
    }

    public function sendResetLink(Request $request): Response
    {
        $data = $request->only(['email']);
        $errors = Validator::validate($data, ['email' => 'required|email|max:190']);

        if ($errors !== []) {
            return $this->redirectWithErrors('/forgot-password', $errors, $data);
        }

        $result = $this->authService->requestPasswordReset((string) $data['email'], $request->ip());

        if (is_string($result['reset_token'])
            && is_int($result['user_id'])
            && is_string($result['name'])
            && is_string($result['email'])) {
            $this->accountMailer->sendPasswordReset(
                $result['user_id'],
                $result['email'],
                $result['name'],
                $result['reset_token'],
            );
        } elseif (($result['mail_dispatch'] ?? null) === 'probe') {
            $this->accountMailer->sendPasswordResetPrivacyProbe();
        }

        $this->session->flash('success', 'If that account exists, a password reset link has been prepared.');

        if ((bool) $this->config->get('debug', false) && is_string($result['reset_token'])) {
            $this->session->flash(
                'development_link',
                '/reset-password/' . rawurlencode((string) $result['reset_token']),
            );
        }

        return Response::redirect('/forgot-password');
    }

    public function showResetPassword(Request $request): Response
    {
        return $this->render('auth/reset-password', [
            'pageTitle' => 'Choose a new password',
            'token' => (string) $request->route('token'),
        ], 'auth');
    }

    public function resetPassword(Request $request): Response
    {
        $data = $request->only(['password', 'password_confirmation']);
        $errors = Validator::validate($data, [
            'password' => 'required|string|min:8|confirmed',
        ]);
        $token = (string) $request->route('token');

        if ($errors !== []) {
            return $this->redirectWithErrors('/reset-password/' . rawurlencode($token), $errors);
        }

        if (!$this->authService->resetPassword($token, (string) $data['password'])) {
            return $this->redirectWith(
                '/forgot-password',
                'error',
                'This reset link is invalid or expired. Request a new one.',
            );
        }

        return $this->redirectWith('/login', 'success', 'Password updated. Sign in with your new password.');
    }

    public function showChangePassword(Request $request): Response
    {
        return $this->render('auth/change-password', ['pageTitle' => 'Change password'], 'dashboard');
    }

    public function changePassword(Request $request): Response
    {
        $data = $request->only(['current_password', 'password', 'password_confirmation']);
        $errors = Validator::validate($data, [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($errors !== []) {
            return $this->redirectWithErrors('/settings/password', $errors);
        }

        $userId = $this->auth->id();

        if ($userId === null || !$this->authService->changePassword(
            $userId,
            (string) $data['current_password'],
            (string) $data['password'],
        )) {
            return $this->redirectWithErrors('/settings/password', [
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        return $this->redirectWith('/settings/password', 'success', 'Your password has been updated.');
    }

    public function logout(Request $request): Response
    {
        $cookieName = (string) $this->config->get('remember_cookie', 'OEMS_REMEMBER');
        $this->authService->logout((string) $request->cookie($cookieName, ''));

        return Response::redirect('/login', 302, [
            'Set-Cookie' => $this->rememberCookie('', time() - 3600),
        ]);
    }

    private function rememberCookie(string $value, int $expires): string
    {
        $name = (string) $this->config->get('remember_cookie', 'OEMS_REMEMBER');
        $parts = [
            rawurlencode($name) . '=' . rawurlencode($value),
            'Expires=' . gmdate('D, d M Y H:i:s T', $expires),
            'Max-Age=' . max(0, $expires - time()),
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
        ];

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    private function safeLoginReturnTo(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $candidate = trim($value);

        return $candidate === '/'
            || $candidate === '/events'
            || preg_match('#^/events/[a-z0-9]+(?:-[a-z0-9]+)*$#', $candidate) === 1
                ? $candidate
                : null;
    }

    private function loginLocation(?string $returnTo): string
    {
        return $returnTo === null ? '/login' : '/login?return_to=' . rawurlencode($returnTo);
    }
}
