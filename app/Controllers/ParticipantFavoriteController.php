<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\FavoriteRepositoryInterface;
use OEMS\App\Support\Money;
use OEMS\App\Services\FavoriteService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class ParticipantFavoriteController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly FavoriteRepositoryInterface $favorites,
        private readonly FavoriteService $favoriteService,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $participantId = $this->auth->id();
        if ($participantId === null) {
            return Response::redirect('/login');
        }

        $history = $this->favorites->forParticipant($participantId, $this->page($request), 12);
        $items = is_array($history['items'] ?? null) ? $history['items'] : [];

        return $this->render('participant/favorites/index', [
            'pageTitle' => 'Saved events',
            'favorites' => array_map($this->presentFavorite(...), $items),
            'pagination' => is_array($history['pagination'] ?? null) ? $history['pagination'] : [
                'page' => 1,
                'per_page' => 12,
                'total' => 0,
                'last_page' => 1,
            ],
        ], 'dashboard');
    }

    public function store(Request $request): Response
    {
        $result = $this->favoriteService->save((int) ($this->auth->id() ?? 0), $this->positiveId($request->route('id')) ?? 0);

        return $this->redirectResult($this->destination($request), $result, 'Event saved.', 'This event is not available to save.');
    }

    public function destroy(Request $request): Response
    {
        $result = $this->favoriteService->remove((int) ($this->auth->id() ?? 0), $this->positiveId($request->route('id')) ?? 0);

        return $this->redirectResult($this->destination($request), $result, 'Event removed from saved events.', 'This saved event could not be updated.');
    }

    private function redirectResult(string $destination, array $result, string $success, string $failure): Response
    {
        if (($result['success'] ?? false) === true) {
            return $this->redirectWith($destination, 'success', $success);
        }

        return $this->redirectWith($destination, 'error', $failure);
    }

    private function destination(Request $request): string
    {
        $candidate = is_scalar($request->input('return_to')) ? (string) $request->input('return_to') : '';

        if ($candidate === '/' || $candidate === '/events' || preg_match('#^/events/[a-z0-9]+(?:-[a-z0-9]+)*$#', $candidate) === 1) {
            return $candidate;
        }

        if (preg_match('#^/participant/favorites(?:\?page=[1-9][0-9]*)?$#', $candidate) === 1) {
            return $candidate;
        }

        return '/participant/favorites';
    }

    private function page(Request $request): int
    {
        $value = $request->query('page', 1);

        return is_scalar($value) && preg_match('/^[1-9][0-9]*$/', (string) $value) === 1 ? (int) $value : 1;
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_scalar($value) || preg_match('/^[1-9][0-9]*$/', (string) $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function presentFavorite(array $favorite): array
    {
        $start = trim((string) ($favorite['start_date'] ?? ''));
        $isFree = Money::isFree($favorite['ticket_price'] ?? null);

        return array_merge($favorite, [
            'start_display' => $start === '' ? 'Schedule unavailable' : (new DateTimeImmutable($start, $this->timezone()))->format('M j, Y, g:i A'),
            'price_display' => $isFree ? 'Free' : Money::format($favorite['ticket_price'] ?? null, (string) ($favorite['currency'] ?? 'BDT')),
            'image' => (string) (($favorite['banner'] ?? '') ?: '/assets/images/event-creative.webp'),
        ]);
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka'));
    }

}
