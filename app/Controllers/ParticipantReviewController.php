<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\ReviewService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\RateLimiter;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class ParticipantReviewController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly ReviewService $reviewService,
        private readonly RateLimiter $limiter,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $result = $this->reviewService->participantReviews((int) ($this->auth->id() ?? 0));
        if (!($result['success'] ?? false)) {
            return Response::html('Forbidden', 403);
        }

        return $this->render('participant/reviews/index', [
            'pageTitle' => 'My reviews',
            'reviews' => is_array($result['reviews'] ?? null) ? $result['reviews'] : [],
            'eligibleEvents' => is_array($result['eligible_events'] ?? null) ? $result['eligible_events'] : [],
        ], 'dashboard');
    }

    public function create(Request $request): Response
    {
        $eventId = $this->positiveId($request->route('id'));
        if ($eventId === null) {
            return $this->notFound();
        }

        $result = $this->reviewService->participantForm((int) ($this->auth->id() ?? 0), $eventId);
        if (!($result['success'] ?? false)) {
            return $this->notFound();
        }

        return $this->render('participant/reviews/form', [
            'pageTitle' => 'Review ' . (string) ($result['event']['title'] ?? 'event'),
            'event' => $result['event'],
            'review' => is_array($result['review'] ?? null) ? $result['review'] : null,
        ], 'dashboard');
    }

    public function store(Request $request): Response
    {
        $eventId = $this->positiveId($request->route('id'));
        if ($eventId === null) {
            return $this->notFound();
        }

        $userId = (int) ($this->auth->id() ?? 0);
        $limitKey = 'participant-review:' . $userId . ':' . $eventId . ':' . hash('sha256', $request->ip());
        if (!$this->limiter->consumeAttempt($limitKey)) {
            $this->session->flash('errors', [
                'review' => ['Too many review attempts. Wait before trying again.'],
            ]);
            $limited = $this->create($request);

            return $limited->status() === 200
                ? Response::html($limited->body(), 429)
                : Response::html('<h1>Too many review attempts</h1><p>Wait before trying again.</p>', 429);
        }

        $rating = $request->input('rating');
        $comment = $request->input('review');
        $result = $this->reviewService->submit(
            $userId,
            $eventId,
            $rating,
            $comment,
        );

        if (!($result['success'] ?? false)) {
            return $this->redirectWithErrors(
                '/participant/events/' . $eventId . '/review',
                is_array($result['errors'] ?? null) ? $result['errors'] : ['review' => ['The review could not be saved.']],
                [
                    'rating' => is_scalar($rating) ? (string) $rating : '',
                    'review' => is_scalar($comment) ? (string) $comment : '',
                ],
            );
        }

        return $this->redirectWith('/participant/reviews', 'success', 'Your review was submitted for moderation.');
    }

    private function positiveId(mixed $value): ?int
    {
        return is_scalar($value) && preg_match('/^[1-9][0-9]*$/', (string) $value) === 1
            ? (int) $value
            : null;
    }

    private function notFound(): Response
    {
        $response = $this->render('errors/404', ['pageTitle' => 'Review not found'], 'dashboard');

        return Response::html($response->body(), 404);
    }
}
