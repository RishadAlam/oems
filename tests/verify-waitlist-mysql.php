<?php

declare(strict_types=1);

use OEMS\App\Repositories\WaitlistRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

$dsn = getenv('OEMS_WAITLIST_TEST_DSN');
$user = getenv('OEMS_WAITLIST_TEST_USER');
$password = getenv('OEMS_WAITLIST_TEST_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user)) {
    fwrite(STDERR, "Waitlist native verifier configuration is missing.\n");
    exit(2);
}

$connection = new PDO($dsn, $user, is_string($password) ? $password : '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$connection->beginTransaction();

try {
    $event = $connection->query(
        "SELECT events.id
         FROM events
         INNER JOIN categories ON categories.id = events.category_id AND categories.is_active = 1
         INNER JOIN organizers ON organizers.id = events.organizer_id AND organizers.approval_status = 'approved'
         WHERE events.deleted_at IS NULL
         ORDER BY events.id ASC LIMIT 1",
    )->fetchColumn();
    if ($event === false) {
        throw new RuntimeException('No eligible event fixture exists.');
    }
    $participants = $connection->query(
        "SELECT users.id
         FROM users
         INNER JOIN roles ON roles.id = users.role_id AND roles.slug = 'participant'
         WHERE users.status = 'active' AND users.email_verified_at IS NOT NULL AND users.deleted_at IS NULL
           AND NOT EXISTS (SELECT 1 FROM registrations WHERE registrations.event_id = " . (int) $event . " AND registrations.user_id = users.id)
         ORDER BY users.id ASC LIMIT 2",
    )->fetchAll(PDO::FETCH_COLUMN);
    if (count($participants) !== 2) {
        throw new RuntimeException('Two eligible participant fixtures are required.');
    }

    $connection->exec(
        "UPDATE events SET status = 'published', deleted_at = NULL, waitlist_enabled = 1,
            capacity = GREATEST(capacity, 2), available_seats = 0, ticket_price = 50.00, currency = 'BDT',
            start_date = DATE_ADD(NOW(), INTERVAL 10 DAY), registration_deadline = DATE_ADD(NOW(), INTERVAL 9 DAY)
         WHERE id = " . (int) $event,
    );
    $repository = new WaitlistRepository($connection);
    $first = $repository->join((int) $participants[0], (int) $event, [
        'registration_number' => 'OEMS-NATIVE-WAIT-' . bin2hex(random_bytes(8)),
        'waitlisted_at' => '2026-08-10 08:00:00',
    ]);
    $second = $repository->join((int) $participants[1], (int) $event, [
        'registration_number' => 'OEMS-NATIVE-WAIT-' . bin2hex(random_bytes(8)),
        'waitlisted_at' => '2026-08-10 09:00:00',
    ]);
    if ($first === null || $second === null || $repository->position((int) $second['id']) !== 2) {
        throw new RuntimeException('Native queue ordering failed.');
    }

    $connection->exec("UPDATE events SET available_seats = 1, ticket_price = 77.25 WHERE id = " . (int) $event);
    $promotedAt = new DateTimeImmutable();
    $claim = $repository->claimOldest((int) $event, $promotedAt, $promotedAt->modify('+1 minute'));
    if ($claim === null
        || (int) $claim['id'] !== (int) $first['id']
        || $claim['amount'] !== '77.25'
        || (int) $connection->query('SELECT available_seats FROM events WHERE id = ' . (int) $event)->fetchColumn() !== 0) {
        throw new RuntimeException('Native promotion or seat consumption failed.');
    }

    $released = $repository->releaseExpiredClaim((int) $claim['id'], $promotedAt->modify('+2 minutes'));
    if ($released === null
        || ($released['status'] ?? null) !== 'cancelled'
        || (int) $connection->query('SELECT available_seats FROM events WHERE id = ' . (int) $event)->fetchColumn() !== 1) {
        throw new RuntimeException('Native claim expiry release failed.');
    }

    $connection->rollBack();
    fwrite(STDOUT, "Native MySQL waitlist ordering, promotion, repricing, and expiry verification passed.\n");
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
