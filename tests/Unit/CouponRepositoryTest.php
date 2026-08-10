<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Repositories\CouponRepository;
use OEMS\App\Support\Money;
use OEMS\Tests\Support\TestCase;
use PDO;

final class CouponRepositoryTest extends TestCase
{
    private PDO $connection;
    private CouponRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->schema();
        $this->repository = new CouponRepository($this->connection);
    }

    public function testOrganizerManagementIsOwnedDeterministicAndCannotCrossEvents(): void
    {
        $created = $this->repository->createOwned(12, $this->attributes('OWNED25', 7));
        $this->assertNotNull($created);
        $this->assertSame(3, (int) $created['organizer_id']);
        $this->assertNull($this->repository->createOwned(12, $this->attributes('FOREIGN25', 8)));
        $this->assertSame(1, count($this->repository->forOrganizerUser(12)));
        $this->assertSame(0, count($this->repository->forOrganizerUser(99)));
        $this->assertFalse($this->repository->updateOwned(99, (int) $created['id'], ['code' => 'STOLEN']));
        $this->assertTrue($this->repository->setActiveOwned(12, (int) $created['id'], false));
        $this->assertSame(0, (int) $this->repository->findOwned(12, (int) $created['id'])['is_active']);
    }

    public function testEligibilityScopesOrganizerEventWindowCapacityAndPreviousParticipantUse(): void
    {
        $coupon = $this->repository->createOwned(12, $this->attributes('SAVE25', 7));
        $now = new DateTimeImmutable('2026-08-12 10:00:00');
        $eligible = $this->repository->findRedeemable(5, 7, 'SAVE25', $now, false);
        $this->assertNotNull($eligible);
        $this->assertSame('125.50', Money::normalize((string) $eligible['ticket_price']));
        $this->assertNull($this->repository->findRedeemable(5, 8, 'SAVE25', $now, false));
        $this->assertNull($this->repository->findRedeemable(5, 7, 'SAVE25', new DateTimeImmutable('2026-08-21'), false));

        $this->assertTrue($this->repository->consume((int) $coupon['id'], 5, 91, '25.00', $now));
        $this->assertFalse($this->repository->consume((int) $coupon['id'], 5, 92, '25.00', $now));
        $this->assertNull($this->repository->findRedeemable(5, 7, 'SAVE25', $now, false));
        $this->assertSame(1, (int) $this->connection->query('SELECT used_count FROM coupons')->fetchColumn());
        $this->assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM coupon_usage')->fetchColumn());
    }

    private function attributes(string $code, int $eventId): array
    {
        return [
            'event_id' => $eventId, 'code' => $code, 'discount_type' => 'fixed', 'discount_value' => '25.00',
            'usage_limit' => 1, 'starts_at' => '2026-08-10 09:00:00', 'expires_at' => '2026-08-20 09:00:00', 'is_active' => 1,
        ];
    }

    private function schema(): void
    {
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, title TEXT NOT NULL, ticket_price NUMERIC NOT NULL, currency TEXT NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE coupons (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER NULL, organizer_id INTEGER NOT NULL, code TEXT NOT NULL UNIQUE, discount_type TEXT NOT NULL, discount_value NUMERIC NOT NULL, usage_limit INTEGER NULL, used_count INTEGER NOT NULL DEFAULT 0, starts_at TEXT NULL, expires_at TEXT NULL, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE coupon_usage (id INTEGER PRIMARY KEY AUTOINCREMENT, coupon_id INTEGER NOT NULL, user_id INTEGER NOT NULL, registration_id INTEGER NOT NULL UNIQUE, discount_amount NUMERIC NOT NULL, used_at TEXT NOT NULL, UNIQUE(coupon_id, user_id))');
        $this->connection->exec("INSERT INTO organizers VALUES (3, 12), (4, 99)");
        $this->connection->exec("INSERT INTO events VALUES (7, 3, 'Owned event', 125.50, 'BDT', NULL), (8, 4, 'Foreign event', 90, 'BDT', NULL)");
        $this->connection->exec("INSERT INTO registrations VALUES (91, 7, 5), (92, 7, 5)");
    }
}
