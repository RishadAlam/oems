<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\ContactRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class ContactRepositoryTest extends TestCase
{
    public function testQueueIsBoundedFilteredCasProtectedAndAudited(): void
    {
        $pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE contact_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, subject TEXT, message TEXT, status TEXT DEFAULT "new", replied_by INTEGER, replied_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, subject_type TEXT, subject_id INTEGER, description TEXT, properties TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $repository = new ContactRepository($pdo);
        $row = $repository->create(['name' => '<Ada>', 'email' => 'ada@example.test', 'subject' => 'Help', 'message' => 'Need help']);
        $this->assertNotNull($row);
        $this->assertSame(1, $repository->countForAdmin(['status' => 'new', 'search' => 'ada@example.test']));
        $this->assertSame(1, count($repository->forAdmin(['status' => 'new', 'search' => 'ada'], 20, 0)));
        $this->assertTrue($repository->setStatus((int) $row['id'], 'new', 'read', 9));
        $this->assertFalse($repository->setStatus((int) $row['id'], 'new', 'archived', 9));
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn());
    }
}
