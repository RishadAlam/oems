<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\ProfileRepositoryInterface;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\Validator;
use OEMS\Core\View;

final class ProfileController extends Controller
{
    private const FIELDS = [
        'name',
        'phone',
        'bio',
        'date_of_birth',
        'gender',
        'address_line',
        'city',
        'country',
        'postal_code',
        'website',
        'locale',
        'timezone',
    ];

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly ProfileRepositoryInterface $profiles,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function edit(Request $request): Response
    {
        $userId = $this->auth->id();
        $profile = $userId === null ? null : $this->profiles->findForUser($userId);

        if ($profile === null) {
            return $this->redirectWith('/dashboard', 'error', 'Your profile could not be loaded.');
        }

        return $this->render('profile/edit', [
            'pageTitle' => 'Profile',
            'profile' => $profile,
        ], 'dashboard');
    }

    public function update(Request $request): Response
    {
        $data = $request->only(self::FIELDS);
        $errors = Validator::validate($data, [
            'name' => 'required|string|min:2|max:100',
            'phone' => 'nullable|string|max:30',
            'bio' => 'nullable|string|max:2000',
            'date_of_birth' => 'nullable|date|before_or_equal_date:today',
            'gender' => 'nullable|in:female,male,non-binary,prefer-not-to-say',
            'address_line' => 'nullable|string|max:190',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30',
            'website' => 'nullable|string|url|max:255',
            'locale' => 'required|in:en,bn',
            'timezone' => 'required|in:Asia/Dhaka,UTC',
        ]);

        if ($errors !== []) {
            return $this->redirectWithErrors('/profile', $errors, $data);
        }

        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login');
        }

        $this->profiles->updateForUser($userId, $this->normalize($data));

        return $this->redirectWith('/profile', 'success', 'Profile updated successfully.');
    }

    private function normalize(array $data): array
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $normalized[$field] = $value === '' && !in_array($field, ['name', 'locale', 'timezone'], true)
                ? null
                : $value;
        }

        return $normalized;
    }
}
