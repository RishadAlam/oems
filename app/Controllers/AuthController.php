<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\AuthService;
use OEMS\App\Services\AccountMailer;
use OEMS\App\Support\RememberCookie;
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
            'password' => 'required|string|max:1024',
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
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:190',
            'password' => 'required|string|min:8|max:128|confirmed',
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

        $result = $this->authService->register($data, $request->ip());

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

        $this->session->flash('old', [
            'email' => strtolower(trim((string) $data['email'])),
        ]);
        $this->session->flash(
            'success',
            'Account created. Check your inbox and verify your email before signing in.',
        );

        return Response::redirect('/verify-email/resend');
    }

    public function showResendVerification(Request $request): Response
    {
        return $this->render('auth/resend-verification', [
            'pageTitle' => 'Resend verification email',
        ], 'auth');
    }

    public function resendVerification(Request $request): Response
    {
        $data = $request->only(['email']);
        $submittedEmail = $data['email'] ?? null;
        $data['email'] = is_scalar($submittedEmail)
            ? strtolower(trim((string) $submittedEmail))
            : '';
        $errors = Validator::validate($data, [
            'email' => 'required|email|max:190',
        ]);

        if ($errors !== []) {
            return $this->redirectWithErrors('/verify-email/resend', $errors, [
                'email' => $data['email'] ?? '',
            ]);
        }

        $normalizedEmail = (string) $data['email'];
        $result = $this->authService->requestEmailVerification($normalizedEmail, $request->ip());

        if (($result['mail_dispatch'] ?? null) === 'verification'
            && is_string($result['verification_token'] ?? null)
            && is_int($result['user_id'] ?? null)
            && is_string($result['name'] ?? null)
            && is_string($result['email'] ?? null)) {
            $this->accountMailer->sendVerification(
                $result['user_id'],
                $result['email'],
                $result['name'],
                $result['verification_token'],
            );
        } elseif (($result['mail_dispatch'] ?? null) === 'probe') {
            $this->accountMailer->sendVerificationPrivacyProbe();
        }

        $this->session->flash('old', ['email' => $normalizedEmail]);

        return $this->redirectWith(
            '/verify-email/resend',
            'success',
            'If the address needs verification, a new link is on its way. Use the newest email you receive.',
        );
    }

    public function verifyEmail(Request $request): Response
    {
        $verified = $this->authService->verifyEmail((string) $request->route('token'));

        if (!$verified) {
            return $this->redirectWith(
                '/verify-email/resend',
                'error',
                'This verification link is invalid, expired, or has already been used. Request a new one below.',
            );
        }

        return $this->auth->check()
            ? $this->redirectWith('/profile', 'success', 'Email verified successfully.')
            : $this->redirectWith('/login', 'success', 'Email verified. You can now sign in.');
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
            'password' => 'required|string|min:8|max:128|confirmed',
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
            'current_password' => 'required|string|max:1024',
            'password' => 'required|string|min:8|max:128|confirmed',
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
        return (new RememberCookie(
            (string) $this->config->get('remember_cookie', 'OEMS_REMEMBER'),
            (bool) $this->config->get('secure_cookies', false),
        ))->header($value, $expires);
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
            || preg_match('#^/organizer/check-in\?token=[a-f0-9]{64}$#i', $candidate) === 1
                ? $candidate
                : null;
    }

    private function loginLocation(?string $returnTo): string
    {
        return $returnTo === null ? '/login' : '/login?return_to=' . rawurlencode($returnTo);
    }
}
