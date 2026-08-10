<?php

declare(strict_types=1);

use OEMS\App\Repositories\ContactRepository;
use OEMS\App\Repositories\MailOutboxRepository;
use OEMS\App\Repositories\NewsletterRepository;
use OEMS\App\Services\ContactService;
use OEMS\App\Services\MailOutboxService;
use OEMS\App\Services\NewsletterService;

require dirname(__DIR__) . '/vendor/autoload.php';

$connection = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', (int) (getenv('DB_PORT') ?: 3306), getenv('DB_DATABASE') ?: ''),
    getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],
);
$contacts = new ContactRepository($connection);
$newsletter = new NewsletterRepository($connection);
$outbox = new MailOutboxService(new MailOutboxRepository($connection));
$contactService = new ContactService($contacts, $outbox, $connection);
$newsletterService = new NewsletterService($newsletter, $outbox, $connection);
$administratorId = (int) $connection->query("SELECT users.id FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.slug = 'super-admin' ORDER BY users.id LIMIT 1")->fetchColumn();

$message = $contacts->create(['name' => 'Native Contact', 'email' => 'native-contact@example.test', 'subject' => 'Native support', 'message' => 'A native prepared contact message.']);
if (!is_array($message) || $contacts->countForAdmin(['status' => 'new', 'search' => 'native-contact']) !== 1) throw new RuntimeException('Native contact queue verification failed.');
$connection->exec("CREATE TRIGGER reject_native_contact_outbox BEFORE INSERT ON mail_outbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'outbox unavailable'");
$reply = $contactService->reply($administratorId, (int) $message['id'], 'A reply that must roll back.');
if (($reply['success'] ?? true) || ($contacts->findForAdmin((int) $message['id'])['status'] ?? null) !== 'new') throw new RuntimeException('Native contact rollback verification failed.');
$connection->exec('DROP TRIGGER reject_native_contact_outbox');
$reply = $contactService->reply($administratorId, (int) $message['id'], 'A queued native support reply.');
if (!($reply['success'] ?? false) || ($contacts->findForAdmin((int) $message['id'])['status'] ?? null) !== 'replied') throw new RuntimeException('Native contact reply verification failed.');

$now = new DateTimeImmutable(); $confirmationToken = str_repeat('c', 64);
$subscriber = $newsletter->savePending('native-subscriber@example.test', hash('sha256', $confirmationToken), hash('sha256', str_repeat('u', 64)), $now->modify('+1 hour'), $now);
if (!is_array($subscriber) || $newsletter->confirm(hash('sha256', $confirmationToken), $now) === null || $newsletter->confirm(hash('sha256', $confirmationToken), $now) !== null) throw new RuntimeException('Native newsletter confirmation verification failed.');
$campaign = $newsletterService->createCampaign($administratorId, ['subject' => 'Native campaign', 'message' => 'A native prepared newsletter campaign.']);
if (!($campaign['success'] ?? false)) throw new RuntimeException('Native campaign creation failed.');
$queued = $newsletterService->queueCampaign($administratorId, (int) $campaign['campaign']['id']);
$repeat = $newsletterService->queueCampaign($administratorId, (int) $campaign['campaign']['id']);
if (!($queued['success'] ?? false) || ($queued['queued_count'] ?? 0) < 1 || !($repeat['idempotent'] ?? false) || $repeat['queued_count'] !== $queued['queued_count']) throw new RuntimeException('Native campaign idempotency verification failed.');
$campaignJobs = (int) $connection->query("SELECT COUNT(*) FROM mail_outbox WHERE template = 'newsletter_campaign'")->fetchColumn();
if ($campaignJobs !== (int) $queued['queued_count']) throw new RuntimeException('Native campaign fanout created duplicate or missing jobs.');

echo "Native MySQL contact and newsletter verification passed.\n";
