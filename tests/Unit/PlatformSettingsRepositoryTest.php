<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\PlatformSettingsRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class PlatformSettingsRepositoryTest extends TestCase
{
    public function testReadsOnlyRequestedPublicKeysAndTransactionallyUpsertsCatalogValues(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE settings (id INTEGER PRIMARY KEY AUTOINCREMENT, `group` TEXT NOT NULL, `key` TEXT NOT NULL UNIQUE, `value` TEXT NULL, value_type TEXT NOT NULL, is_public INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $pdo->exec("INSERT INTO settings (`group`, `key`, `value`, value_type, is_public, created_at, updated_at) VALUES ('general','site_name','Before','string',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),('mail','smtp_password','secret','secret',0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $repository = new PlatformSettingsRepository($pdo);

        $this->assertSame(['site_name' => 'Before'], $repository->valuesForKeys(['site_name', 'smtp_password']));
        $repository->updateMany(['site_name' => 'After', 'contact_email' => 'hello@example.test']);

        $this->assertSame(['contact_email' => 'hello@example.test', 'site_name' => 'After'], array_replace([], ...array_map(static fn(array $row): array => [$row['key'] => $row['value']], $pdo->query("SELECT `key`,`value` FROM settings WHERE is_public=1 ORDER BY `key`")->fetchAll())));
        $this->assertSame('secret', $pdo->query("SELECT `value` FROM settings WHERE `key`='smtp_password'")->fetchColumn());
    }
}
