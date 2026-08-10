<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Tests\Support\TestCase;

final class AnnouncementFeatureSurfaceTest extends TestCase
{
    public function testOrganizerAnnouncementFeatureHasItsApplicationBoundaries(): void
    {
        $this->assertTrue(interface_exists(\OEMS\App\Contracts\AnnouncementRepositoryInterface::class));
        $this->assertTrue(class_exists(\OEMS\App\Repositories\AnnouncementRepository::class));
        $this->assertTrue(class_exists(\OEMS\App\Services\AnnouncementService::class));
        $this->assertTrue(class_exists(\OEMS\App\Controllers\OrganizerAnnouncementController::class));
    }
}
