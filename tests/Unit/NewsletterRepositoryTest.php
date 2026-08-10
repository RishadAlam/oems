<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use DateTimeImmutable;
use OEMS\App\Repositories\NewsletterRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class NewsletterRepositoryTest extends TestCase
{
    public function testTokensAreHashedConsumedOnceAndCampaignStateUsesCas(): void
    {
        $pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE newsletter (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, status TEXT, confirmation_token_hash TEXT UNIQUE, confirmation_expires_at TEXT, confirmed_at TEXT, unsubscribe_token_hash TEXT UNIQUE, subscribed_at TEXT, unsubscribed_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE newsletter_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, subject TEXT, message TEXT, status TEXT, created_by INTEGER, recipient_count INTEGER DEFAULT 0, queued_count INTEGER DEFAULT 0, request_key TEXT UNIQUE, scheduled_at TEXT, queued_at TEXT, sent_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $repository = new NewsletterRepository($pdo); $now = new DateTimeImmutable('2026-08-10 12:00:00');
        $subscriber = $repository->savePending('a@example.test', hash('sha256', 'confirm'), hash('sha256', 'unsubscribe'), $now->modify('+1 hour'), $now);
        $this->assertNotNull($subscriber); $this->assertSame('pending', $subscriber['status']);
        $repository->savePending('b@example.test', hash('sha256', 'other-confirm'), hash('sha256', 'other-unsubscribe'), $now->modify('+1 hour'), $now);
        $confirmed = $repository->confirm(hash('sha256', 'confirm'), $now); $this->assertNotNull($confirmed); $this->assertNull($repository->confirm(hash('sha256', 'confirm'), $now));
        $this->assertSame('a@example.test', $confirmed['email']);
        $campaign = $repository->createCampaign(9, ['subject' => 'Update', 'message' => 'News', 'request_key' => hash('sha256', 'campaign'), 'scheduled_at' => null]);
        $this->assertNotNull($campaign); $this->assertTrue($repository->markCampaignQueued((int) $campaign['id'], 1, 1, $now)); $this->assertFalse($repository->markCampaignQueued((int) $campaign['id'], 1, 1, $now));
    }
}
