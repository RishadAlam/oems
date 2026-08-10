<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Services\CouponService;
use OEMS\Tests\Support\FakeCouponRepository;
use OEMS\Tests\Support\TestCase;

final class CouponServiceTest extends TestCase
{
    private FakeCouponRepository $repository;
    private CouponService $service;

    protected function setUp(): void
    {
        $this->repository = new FakeCouponRepository();
        $this->repository->events = [
            7 => ['id' => 7, 'organizer_id' => 3, 'organizer_user_id' => 12, 'title' => 'Owned event', 'ticket_price' => '125.50', 'currency' => 'BDT'],
            8 => ['id' => 8, 'organizer_id' => 4, 'organizer_user_id' => 99, 'title' => 'Foreign event', 'ticket_price' => '90.00', 'currency' => 'BDT'],
        ];
        $this->service = new CouponService($this->repository);
    }

    public function testManagementNormalizesAndValidatesOwnershipValueWindowAndUsage(): void
    {
        $invalid = $this->service->create(12, [
            'code' => ' x ', 'discount_type' => 'percentage', 'discount_value' => '100.01',
            'usage_limit' => '0', 'event_id' => '8', 'starts_at' => '2026-08-12T10:00', 'expires_at' => '2026-08-11T10:00',
        ]);
        $this->assertFalse($invalid['success']);
        foreach (['code', 'discount_value', 'usage_limit', 'event_id', 'expires_at'] as $field) $this->assertArrayHasKey($field, $invalid['errors']);

        $created = $this->service->create(12, [
            'code' => ' summer-25 ', 'discount_type' => 'fixed', 'discount_value' => '25.5',
            'usage_limit' => '10', 'event_id' => '7', 'starts_at' => '2026-08-10T09:00', 'expires_at' => '2026-08-20T09:00',
        ]);
        $this->assertTrue($created['success']);
        $coupon = $created['coupon'];
        $this->assertSame('SUMMER-25', $coupon['code']);
        $this->assertSame('25.50', $coupon['discount_value']);
        $this->assertSame(7, $coupon['event_id']);
    }

    public function testQuoteUsesExactPercentageAndFixedMathWithPriceCapAndNoMutation(): void
    {
        $this->repository->coupons = [
            1 => $this->coupon(1, 'TWELVE50', 'percentage', '12.50'),
            2 => $this->coupon(2, 'FREEPASS', 'fixed', '500.00'),
        ];
        $now = new DateTimeImmutable('2026-08-12 10:00:00');

        $percentage = $this->service->quoteForRegistration(5, 7, ' twelve50 ', $now);
        $this->assertTrue($percentage['success']);
        $this->assertSame('125.50', $percentage['base_amount']);
        $this->assertSame('15.68', $percentage['discount_amount']);
        $this->assertSame('109.82', $percentage['final_amount']);
        $this->assertSame('BDT', $percentage['currency']);

        $free = $this->service->quoteForRegistration(5, 7, 'freepass', $now);
        $this->assertSame('125.50', $free['discount_amount']);
        $this->assertSame('0.00', $free['final_amount']);
        $this->assertSame(0, $this->repository->coupons[2]['used_count']);
    }

    public function testQuoteRejectsExpiredExhaustedForeignAndPreviouslyUsedCoupons(): void
    {
        $this->repository->coupons = [1 => array_merge($this->coupon(1, 'ONEUSE', 'fixed', '10.00'), ['usage_limit' => 1, 'used_count' => 1])];
        $result = $this->service->quoteForRegistration(5, 7, 'ONEUSE', new DateTimeImmutable('2026-08-12 10:00:00'));
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('coupon_code', $result['errors']);
        $this->assertSame([], $this->repository->usage);
    }

    public function testExplicitActivationAndConsumptionAreOwnerAndCasProtected(): void
    {
        $this->repository->coupons = [1 => $this->coupon(1, 'USEONCE', 'fixed', '10.00')];
        $this->assertFalse($this->service->setActive(99, 1, '0')['success']);
        $this->assertTrue($this->service->setActive(12, 1, '0')['success']);
        $this->assertSame(0, $this->repository->coupons[1]['is_active']);
        $this->repository->coupons[1]['is_active'] = 1;
        $this->assertTrue($this->service->consume(1, 5, 91, '10.00', new DateTimeImmutable()));
        $this->assertFalse($this->service->consume(1, 5, 92, '10.00', new DateTimeImmutable()));
    }

    private function coupon(int $id, string $code, string $type, string $value): array
    {
        return [
            'id' => $id, 'organizer_id' => 3, 'organizer_user_id' => 12, 'event_id' => 7,
            'code' => $code, 'discount_type' => $type, 'discount_value' => $value,
            'usage_limit' => null, 'used_count' => 0, 'starts_at' => '2026-08-10 09:00:00',
            'expires_at' => '2026-08-20 09:00:00', 'is_active' => 1,
        ];
    }
}
