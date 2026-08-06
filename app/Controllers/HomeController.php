<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('home/index', [
            'pageTitle' => 'Events worth showing up for',
            'featuredEvents' => $this->featuredEvents(),
        ]);
    }

    public function events(Request $request): Response
    {
        return $this->render('events/index', [
            'pageTitle' => 'Explore events',
            'featuredEvents' => $this->featuredEvents(),
            'search' => trim((string) $request->query('search', '')),
        ]);
    }

    private function featuredEvents(): array
    {
        return [
            [
                'title' => 'Designing for public life',
                'category' => 'Creative workshop',
                'date' => 'August 22',
                'time' => '10:00 AM',
                'venue' => 'Dhanmondi, Dhaka',
                'price' => 'Free',
                'image' => '/assets/images/event-creative.webp',
                'alt' => 'A collaborative design workshop around a studio table',
            ],
            [
                'title' => 'Rooftop sessions',
                'category' => 'Music and culture',
                'date' => 'August 29',
                'time' => '7:30 PM',
                'venue' => 'Banani, Dhaka',
                'price' => 'From ৳600',
                'image' => '/assets/images/event-community.webp',
                'alt' => 'A rooftop music gathering at blue hour',
            ],
        ];
    }
}

