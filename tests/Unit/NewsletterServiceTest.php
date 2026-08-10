<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\NewsletterService;
use OEMS\Tests\Support\FakeMailOutboxRepository;
use OEMS\Tests\Support\FakeNewsletterRepository;
use OEMS\Tests\Support\TestCase;
use OEMS\App\Services\MailOutboxService;
use OEMS\App\Repositories\MailOutboxRepository;
use OEMS\App\Repositories\NewsletterRepository;
use PDO;

final class NewsletterServiceTest extends TestCase
{
    public function testSubscribeIsEnumerationSafeAndQueuesOnlyHashedDoubleOptIn(): void
    {
        $repository = new FakeNewsletterRepository(); $outbox = new FakeMailOutboxRepository();
        $service = new NewsletterService($repository, new MailOutboxService($outbox));
        $invalid = $service->subscribe('bad'); $this->assertFalse($invalid['success']);
        $valid = $service->subscribe('Person@Example.Test'); $this->assertTrue($valid['success']);
        $this->assertSame(1, count($repository->subscribers)); $this->assertSame(1, count($outbox->jobs));
        $row = array_values($repository->subscribers)[0]; $this->assertSame(64, strlen($row['confirmation_token_hash']));
        $this->assertFalse(str_contains(json_encode($row, JSON_THROW_ON_ERROR), '/newsletter/confirm/'));
    }

    public function testCampaignQueuesConfirmedRecipientsOnceWithoutExposingAddresses(): void
    {
        $repository = new FakeNewsletterRepository(); $outbox = new FakeMailOutboxRepository();
        $service = new NewsletterService($repository, new MailOutboxService($outbox));
        $service->subscribe('one@example.test'); $row = array_values($repository->subscribers)[0]; $repository->subscribers[$row['id']]['status'] = 'subscribed';
        $campaign = $service->createCampaign(9, ['subject' => 'Weekly events', 'message' => 'Three events are open.']);
        $this->assertTrue($campaign['success']);
        $queued = $service->queueCampaign(9, (int) $campaign['campaign']['id']); $this->assertTrue($queued['success']); $this->assertSame(1, $queued['queued_count']);
        $repeat = $service->queueCampaign(9, (int) $campaign['campaign']['id']); $this->assertTrue($repeat['success']); $this->assertSame(1, $repeat['queued_count']);
    }

    public function testCampaignFanoutRollsBackSubscriberTokensAndStateWhenOutboxFails(): void
    {
        $pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE newsletter (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, status TEXT, confirmation_token_hash TEXT UNIQUE, confirmation_expires_at TEXT, confirmed_at TEXT, unsubscribe_token_hash TEXT UNIQUE, subscribed_at TEXT, unsubscribed_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE newsletter_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, subject TEXT, message TEXT, status TEXT, created_by INTEGER, recipient_count INTEGER DEFAULT 0, queued_count INTEGER DEFAULT 0, request_key TEXT UNIQUE, scheduled_at TEXT, queued_at TEXT, sent_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("CREATE TABLE mail_outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, template TEXT, recipient_email TEXT, payload TEXT, idempotency_key TEXT UNIQUE, status TEXT DEFAULT 'queued', attempts INTEGER DEFAULT 0, available_at TEXT, lock_token TEXT, locked_at TEXT, sent_at TEXT, provider_message_id TEXT, last_error TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO newsletter (email, status, confirmation_token_hash, confirmation_expires_at, unsubscribe_token_hash, subscribed_at) VALUES ('one@example.test', 'subscribed', NULL, NULL, '" . str_repeat('a', 64) . "', CURRENT_TIMESTAMP)");
        $repository = new NewsletterRepository($pdo); $service = new NewsletterService($repository, new MailOutboxService(new MailOutboxRepository($pdo)), $pdo);
        $campaign = $service->createCampaign(9, ['subject' => 'Weekly events', 'message' => 'Three events are open.']);
        $pdo->exec("CREATE TRIGGER reject_newsletter_outbox BEFORE INSERT ON mail_outbox BEGIN SELECT RAISE(FAIL, 'outbox unavailable'); END");
        $queued = $service->queueCampaign(9, (int) $campaign['campaign']['id']);
        $this->assertFalse($queued['success']); $this->assertSame('draft', $repository->findCampaign((int) $campaign['campaign']['id'])['status']);
        $this->assertSame(str_repeat('a', 64), (string) $pdo->query('SELECT unsubscribe_token_hash FROM newsletter WHERE id = 1')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM mail_outbox')->fetchColumn());
    }
}
