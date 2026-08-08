<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\FavoriteService;
use OEMS\Tests\Support\FakeFavoriteRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;

final class FavoriteServiceTest extends TestCase
{
    private FakeFavoriteRepository $favorites;

    private FakeUserRepository $users;

    private mixed $service = null;

    protected function setUp(): void
    {
        $this->favorites = new FakeFavoriteRepository();
        $this->users = new FakeUserRepository();
        $this->users->users[7] = [
            'id' => 7,
            'role_id' => 3,
            'name' => 'Verified participant',
            'email' => 'participant@example.test',
            'status' => 'active',
            'email_verified_at' => '2026-08-01 10:00:00',
        ];

        if (class_exists(FavoriteService::class)) {
            $this->service = new FavoriteService($this->favorites, $this->users);
        }
    }

    public function testSaveRevalidatesActiveVerifiedParticipantAndPositiveEventId(): void
    {
        $service = $this->service();

        $this->assertSame(['success' => false, 'code' => 'invalid_event'], $service->save(7, 0));
        $this->users->users[7]['email_verified_at'] = null;
        $this->assertSame(['success' => false, 'code' => 'invalid_participant'], $service->save(7, 41));
        $this->users->users[7]['email_verified_at'] = '2026-08-01 10:00:00';
        $this->users->users[7]['status'] = 'suspended';
        $this->assertSame(['success' => false, 'code' => 'invalid_participant'], $service->save(7, 41));
    }

    public function testSaveReturnsExplicitEligibilityOutcomeAndIsIdempotent(): void
    {
        $service = $this->service();

        $this->assertSame(['success' => true, 'code' => 'saved'], $service->save(7, 41));
        $this->assertSame(['success' => true, 'code' => 'saved'], $service->save(7, 41));
        $this->assertTrue($this->favorites->existsForParticipant(7, 41));
        $this->favorites->allowsSave = false;
        $this->assertSame(['success' => false, 'code' => 'event_not_available'], $service->save(7, 42));
    }

    public function testRemoveIsParticipantScopedAndIdempotent(): void
    {
        $service = $this->service();
        $this->favorites->favorites[8][41] = true;

        $this->assertSame(['success' => true, 'code' => 'removed'], $service->remove(7, 41));
        $this->assertSame(['success' => true, 'code' => 'removed'], $service->remove(7, 41));
        $this->assertTrue($this->favorites->existsForParticipant(8, 41));
    }

    private function service(): FavoriteService
    {
        $this->assertTrue($this->service instanceof FavoriteService, 'Favorite service is missing.');

        return $this->service;
    }
}
