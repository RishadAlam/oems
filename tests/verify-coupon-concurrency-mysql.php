<?php

declare(strict_types=1);

use OEMS\App\Repositories\CouponRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

function couponConnection(): PDO
{
    return new PDO(
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
}

$connection = couponConnection();
$registration = $connection->query(
    "SELECT registrations.id, registrations.user_id, registrations.event_id, events.organizer_id
     FROM registrations
     INNER JOIN users ON users.id = registrations.user_id
     INNER JOIN events ON events.id = registrations.event_id
     WHERE users.deleted_at IS NULL AND events.deleted_at IS NULL
     ORDER BY registrations.id LIMIT 1",
)->fetch();
if (!is_array($registration)) {
    throw new RuntimeException('The disposable demo requires one registration for coupon verification.');
}

$connection->prepare(
    "INSERT INTO coupons
        (event_id, organizer_id, code, discount_type, discount_value, usage_limit, used_count, starts_at, expires_at, is_active)
     VALUES (:event_id, :organizer_id, 'NATIVE-FINAL-USE', 'fixed', 1.00, 1, 0, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 1 DAY, 1)",
)->execute([
    'event_id' => (int) $registration['event_id'],
    'organizer_id' => (int) $registration['organizer_id'],
]);
$couponId = (int) $connection->lastInsertId();
$participantId = (int) $registration['user_id'];
$eventId = (int) $registration['event_id'];
$registrationId = (int) $registration['id'];
$now = new DateTimeImmutable();
$connection = null;

$sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
if (!is_array($sockets)) {
    throw new RuntimeException('A synchronization channel could not be created.');
}
$child = pcntl_fork();
if ($child === -1) {
    throw new RuntimeException('The coupon concurrency child could not be created.');
}
if ($child === 0) {
    fclose($sockets[0]);
    $childConnection = couponConnection();
    $childRepository = new CouponRepository($childConnection);
    stream_get_contents($sockets[1]);
    fclose($sockets[1]);
    $childConnection->beginTransaction();
    $quote = $childRepository->findRedeemable($participantId, $eventId, 'NATIVE-FINAL-USE', new DateTimeImmutable(), true);
    $won = is_array($quote) && $childRepository->consume($couponId, $participantId, $registrationId, '1.00', new DateTimeImmutable());
    if ($childConnection->inTransaction()) {
        $childConnection->commit();
    }
    exit($won ? 1 : 0);
}

$connection = couponConnection();
$repository = new CouponRepository($connection);
$connection->beginTransaction();
$locked = $repository->findRedeemable($participantId, $eventId, 'NATIVE-FINAL-USE', $now, true);
if (!is_array($locked) || (int) $locked['id'] !== $couponId) {
    throw new RuntimeException('The final coupon use could not be locked.');
}

fclose($sockets[1]);
fwrite($sockets[0], 'locked');
fclose($sockets[0]);
usleep(50000);
if (!$repository->consume($couponId, $participantId, $registrationId, '1.00', $now)) {
    throw new RuntimeException('The locked coupon winner could not consume the final use.');
}
$connection->commit();
pcntl_waitpid($child, $status);
if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
    throw new RuntimeException('A concurrent coupon consumer incorrectly won.');
}

$row = $connection->query("SELECT used_count, (SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = {$couponId}) AS uses FROM coupons WHERE id = {$couponId}")->fetch();
if (!is_array($row) || (int) $row['used_count'] !== 1 || (int) $row['uses'] !== 1) {
    throw new RuntimeException('The final coupon use was not settled exactly once.');
}

echo "Native MySQL coupon final-use concurrency verification passed.\n";
