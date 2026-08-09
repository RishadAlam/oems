<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\FavoriteRepositoryInterface;
use OEMS\App\Support\Money;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class HomeController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly EventRepositoryInterface $events,
        private readonly ?FavoriteRepositoryInterface $favorites = null,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $featured = $this->events->featured(2);
        $favoriteStates = $this->favoriteStates($featured);
        $featuredEvents = array_map(
            fn (array $event): array => $this->presentEvent($event, isset($favoriteStates[(int) ($event['id'] ?? 0)])),
            $featured,
        );
        $canonicalUrl = rtrim((string) $this->config->get('url', 'http://localhost:8000'), '/') . '/';
        $description = 'Discover published workshops, talks, and gatherings with OEMS.';

        return $this->render('home/index', [
            'pageTitle' => 'Events worth showing up for',
            'metaDescription' => $description,
            'canonicalUrl' => $canonicalUrl,
            'openGraph' => [
                'type' => 'website',
                'title' => 'Events worth showing up for',
                'description' => $description,
                'url' => $canonicalUrl,
            ],
            'featuredEvents' => $featuredEvents,
        ]);
    }

    private function presentEvent(array $event, bool $isFavorited = false): array
    {
        $timezone = new DateTimeZone((string) $this->config->get('timezone', 'Asia/Dhaka'));
        $start = new DateTimeImmutable((string) $event['start_date'], $timezone);
        $venue = array_values(array_filter([
            trim((string) ($event['venue_name'] ?? '')),
            trim((string) ($event['venue_city'] ?? '')),
        ], static fn (string $value): bool => $value !== ''));
        $isFree = Money::isFree($event['ticket_price'] ?? null);

        return array_merge($event, [
            'date' => $start->format('M j, Y'),
            'time' => $start->format('g:i A'),
            'datetime' => $start->format(DATE_ATOM),
            'category' => (string) ($event['category_name'] ?? 'Event'),
            'venue' => $venue === [] ? 'Venue to be announced' : implode(', ', $venue),
            'price' => $isFree
                ? 'Free'
                : Money::format($event['ticket_price'] ?? null, (string) ($event['currency'] ?? 'BDT')),
            'image' => (string) (($event['banner'] ?? '') ?: '/assets/images/event-creative.webp'),
            'alt' => 'Banner for ' . (string) $event['title'],
            'favorite' => [
                'is_participant' => $this->auth->hasRole('participant'),
                'is_guest' => $this->auth->guest(),
                'is_saved' => $isFavorited,
            ],
        ]);
    }

    /** @return array<int, bool> */
    private function favoriteStates(array $events): array
    {
        if ($this->favorites === null || !$this->auth->hasRole('participant')) {
            return [];
        }

        $eventIds = array_map(
            static fn (array $event): int => (int) ($event['id'] ?? 0),
            $events,
        );

        return $this->favorites->statesForParticipant((int) $this->auth->id(), $eventIds);
    }

}
