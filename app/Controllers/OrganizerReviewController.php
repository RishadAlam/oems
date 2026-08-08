<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\ReviewService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class OrganizerReviewController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly ReviewService $reviewService,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $result = $this->reviewService->organizerReviews((int) ($this->auth->id() ?? 0));
        if (!($result['success'] ?? false)) {
            return Response::html('Forbidden', 403);
        }

        return $this->render('organizer/reviews/index', [
            'pageTitle' => 'Event reviews',
            'reviews' => is_array($result['reviews'] ?? null) ? $result['reviews'] : [],
        ], 'dashboard');
    }

    public function reply(Request $request): Response
    {
        $reviewId = $this->positiveId($request->route('id'));
        if ($reviewId === null) {
            return $this->notFound();
        }

        $reply = $request->input('reply');
        $result = $this->reviewService->reply((int) ($this->auth->id() ?? 0), $reviewId, $reply);
        if (!($result['success'] ?? false)) {
            if (($result['code'] ?? null) === 'not_found') {
                return $this->notFound();
            }

            return $this->redirectWithErrors(
                '/organizer/reviews',
                is_array($result['errors'] ?? null) ? $result['errors'] : ['reply' => ['The reply could not be saved.']],
                [
                    'reply_review_id' => (string) $reviewId,
                    'reply' => is_scalar($reply) ? (string) $reply : '',
                ],
            );
        }

        return $this->redirectWith('/organizer/reviews', 'success', 'Your reply was saved.');
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
