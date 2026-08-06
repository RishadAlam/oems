<?php

declare(strict_types=1);

use OEMS\App\Controllers\AuthController;
use OEMS\App\Controllers\DashboardController;
use OEMS\App\Controllers\HomeController;
use OEMS\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index'], name: 'home');
    $router->get('/events', [HomeController::class, 'events'], name: 'events.index');

    $router->get('/login', [AuthController::class, 'showLogin'], ['guest'], 'login');
    $router->post('/login', [AuthController::class, 'login'], ['guest', 'csrf'], 'login.submit');
    $router->get('/register', [AuthController::class, 'showRegister'], ['guest'], 'register');
    $router->post('/register', [AuthController::class, 'register'], ['guest', 'csrf'], 'register.submit');
    $router->get('/verify-email/{token}', [AuthController::class, 'verifyEmail'], ['guest'], 'verify-email');
    $router->get('/forgot-password', [AuthController::class, 'showForgotPassword'], ['guest'], 'password.request');
    $router->post('/forgot-password', [AuthController::class, 'sendResetLink'], ['guest', 'csrf'], 'password.email');
    $router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword'], ['guest'], 'password.reset');
    $router->post('/reset-password/{token}', [AuthController::class, 'resetPassword'], ['guest', 'csrf'], 'password.update');

    $router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf'], 'logout');
    $router->get('/dashboard', [DashboardController::class, 'index'], ['auth'], 'dashboard');
    $router->get('/participant/dashboard', [DashboardController::class, 'participant'], ['role:participant'], 'participant.dashboard');
    $router->get('/organizer/dashboard', [DashboardController::class, 'organizer'], ['role:organizer'], 'organizer.dashboard');
    $router->get('/admin/dashboard', [DashboardController::class, 'admin'], ['role:super-admin'], 'admin.dashboard');
    $router->get('/settings/password', [AuthController::class, 'showChangePassword'], ['auth'], 'password.change');
    $router->post('/settings/password', [AuthController::class, 'changePassword'], ['auth', 'csrf'], 'password.change.submit');
};

