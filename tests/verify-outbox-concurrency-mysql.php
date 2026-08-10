<?php

declare(strict_types=1);

use OEMS\App\Repositories\MailOutboxRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

function outboxConnection(): PDO
{
    $connection = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            (int) (getenv('DB_PORT') ?: 3306),
            getenv('DB_DATABASE') ?: '',
        ),
        getenv('DB_USERNAME') ?: 'root',
        getenv('DB_PASSWORD') ?: '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
    $connection->exec('SET SESSION innodb_lock_wait_timeout = 3');

    return $connection;
}

$firstConnection = outboxConnection();
$secondConnection = outboxConnection();
$firstRepository = new MailOutboxRepository($firstConnection);
$secondRepository = new MailOutboxRepository($secondConnection);
$now = new DateTimeImmutable('2026-08-10 12:00:00');

foreach ([1, 2] as $sequence) {
    $job = $firstRepository->enqueue([
        'template' => 'event_reminder',
        'recipient_email' => "worker{$sequence}@example.test",
        'payload' => ['sequence' => $sequence],
        'idempotency_key' => hash('sha256', "native-outbox-concurrency-{$sequence}"),
        'available_at' => '2026-08-10 11:00:00',
    ]);
    if (!is_array($job)) {
        throw new RuntimeException('The outbox fixture could not be queued.');
    }
}

$firstConnection->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
$firstConnection->beginTransaction();
$firstClaim = $firstRepository->claimBatch(1, 'native-worker-1', $now);
if (count($firstClaim) !== 1) {
    throw new RuntimeException('The first worker did not claim exactly one job.');
}

$secondClaim = $secondRepository->claimBatch(1, 'native-worker-2', $now);
if (count($secondClaim) !== 1 || (int) $secondClaim[0]['id'] === (int) $firstClaim[0]['id']) {
    throw new RuntimeException('SKIP LOCKED did not give the second worker a distinct job.');
}
$firstConnection->commit();

$firstId = (int) $firstClaim[0]['id'];
$secondId = (int) $secondClaim[0]['id'];
if ($firstRepository->markSent($firstId, 'wrong-worker', 'wrong-provider', $now)) {
    throw new RuntimeException('A foreign worker settled the first job.');
}
if (!$firstRepository->markSent($firstId, 'native-worker-1', 'provider-1', $now)
    || !$secondRepository->markSent($secondId, 'native-worker-2', 'provider-2', $now)) {
    throw new RuntimeException('The owning workers could not settle their jobs.');
}

$summary = $firstConnection->query(
    "SELECT COUNT(*) AS total,
            SUM(status = 'sent') AS sent,
            COUNT(DISTINCT provider_message_id) AS provider_ids,
            SUM(lock_token IS NULL) AS unlocked
     FROM mail_outbox",
)->fetch();
if (!is_array($summary)
    || (int) $summary['total'] !== 2
    || (int) $summary['sent'] !== 2
    || (int) $summary['provider_ids'] !== 2
    || (int) $summary['unlocked'] !== 2) {
    throw new RuntimeException('The outbox jobs were not settled exactly once.');
}

echo "Native MySQL outbox SKIP LOCKED concurrency verification passed.\n";
