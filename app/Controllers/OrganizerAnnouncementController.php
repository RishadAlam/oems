<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\AnnouncementService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class OrganizerAnnouncementController extends Controller
{
    private const INTENT_SESSION_KEY = 'announcement_send_intents';

    private const INTENT_TTL = 600;

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly AnnouncementService $announcements,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $context = $this->workspace($request);
        if ($context === null) {
            return $this->notFound();
        }

        return $this->render('organizer/announcements/index', [
            'pageTitle' => 'Announcements',
            'event' => $context['event'],
            'announcements' => $context['announcements'],
            'canSend' => $context['can_send'],
        ], 'dashboard');
    }

    public function create(Request $request): Response
    {
        $context = $this->workspace($request);
        if ($context === null) {
            return $this->notFound();
        }

        return $this->renderComposer($context);
    }

    public function store(Request $request): Response
    {
        $eventId = $this->positiveInt($request->route('id'));
        $organizerUserId = $this->auth->id();
        if ($eventId === null || $organizerUserId === null) {
            return $this->notFound();
        }

        if ($request->input('confirm_send') === '1') {
            return $this->confirm($request, $organizerUserId, $eventId);
        }

        $review = $this->announcements->review(
            $organizerUserId,
            $eventId,
            $request->input('subject'),
            $request->input('message'),
        );
        if (($review['success'] ?? false) !== true) {
            if (($review['code'] ?? null) === 'not_found') {
                return $this->notFound();
            }
            if (($review['code'] ?? null) === 'ineligible') {
                $context = $this->announcements->workspace($organizerUserId, $eventId);
                if ($context === null) {
                    return $this->notFound();
                }

                return $this->composerResponse(
                    $context,
                    'Announcements are available only for published or completed events from approved organizers.',
                    409,
                );
            }

            return $this->redirectWithErrors(
                '/organizer/events/' . $eventId . '/announcements/create',
                is_array($review['errors'] ?? null) ? $review['errors'] : [],
                $this->safeOld($request),
            );
        }

        $requestKey = bin2hex(random_bytes(32));
        $intents = $this->activeIntents();
        $intents[hash('sha256', $requestKey)] = [
            'organizer_user_id' => $organizerUserId,
            'event_id' => $eventId,
            'subject' => (string) $review['data']['subject'],
            'message' => (string) $review['data']['message'],
            'expires_at' => time() + self::INTENT_TTL,
        ];
        $this->session->put(self::INTENT_SESSION_KEY, array_slice($intents, -5, null, true));
        $context = $this->announcements->workspace($organizerUserId, $eventId);
        if ($context === null) {
            return $this->notFound();
        }

        return $this->renderComposer($context, [
            'request_key' => $requestKey,
            'subject' => (string) $review['data']['subject'],
            'message' => (string) $review['data']['message'],
        ]);
    }

    private function confirm(Request $request, int $organizerUserId, int $eventId): Response
    {
        $rawKey = $request->input('request_key');
        $requestKey = is_scalar($rawKey) ? (string) $rawKey : '';
        $intentKey = preg_match('/\A[a-f0-9]{64}\z/D', $requestKey) === 1
            ? hash('sha256', $requestKey)
            : '';
        $intents = $this->activeIntents();
        $intent = $intentKey === '' ? null : ($intents[$intentKey] ?? null);
        $valid = is_array($intent)
            && (int) ($intent['organizer_user_id'] ?? 0) === $organizerUserId
            && (int) ($intent['event_id'] ?? 0) === $eventId
            && is_string($intent['subject'] ?? null)
            && is_string($intent['message'] ?? null);
        $context = $this->announcements->workspace($organizerUserId, $eventId);
        if ($context === null) {
            return $this->notFound();
        }

        if (!$valid) {
            return $this->composerResponse(
                $context,
                'This confirmation is invalid or has expired. Review the announcement and begin again.',
                409,
            );
        }

        $result = $this->announcements->send(
            $organizerUserId,
            $eventId,
            $intent['subject'],
            $intent['message'],
            $requestKey,
            [
                'ip_address' => $request->ip(),
                'user_agent' => is_scalar($request->header('User-Agent'))
                    ? (string) $request->header('User-Agent')
                    : '',
            ],
        );
        if (($result['success'] ?? false) === true) {
            $recipientCount = (int) ($result['announcement']['recipient_count'] ?? 0);
            if (($result['replayed'] ?? false) === true) {
                $this->session->flash(
                    'info',
                    'This announcement was already sent to ' . $recipientCount
                        . ' confirmed participant' . ($recipientCount === 1 ? '' : 's')
                        . '. No duplicate notifications were created.',
                );
            } else {
                $this->session->flash(
                    'success',
                    'Announcement sent to ' . $recipientCount
                        . ' confirmed participant' . ($recipientCount === 1 ? '' : 's') . '.',
                );
            }

            return Response::redirect('/organizer/events/' . $eventId . '/announcements');
        }

        $error = $this->firstError($result['errors'] ?? []);
        $status = ($result['code'] ?? null) === 'persistence' ? 500 : 409;

        return $this->composerResponse($context, $error, $status, [
            'request_key' => $requestKey,
            'subject' => (string) $intent['subject'],
            'message' => (string) $intent['message'],
        ]);
    }

    private function workspace(Request $request): ?array
    {
        $organizerUserId = $this->auth->id();
        $eventId = $this->positiveInt($request->route('id'));

        return $organizerUserId === null || $eventId === null
            ? null
            : $this->announcements->workspace($organizerUserId, $eventId);
    }

    private function renderComposer(
        array $context,
        ?array $confirmation = null,
        ?string $actionError = null,
    ): Response {
        return $this->render('organizer/announcements/create', [
            'pageTitle' => 'Send announcement',
            'event' => $context['event'],
            'canSend' => $context['can_send'],
            'confirmation' => $confirmation,
            'actionError' => $actionError,
        ], 'dashboard');
    }

    private function composerResponse(
        array $context,
        string $error,
        int $status,
        ?array $confirmation = null,
    ): Response {
        $response = $this->renderComposer($context, $confirmation, $error);

        return Response::html($response->body(), $status);
    }

    private function activeIntents(): array
    {
        $stored = $this->session->get(self::INTENT_SESSION_KEY, []);
        if (!is_array($stored)) {
            return [];
        }

        $now = time();
        $active = array_filter(
            $stored,
            static fn (mixed $intent): bool => is_array($intent)
                && (int) ($intent['expires_at'] ?? 0) >= $now,
        );
        if (count($active) !== count($stored)) {
            $this->session->put(self::INTENT_SESSION_KEY, $active);
        }

        return $active;
    }

    private function safeOld(Request $request): array
    {
        $old = [];
        $subject = $request->input('subject');
        $message = $request->input('message');
        if (is_scalar($subject)) {
            $old['subject'] = mb_substr((string) $subject, 0, 180);
        }
        if (is_scalar($message)) {
            $old['message'] = mb_substr((string) $message, 0, 1000);
        }

        return $old;
    }

    private function positiveInt(mixed $value): ?int
    {
        return (is_int($value) || is_string($value))
            && ctype_digit((string) $value)
            && (int) $value > 0
                ? (int) $value
                : null;
    }

    private function firstError(array $errors): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && is_string($messages[0] ?? null)) {
                return $messages[0];
            }
        }

        return 'The announcement could not be sent. Please try again.';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404);
    }
}
