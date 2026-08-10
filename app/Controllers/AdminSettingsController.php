<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Services\PlatformSettingsService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class AdminSettingsController extends Controller
{
    private const FIELDS = ['site_name', 'site_tagline', 'contact_email', 'support_phone', 'footer_blurb', 'footer_location', 'home_hero_kicker', 'home_hero_title', 'home_hero_copy', 'default_seo_description'];

    public function __construct(View $view, Session $session, Security $security, Auth $auth, Config $config, private readonly PlatformSettingsService $settings)
    {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function edit(Request $request): Response
    {
        return $this->render('admin/settings/edit', ['pageTitle' => 'Platform settings', 'settings' => $this->settings->publicValues()], 'dashboard');
    }

    public function update(Request $request): Response
    {
        $input = array_filter($request->only(self::FIELDS), 'is_scalar');
        $result = $this->settings->update($input);
        if (!$result['success']) return $this->redirectWithErrors('/admin/settings', $result['errors'], $input);
        $this->session->flash('success', 'Platform settings saved.');
        return Response::redirect('/admin/settings');
    }
}
