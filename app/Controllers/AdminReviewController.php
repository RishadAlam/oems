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

final class AdminReviewController extends Controller
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
        $value = $request->query('status');
        $status = is_scalar($value) ? mb_strtolower(trim((string) $value)) : null;
        $result = $this->reviewService->adminQueue((int) ($this->auth->id() ?? 0), $status);
        if (!($result['success'] ?? false)) {
            return Response::html('Forbidden', 403);
        }

        return $this->render('admin/reviews/index', [
            'pageTitle' => 'Review moderation',
            'reviews' => is_array($result['reviews'] ?? null) ? $result['reviews'] : [],
            'status' => $result['status'] ?? null,
        ], 'dashboard');
    }

    public function publish(Request $request): Response
    {
        return $this->moderate($request, 'published');
    }

    public function hide(Request $request): Response
    {
        return $this->moderate($request, 'hidden');
    }

    private function moderate(Request $request, string $status): Response
    {
        $reviewId = $this->positiveId($request->route('id'));
        if ($reviewId === null) {
            return $this->notFound();
        }

        $result = $this->reviewService->moderate((int) ($this->auth->id() ?? 0), $reviewId, $status);
        if (!($result['success'] ?? false)) {
            if (($result['code'] ?? null) === 'conflict') {
                $response = $this->render('errors/404', ['pageTitle' => 'Review state changed'], 'dashboard');

                return Response::html($response->body(), 409);
            }

            return $this->notFound();
        }

        return $this->redirectWith(
            '/admin/reviews',
            'success',
            $status === 'published' ? 'The review is published.' : 'The review is hidden.',
        );
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
