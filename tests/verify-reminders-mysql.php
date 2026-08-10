<?php

declare(strict_types=1);

use OEMS\App\Repositories\MailOutboxRepository;
use OEMS\App\Repositories\RegistrationRepository;
use OEMS\App\Services\CalendarService;
use OEMS\App\Services\EventReminderService;
use OEMS\App\Services\MailOutboxService;

require dirname(__DIR__) . '/vendor/autoload.php';

$connection = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', (int) (getenv('DB_PORT') ?: 3306), getenv('DB_DATABASE') ?: ''),
    getenv('DB_USERNAME') ?: 'root',
    getenv('DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],
);
$identity = $connection->query(
    "SELECT registrations.id AS registration_id, registrations.user_id, registrations.event_id
     FROM registrations
     INNER JOIN users ON users.id = registrations.user_id
     INNER JOIN events ON events.id = registrations.event_id
     WHERE registrations.status = 'confirmed'
     ORDER BY registrations.id LIMIT 1",
)->fetch();
if (!is_array($identity)) {
    throw new RuntimeException('The reminder verifier requires a confirmed demo registration.');
}

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Dhaka'));
$start = $now->modify('+23 hours');
$event = $connection->prepare(
    "UPDATE events SET status = 'published', deleted_at = NULL, registration_deadline = :registration_deadline,
                       start_date = :start_date, end_date = :end_date
     WHERE id = :event_id",
);
$event->execute([
    'registration_deadline' => $now->modify('+1 hour')->format('Y-m-d H:i:s'),
    'start_date' => $start->format('Y-m-d H:i:s'),
    'end_date' => $start->modify('+2 hours')->format('Y-m-d H:i:s'),
    'event_id' => (int) $identity['event_id'],
]);
$user = $connection->prepare("UPDATE users SET status = 'active', deleted_at = NULL, email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP) WHERE id = :user_id");
$user->execute(['user_id' => (int) $identity['user_id']]);

$registrations = new RegistrationRepository($connection);
$service = new EventReminderService(
    $registrations,
    new MailOutboxService(new MailOutboxRepository($connection)),
    'Asia/Dhaka',
);
$first = $service->queueDue($now, 25);
$second = $service->queueDue($now, 25);
$count = (int) $connection->query("SELECT COUNT(*) FROM mail_outbox WHERE template = 'event_reminder'")->fetchColumn();
$target = $connection->prepare(
    "SELECT COUNT(*) FROM mail_outbox
     WHERE template = 'event_reminder'
       AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.registration_id')) = :registration_id",
);
$target->execute(['registration_id' => (string) $identity['registration_id']]);
$targetCount = (int) $target->fetchColumn();
if (($first['queued'] ?? 0) < 1 || ($second['queued'] ?? -1) !== 0 || $count !== (int) $first['queued'] || $targetCount !== 1) {
    throw new RuntimeException('Native reminder idempotency verification failed: ' . json_encode([
        'first' => $first,
        'second' => $second,
        'count' => $count,
        'target_count' => $targetCount,
    ], JSON_THROW_ON_ERROR));
}

$owned = $registrations->findCalendarForParticipant((int) $identity['user_id'], (int) $identity['registration_id']);
if (!is_array($owned)) {
    throw new RuntimeException('Native owned calendar scope verification failed.');
}
$ics = (new CalendarService('Asia/Dhaka', 'https://events.example.test', $now))->forOwnedRegistration($owned);
if (!str_contains($ics, "BEGIN:VCALENDAR\r\n") || !str_contains($ics, 'UID:registration-')) {
    throw new RuntimeException('Native calendar artifact verification failed.');
}

echo "Native MySQL reminder and calendar verification passed.\n";
