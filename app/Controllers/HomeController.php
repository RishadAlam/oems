<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\FavoriteRepositoryInterface;
use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\App\Services\LocationService;
use OEMS\App\Services\PlatformSettingsService;
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
        private readonly ?RegistrationRepositoryInterface $registrations = null,
        private readonly ?LocationService $locations = null,
        private readonly ?PlatformSettingsService $settings = null,
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
        $siteSettings = $this->settings?->publicValues() ?? PlatformSettingsService::defaults();
        $description = $siteSettings['default_seo_description'];

        return $this->render('home/index', [
            'pageTitle' => $siteSettings['home_hero_title'],
            'metaDescription' => $description,
            'canonicalUrl' => $canonicalUrl,
            'openGraph' => [
                'type' => 'website',
                'title' => $siteSettings['home_hero_title'],
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
        $locations = $this->locations ?? new LocationService();
        $event = $locations->presentEventLocation($event, $this->canViewExactLocation($event, $locations));
        $isFree = Money::isFree($event['ticket_price'] ?? null);

        return array_merge($event, [
            'date' => $start->format('M j, Y'),
            'time' => $start->format('g:i A'),
            'datetime' => $start->format(DATE_ATOM),
            'category' => (string) ($event['category_name'] ?? 'Event'),
            'venue' => (string) $event['venue_display'],
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

    private function canViewExactLocation(array $event, LocationService $locations): bool
    {
        $userId = $this->auth->id();
        $registrationStatus = null;

        if ($userId !== null && $this->auth->hasRole('participant') && $this->registrations !== null) {
            $registration = $this->registrations->findForParticipantEvent($userId, (int) ($event['id'] ?? 0));
            $registrationStatus = $registration['registration_status'] ?? $registration['status'] ?? null;
        }

        return $locations->canViewExactLocation(
            $event,
            $userId,
            $this->auth->hasRole('super-admin'),
            $this->auth->hasRole('organizer'),
            is_string($registrationStatus) ? $registrationStatus : null,
        );
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
