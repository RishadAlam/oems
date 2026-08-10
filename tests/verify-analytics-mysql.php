<?php

declare(strict_types=1);

use OEMS\App\Repositories\AnalyticsRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3306);
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$connection = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);
$repository = new AnalyticsRepository($connection);
$organizerUserId = (int) $connection->query('SELECT user_id FROM organizers ORDER BY id LIMIT 1')->fetchColumn();
$eventId = (int) $connection->query('SELECT id FROM events ORDER BY id LIMIT 1')->fetchColumn();
$startAt = '2000-01-01 00:00:00';
$endExclusive = '2100-01-01 00:00:00';

$organizerSummary = $repository->organizerSummary($organizerUserId, $startAt, $endExclusive, $eventId);
$organizerRows = $repository->organizerEventRows($organizerUserId, $startAt, $endExclusive, $eventId, 100, 0);
$adminSummary = $repository->adminSummary($startAt, $endExclusive, ['event_status' => 'published', 'currency' => 'BDT']);

if ($organizerSummary === null || $organizerRows === null || !isset($adminSummary['verified_payments'])) {
    throw new RuntimeException('Native MySQL analytics scope verification failed.');
}

foreach (['events', 'registrations', 'payments', 'attendance', 'organizers'] as $type) {
    $repository->adminReportRows($type, $startAt, $endExclusive, [], 100, 0);
}

foreach ($organizerSummary['verified_payments'] as $amount) {
    if (!is_string($amount) || preg_match('/\A\d+\.\d{2}\z/D', $amount) !== 1) {
        throw new RuntimeException('Native MySQL money formatting verification failed.');
    }
}

echo "Native MySQL analytics and report verification passed.\n";
