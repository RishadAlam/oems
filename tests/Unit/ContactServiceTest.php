<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\ContactService;
use OEMS\App\Repositories\ContactRepository;
use OEMS\App\Repositories\MailOutboxRepository;
use OEMS\App\Services\MailOutboxService;
use OEMS\Tests\Support\FakeContactRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class ContactServiceTest extends TestCase
{
    public function testSubmissionValidatesBoundsNormalizesAndTreatsHoneypotAsSafeNoOp(): void
    {
        $repository = new FakeContactRepository();
        $service = new ContactService($repository);
        $invalid = $service->submit(['name' => '', 'email' => 'bad', 'subject' => '', 'message' => 'short']);
        $this->assertFalse($invalid['success']); $this->assertArrayHasKey('email', $invalid['errors']);
        $spam = $service->submit(['website' => 'bot', 'name' => 'Bot', 'email' => 'bot@example.test', 'subject' => 'Spam', 'message' => str_repeat('x', 20)]);
        $this->assertTrue($spam['success']); $this->assertSame(0, count($repository->rows));
        $valid = $service->submit(['name' => ' Ada ', 'email' => 'ADA@EXAMPLE.TEST', 'subject' => ' Support ', 'message' => str_repeat('x', 20)]);
        $this->assertTrue($valid['success']); $this->assertSame('ada@example.test', $valid['message']['email']);
        $invalidQueue = $service->index(['status' => 'forged', 'search' => str_repeat('x', 101), 'page' => 'bad']);
        $this->assertFalse($invalidQueue['valid']); $this->assertSame([], $invalidQueue['messages']);
    }

    public function testReplyAndAuditRollBackWhenOutboxPersistenceFails(): void
    {
        $pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE contact_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, subject TEXT, message TEXT, status TEXT DEFAULT "new", replied_by INTEGER, replied_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, subject_type TEXT, subject_id INTEGER, description TEXT, properties TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->createOutbox($pdo);
        $repository = new ContactRepository($pdo); $message = $repository->create(['name' => 'Ada', 'email' => 'ada@example.test', 'subject' => 'Support', 'message' => 'Please help me.']);
        $pdo->exec("CREATE TRIGGER reject_contact_outbox BEFORE INSERT ON mail_outbox BEGIN SELECT RAISE(FAIL, 'outbox unavailable'); END");
        $service = new ContactService($repository, new MailOutboxService(new MailOutboxRepository($pdo)), $pdo);
        $result = $service->reply(9, (int) $message['id'], 'Here is a safe support reply.');
        $this->assertFalse($result['success']); $this->assertSame('new', $repository->findForAdmin((int) $message['id'])['status']);
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()); $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM mail_outbox')->fetchColumn());
    }

    private function createOutbox(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE mail_outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, template TEXT, recipient_email TEXT, payload TEXT, idempotency_key TEXT UNIQUE, status TEXT DEFAULT 'queued', attempts INTEGER DEFAULT 0, available_at TEXT, lock_token TEXT, locked_at TEXT, sent_at TEXT, provider_message_id TEXT, last_error TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    }
}
