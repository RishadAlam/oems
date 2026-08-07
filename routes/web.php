<?php

declare(strict_types=1);

use OEMS\App\Controllers\AuthController;
use OEMS\App\Controllers\DashboardController;
use OEMS\App\Controllers\HomeController;
use OEMS\App\Controllers\OrganizerEventController;
use OEMS\App\Controllers\OrganizerVenueController;
use OEMS\App\Controllers\ProfileController;
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
    $router->get('/profile', [ProfileController::class, 'edit'], ['auth'], 'profile.edit');
    $router->post('/profile', [ProfileController::class, 'update'], ['auth', 'csrf'], 'profile.update');
    $router->get('/participant/dashboard', [DashboardController::class, 'participant'], ['role:participant'], 'participant.dashboard');
    $router->get('/organizer/dashboard', [DashboardController::class, 'organizer'], ['role:organizer'], 'organizer.dashboard');
    $router->get('/organizer/events', [OrganizerEventController::class, 'index'], ['role:organizer'], 'organizer.events.index');
    $router->get('/organizer/events/create', [OrganizerEventController::class, 'create'], ['role:organizer'], 'organizer.events.create');
    $router->post('/organizer/events', [OrganizerEventController::class, 'store'], ['role:organizer', 'csrf'], 'organizer.events.store');
    $router->get('/organizer/events/{id}', [OrganizerEventController::class, 'show'], ['role:organizer'], 'organizer.events.show');
    $router->get('/organizer/events/{id}/edit', [OrganizerEventController::class, 'edit'], ['role:organizer'], 'organizer.events.edit');
    $router->post('/organizer/events/{id}', [OrganizerEventController::class, 'update'], ['role:organizer', 'csrf'], 'organizer.events.update');
    $router->post('/organizer/events/{id}/submit', [OrganizerEventController::class, 'submit'], ['role:organizer', 'csrf'], 'organizer.events.submit');
    $router->post('/organizer/events/{id}/cancel', [OrganizerEventController::class, 'cancel'], ['role:organizer', 'csrf'], 'organizer.events.cancel');
    $router->post('/organizer/events/{id}/delete', [OrganizerEventController::class, 'delete'], ['role:organizer', 'csrf'], 'organizer.events.delete');
    $router->get('/organizer/venues', [OrganizerVenueController::class, 'index'], ['role:organizer'], 'organizer.venues.index');
    $router->get('/organizer/venues/create', [OrganizerVenueController::class, 'create'], ['role:organizer'], 'organizer.venues.create');
    $router->post('/organizer/venues', [OrganizerVenueController::class, 'store'], ['role:organizer', 'csrf'], 'organizer.venues.store');
    $router->get('/organizer/venues/{id}/edit', [OrganizerVenueController::class, 'edit'], ['role:organizer'], 'organizer.venues.edit');
    $router->post('/organizer/venues/{id}', [OrganizerVenueController::class, 'update'], ['role:organizer', 'csrf'], 'organizer.venues.update');
    $router->post('/organizer/venues/{id}/delete', [OrganizerVenueController::class, 'delete'], ['role:organizer', 'csrf'], 'organizer.venues.delete');
    $router->get('/admin/dashboard', [DashboardController::class, 'admin'], ['role:super-admin'], 'admin.dashboard');
    $router->get('/settings/password', [AuthController::class, 'showChangePassword'], ['auth'], 'password.change');
    $router->post('/settings/password', [AuthController::class, 'changePassword'], ['auth', 'csrf'], 'password.change.submit');
};
