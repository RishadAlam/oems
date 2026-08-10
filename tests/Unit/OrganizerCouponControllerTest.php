<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;

final class OrganizerCouponControllerTest extends TestCase
{
    public function testCouponRoutesViewsAndNavigationExposeOnlyOrganizerProtectedWrites(): void
    {
        $routes = file_get_contents(base_path('routes/web.php')) ?: '';
        $layout = file_get_contents(base_path('app/Views/layouts/dashboard.php')) ?: '';
        $index = file_get_contents(base_path('app/Views/organizer/coupons/index.php')) ?: '';
        $form = file_get_contents(base_path('app/Views/organizer/coupons/form.php')) ?: '';

        $this->assertTrue(str_contains($routes, "'/organizer/coupons'"));
        $this->assertTrue(str_contains($routes, "['role:organizer', 'csrf']"));
        $this->assertTrue(str_contains($layout, 'Coupons'));
        $this->assertTrue(str_contains($index, 'data-label='));
        $this->assertTrue(str_contains($form, 'aria-describedby'));
        $this->assertTrue(str_contains($form, 'field-error'));
        $this->assertFalse(str_contains($form, 'coupon_id'));
    }

    public function testParticipantCheckoutAndDetailExplainCouponInputAndExactDiscountEvidence(): void
    {
        $checkout = file_get_contents(base_path('app/Views/participant/registrations/register.php')) ?: '';
        $detail = file_get_contents(base_path('app/Views/participant/registrations/show.php')) ?: '';

        $this->assertTrue(str_contains($checkout, 'name="coupon_code"'));
        $this->assertTrue(str_contains($checkout, 'coupon-code-help'));
        $this->assertTrue(str_contains($detail, 'Discount'));
        $this->assertTrue(str_contains($detail, 'Coupon applied'));
    }
}
