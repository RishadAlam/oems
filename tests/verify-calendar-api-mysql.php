<?php

declare(strict_types=1);

use OEMS\App\Repositories\EventRepository;
use OEMS\App\Services\PublicEventApiService;

require dirname(__DIR__) . '/vendor/autoload.php';

$dsn = getenv('OEMS_PUBLIC_API_TEST_DSN');
$user = getenv('OEMS_PUBLIC_API_TEST_USER');
$password = getenv('OEMS_PUBLIC_API_TEST_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user)) {
    fwrite(STDERR, "Public calendar/API native verifier configuration is missing.\n");
    exit(2);
}

$connection = new PDO($dsn, $user, is_string($password) ? $password : '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$connection->beginTransaction();

try {
    $repository = new EventRepository($connection);
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Dhaka'));
    $from = $now->modify('-12 months');
    $to = $now->modify('+12 months');
    $rows = $repository->publicRange($from, $to, ['search' => 'event', 'sort' => 'soonest'], 100, 0);
    $count = $repository->countPublicRange($from, $to, ['search' => 'event', 'sort' => 'soonest']);
    if ($count < count($rows)) {
        throw new RuntimeException('Native range count is smaller than its page.');
    }

    $target = $connection->query(
        "SELECT events.id, events.slug, organizers.user_id
         FROM events
         INNER JOIN organizers ON organizers.id = events.organizer_id
         INNER JOIN users ON users.id = organizers.user_id
         INNER JOIN categories ON categories.id = events.category_id
         WHERE events.status IN ('published', 'completed')
           AND events.deleted_at IS NULL AND categories.is_active = 1
           AND organizers.approval_status = 'approved'
           AND users.status = 'active' AND users.email_verified_at IS NOT NULL AND users.deleted_at IS NULL
         ORDER BY events.id ASC LIMIT 1",
    )->fetch();
    if (!is_array($target)) {
        throw new RuntimeException('An eligible native public event fixture is required.');
    }

    $connection->exec("UPDATE events SET start_date = DATE_ADD(NOW(), INTERVAL 10 DAY), end_date = DATE_ADD(DATE_ADD(NOW(), INTERVAL 10 DAY), INTERVAL 2 HOUR), registration_deadline = DATE_ADD(NOW(), INTERVAL 9 DAY) WHERE id = " . (int) $target['id']);
    $visible = array_column($repository->publicRange($now, $now->modify('+30 days'), [], 100, 0), 'slug');
    if (!in_array((string) $target['slug'], $visible, true)) {
        throw new RuntimeException('Eligible event is absent from the native public range.');
    }

    $suspend = $connection->prepare("UPDATE users SET status = 'suspended' WHERE id = :organizer_user_id");
    $suspend->execute(['organizer_user_id' => (int) $target['user_id']]);
    $hidden = array_column($repository->publicRange($now, $now->modify('+30 days'), [], 100, 0), 'slug');
    if (in_array((string) $target['slug'], $hidden, true)
        || $repository->findPublishedBySlug((string) $target['slug']) !== null) {
        throw new RuntimeException('Suspended organizer event leaked through a native public query.');
    }
    $connection->prepare("UPDATE users SET status = 'active' WHERE id = :organizer_user_id")
        ->execute(['organizer_user_id' => (int) $target['user_id']]);

    $restricted = $connection->query(
        "SELECT events.slug FROM events
         INNER JOIN organizers ON organizers.id = events.organizer_id
         INNER JOIN users ON users.id = organizers.user_id
         INNER JOIN categories ON categories.id = events.category_id
         WHERE events.status IN ('published', 'completed') AND events.deleted_at IS NULL
           AND events.location_visibility = 'registered' AND categories.is_active = 1
           AND organizers.approval_status = 'approved' AND users.status = 'active'
           AND users.email_verified_at IS NOT NULL AND users.deleted_at IS NULL
         ORDER BY events.id ASC LIMIT 1",
    )->fetchColumn();
    if ($restricted !== false) {
        $service = new PublicEventApiService($repository, 'Asia/Dhaka', 'https://events.example.test', $now);
        $detail = $service->detail((string) $restricted);
        $location = $detail['event']['location'] ?? [];
        if (!($detail['success'] ?? false)
            || !is_array($location)
            || array_key_exists('venue', $location)
            || array_key_exists('address', $location)
            || array_key_exists('latitude', $location)
            || array_key_exists('longitude', $location)) {
            throw new RuntimeException('Restricted exact location leaked through native API presentation.');
        }
    }

    $connection->rollBack();
    fwrite(STDOUT, "Native MySQL public calendar/API lifecycle, privacy, and native-prepare verification passed.\n");
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
