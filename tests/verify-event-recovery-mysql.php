<?php

declare(strict_types=1);

use OEMS\App\Repositories\EventRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$dsn = getenv('OEMS_EVENT_RECOVERY_TEST_DSN');
$user = getenv('OEMS_EVENT_RECOVERY_TEST_USER');
$password = getenv('OEMS_EVENT_RECOVERY_TEST_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user) || !function_exists('pcntl_fork')) {
    fwrite(STDERR, "Event recovery native verifier configuration or process support is missing.\n");
    exit(2);
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$connection = new PDO($dsn, $user, is_string($password) ? $password : '', $options);
$source = $connection->query(
    "SELECT events.*, organizers.user_id AS organizer_user_id
     FROM events INNER JOIN organizers ON organizers.id = events.organizer_id
     WHERE organizers.user_id IS NOT NULL ORDER BY events.id LIMIT 1",
)->fetch();
if (!is_array($source)) {
    fwrite(STDERR, "An event fixture is required for native recovery verification.\n");
    exit(1);
}

$slug = 'native-recovery-' . bin2hex(random_bytes(8));
$insert = $connection->prepare(
    "INSERT INTO events
        (organizer_id, category_id, venue_id, title, slug, description, start_date, end_date,
         registration_deadline, capacity, available_seats, ticket_price, currency, status, waitlist_enabled,
         created_at, updated_at, deleted_at)
     VALUES
        (:organizer_id, :category_id, NULL, 'Native recovery proof', :slug, 'Native MySQL recovery fixture.',
         '2027-01-10 10:00:00', '2027-01-10 12:00:00', '2027-01-09 10:00:00', 10, 10, 0.00,
         'BDT', 'draft', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
);
$insert->execute(['organizer_id' => $source['organizer_id'], 'category_id' => $source['category_id'], 'slug' => $slug]);
$eventId = (int) $connection->lastInsertId();
$deletedAt = (string) $connection->query("SELECT deleted_at FROM events WHERE id = $eventId")->fetchColumn();
$ownerId = (int) $source['organizer_user_id'];
$resultRoot = sys_get_temp_dir() . '/oems-event-recovery-' . bin2hex(random_bytes(6));
mkdir($resultRoot, 0775, true);
$children = [];

for ($index = 0; $index < 2; $index++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        fwrite(STDERR, "The recovery verifier could not fork.\n");
        exit(1);
    }
    if ($pid === 0) {
        try {
            $child = new PDO($dsn, $user, is_string($password) ? $password : '', $options);
            $restored = (new EventRepository($child))->restoreOwned(
                $ownerId,
                $eventId,
                $deletedAt,
                ['ip_address' => '127.0.0.1', 'user_agent' => 'native-recovery-verifier'],
            );
            file_put_contents($resultRoot . '/' . $index, $restored ? '1' : '0');
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultRoot . '/' . $index, 'error:' . $exception::class);
            exit(1);
        }
    }
    $children[] = $pid;
}

$failed = false;
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
    $failed = $failed || !pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0;
}
$results = [];
for ($index = 0; $index < 2; $index++) {
    $results[] = trim((string) @file_get_contents($resultRoot . '/' . $index));
    @unlink($resultRoot . '/' . $index);
}
@rmdir($resultRoot);

$connection = new PDO($dsn, $user, is_string($password) ? $password : '', $options);
$event = $connection->query("SELECT status, deleted_at FROM events WHERE id = $eventId")->fetch();
$audit = (int) $connection->query(
    "SELECT COUNT(*) FROM activity_logs WHERE subject_type = 'event' AND subject_id = $eventId AND action = 'event.restored'",
)->fetchColumn();
if ($failed || count(array_filter($results, static fn (string $value): bool => $value === '1')) !== 1
    || !is_array($event) || $event['status'] !== 'draft' || $event['deleted_at'] !== null || $audit !== 1) {
    fwrite(STDERR, 'Native recovery CAS/audit verification failed: ' . json_encode($results) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Native MySQL event recovery CAS, lifecycle preservation, and single audit passed.\n");
