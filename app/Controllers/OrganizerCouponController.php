<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\CouponRepositoryInterface;
use OEMS\App\Services\CouponService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

final class OrganizerCouponController extends Controller
{
    private const FIELDS = ['event_id', 'code', 'discount_type', 'discount_value', 'usage_limit', 'starts_at', 'expires_at'];

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly CouponRepositoryInterface $coupons,
        private readonly CouponService $couponService,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) return Response::redirect('/login');
        $workspace = $this->couponService->index($userId);
        return $this->render('organizer/coupons/index', ['pageTitle' => 'Coupons', ...$workspace], 'dashboard');
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) return Response::redirect('/login');
        $input = $this->input($request);
        $result = $this->couponService->create($userId, $input);
        if (!$result['success']) return $this->redirectWithErrors('/organizer/coupons/create', $result['errors'], $input);
        $this->session->flash('success', 'Coupon created.');
        return Response::redirect('/organizer/coupons');
    }

    public function edit(Request $request): Response
    {
        $coupon = $this->owned($request);
        return $coupon === null ? Response::text('Not Found', 404) : $this->form($coupon);
    }

    public function update(Request $request): Response
    {
        $userId = $this->auth->id();
        $id = $this->id($request->route('id'));
        if ($userId === null) return Response::redirect('/login');
        if ($id === null || $this->coupons->findOwned($userId, $id) === null) return Response::text('Not Found', 404);
        $input = $this->input($request);
        $result = $this->couponService->update($userId, $id, $input);
        if (!$result['success']) return $this->redirectWithErrors('/organizer/coupons/' . $id . '/edit', $result['errors'], $input);
        $this->session->flash('success', 'Coupon updated.');
        return Response::redirect('/organizer/coupons');
    }

    public function status(Request $request): Response
    {
        $userId = $this->auth->id();
        $id = $this->id($request->route('id'));
        if ($userId === null) return Response::redirect('/login');
        if ($id === null || $this->coupons->findOwned($userId, $id) === null) return Response::text('Not Found', 404);
        $result = $this->couponService->setActive($userId, $id, $request->input('is_active'));
        if (!$result['success']) return $this->redirectWith('/organizer/coupons', 'error', 'The coupon status could not be changed.');
        $this->session->flash('success', $result['is_active'] ? 'Coupon activated.' : 'Coupon deactivated.');
        return Response::redirect('/organizer/coupons');
    }

    private function form(?array $coupon): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) return Response::redirect('/login');
        return $this->render('organizer/coupons/form', [
            'pageTitle' => $coupon === null ? 'Create coupon' : 'Edit coupon',
            'coupon' => $coupon,
            'events' => $this->coupons->eventsForOrganizerUser($userId),
        ], 'dashboard');
    }

    private function owned(Request $request): ?array
    {
        $id = $this->id($request->route('id'));
        $userId = $this->auth->id();
        return $id === null || $userId === null ? null : $this->coupons->findOwned($userId, $id);
    }

    private function input(Request $request): array
    {
        return array_filter($request->only(self::FIELDS), static fn (mixed $value): bool => is_scalar($value));
    }

    private function id(mixed $value): ?int
    {
        return is_scalar($value) && preg_match('/\A[1-9][0-9]*\z/D', (string) $value) === 1 ? (int) $value : null;
    }
}
