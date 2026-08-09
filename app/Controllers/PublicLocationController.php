<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use InvalidArgumentException;
use OEMS\App\Services\LocationService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class PublicLocationController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly LocationService $locations,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function store(Request $request): Response
    {
        try {
            $location = $this->locations->preference(
                $request->input('latitude'),
                $request->input('longitude'),
                $request->input('radius'),
                $this->inputString($request, 'label', 'Current area'),
                $this->inputString($request, 'source', 'device'),
            );
        } catch (InvalidArgumentException) {
            $errors = ['location' => ['Enter a valid location.']];

            if ($this->expectsJson($request)) {
                return Response::json(['errors' => $errors], 422);
            }

            return $this->redirectWithErrors('/events', $errors);
        }

        $this->session->put('event_location', $location);

        return Response::redirect('/events?radius=' . $location['radius'] . '&sort=distance');
    }

    public function clear(Request $request): Response
    {
        $this->session->forget('event_location');

        return Response::redirect('/events');
    }

    private function expectsJson(Request $request): bool
    {
        $accept = (string) $request->header('accept', '');

        return str_contains($accept, 'application/json')
            || strtolower((string) $request->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    private function inputString(Request $request, string $key, string $default): string
    {
        $value = $request->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }
}
