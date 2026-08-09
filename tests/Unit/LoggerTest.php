<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\Logger;
use OEMS\Tests\Support\TestCase;

final class LoggerTest extends TestCase
{
    public function testRecursivelyRedactsCaseInsensitiveCredentialContext(): void
    {
        $path = sys_get_temp_dir() . '/oems-logger-' . bin2hex(random_bytes(6)) . '.log';
        $logger = new Logger($path);

        try {
            $logger->error('Authentication boundary failed.', [
                'request' => [
                    'Password' => 'plain-secret',
                    'authorization' => 'Bearer raw-token',
                ],
                'safe_id' => 42,
            ]);
            $record = (string) file_get_contents($path);

            $this->assertFalse(str_contains($record, 'plain-secret'));
            $this->assertFalse(str_contains($record, 'raw-token'));
            $this->assertTrue(str_contains($record, '"safe_id":42'));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
