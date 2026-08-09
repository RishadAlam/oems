<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\NotificationRepositoryInterface;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class ParticipantNotificationController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly NotificationRepositoryInterface $notifications,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login');
        }
        $page = $this->positiveInt($request->query('page')) ?? 1;
        $unreadCount = $this->notifications->unreadCountForUser($userId);

        return $this->render('participant/notifications/index', [
            'pageTitle' => 'Notifications',
            'notifications' => $this->notifications->forUser($userId, $page, 20),
            'unreadCount' => $unreadCount,
            'unreadNotifications' => $unreadCount,
        ], 'dashboard');
    }

    public function markRead(Request $request): Response
    {
        $userId = $this->auth->id();
        $notificationId = $this->positiveInt($request->route('id'));
        if ($userId !== null && $notificationId !== null) {
            $this->notifications->markReadForUser($userId, $notificationId);
        }

        return Response::redirect('/participant/notifications');
    }

    public function markAllRead(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId !== null) {
            $this->notifications->markAllReadForUser($userId);
        }

        return Response::redirect('/participant/notifications');
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_scalar($value) && preg_match('/^[1-9][0-9]*$/', (string) $value) === 1
            ? (int) $value
            : null;
    }
}
