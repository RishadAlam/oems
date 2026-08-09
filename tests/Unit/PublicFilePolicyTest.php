<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\PublicFilePolicy;
use OEMS\Tests\Support\TestCase;

final class PublicFilePolicyTest extends TestCase
{
    private string $publicRoot;

    protected function setUp(): void
    {
        $this->publicRoot = sys_get_temp_dir() . '/oems-public-policy-' . bin2hex(random_bytes(6));
        mkdir($this->publicRoot . '/uploads/tickets', 0775, true);
        mkdir($this->publicRoot . '/assets', 0775, true);
        file_put_contents($this->publicRoot . '/uploads/tickets/legacy.pdf', '%PDF-private');
        file_put_contents($this->publicRoot . '/assets/app.css', 'body{}');
    }

    protected function tearDown(): void
    {
        unlink($this->publicRoot . '/uploads/tickets/legacy.pdf');
        unlink($this->publicRoot . '/assets/app.css');
        rmdir($this->publicRoot . '/uploads/tickets');
        rmdir($this->publicRoot . '/uploads');
        rmdir($this->publicRoot . '/assets');
        rmdir($this->publicRoot);
    }

    public function testTicketArtifactsAreNeverServedAsPublicStaticFiles(): void
    {
        $this->assertFalse(PublicFilePolicy::mayServe($this->publicRoot, '/uploads/tickets/legacy.pdf'));
        $this->assertTrue(PublicFilePolicy::mayServe($this->publicRoot, '/assets/app.css'));
    }
}
