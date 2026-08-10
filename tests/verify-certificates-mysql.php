<?php

declare(strict_types=1);

use OEMS\App\Repositories\CertificateRepository;
use OEMS\App\Services\CertificateArtifactService;
use OEMS\App\Services\CertificateService;

require dirname(__DIR__) . '/vendor/autoload.php';

$dsn = getenv('OEMS_CERTIFICATE_TEST_DSN');
$user = getenv('OEMS_CERTIFICATE_TEST_USER');
$password = getenv('OEMS_CERTIFICATE_TEST_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user) || !function_exists('pcntl_fork')) {
    fwrite(STDERR, "Certificate native verifier configuration or process support is missing.\n");
    exit(2);
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$connection = new PDO($dsn, $user, is_string($password) ? $password : '', $options);
$eligible = $connection->query(
    "SELECT registrations.id AS registration_id, registrations.user_id AS participant_id
     FROM registrations
     INNER JOIN users ON users.id = registrations.user_id
     INNER JOIN events ON events.id = registrations.event_id
     INNER JOIN tickets ON tickets.registration_id = registrations.id
     INNER JOIN attendance ON attendance.registration_id = registrations.id AND attendance.ticket_id = tickets.id
     WHERE registrations.status = 'confirmed' AND users.status = 'active'
       AND users.email_verified_at IS NOT NULL AND users.deleted_at IS NULL
       AND events.status = 'completed' AND events.deleted_at IS NULL
       AND tickets.status = 'used' AND attendance.status = 'present'
       AND NOT EXISTS (SELECT 1 FROM event_certificates WHERE event_certificates.registration_id = registrations.id)
     ORDER BY registrations.id ASC LIMIT 1",
)->fetch();
if (!is_array($eligible)) {
    fwrite(STDERR, "An eligible completed attendance fixture is required.\n");
    exit(1);
}
$registrationId = (int) $eligible['registration_id'];
$participantId = (int) $eligible['participant_id'];
$connection = null;
$root = sys_get_temp_dir() . '/oems-certificate-native-' . bin2hex(random_bytes(6));
$resultFiles = [$root . '-result-0.json', $root . '-result-1.json'];
$children = [];

for ($index = 0; $index < 2; $index++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        fwrite(STDERR, "The certificate verifier could not fork.\n");
        exit(1);
    }
    if ($pid === 0) {
        try {
            $childConnection = new PDO($dsn, $user, is_string($password) ? $password : '', $options);
            $service = new CertificateService(
                $childConnection,
                new CertificateRepository($childConnection),
                new CertificateArtifactService($root, 'certificates', 'https://events.example.test/certificates/verify'),
            );
            $result = $service->issue($participantId, $registrationId);
            file_put_contents($resultFiles[$index], json_encode([
                'success' => $result['success'] ?? false,
                'created' => $result['created'] ?? false,
                'token' => $result['verification_token'] ?? null,
            ], JSON_THROW_ON_ERROR));
            exit(($result['success'] ?? false) ? 0 : 1);
        } catch (Throwable $exception) {
            file_put_contents($resultFiles[$index], json_encode(['error' => $exception::class], JSON_THROW_ON_ERROR));
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

try {
    $results = array_map(static fn (string $path): array => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR), $resultFiles);
    $createdCount = count(array_filter($results, static fn (array $result): bool => ($result['created'] ?? false) === true));
    $connection = new PDO($dsn, $user, is_string($password) ? $password : '', $options);
    $statement = $connection->prepare('SELECT id, pdf_path FROM event_certificates WHERE registration_id = :registration_id');
    $statement->execute(['registration_id' => $registrationId]);
    $certificate = $statement->fetch();
    $token = null;
    foreach ($results as $result) {
        if (is_string($result['token'] ?? null)) {
            $token = $result['token'];
        }
    }
    $service = new CertificateService(
        $connection,
        new CertificateRepository($connection),
        new CertificateArtifactService($root, 'certificates', 'https://events.example.test/certificates/verify'),
    );
    if ($failed || $createdCount !== 1 || !is_array($certificate) || count(glob($root . '/*.pdf') ?: []) !== 1
        || !is_string($token) || $service->verify($token) === null) {
        throw new RuntimeException('Concurrent certificate issuance did not converge on one verifiable record and artifact.');
    }
    $connection->prepare('DELETE FROM event_certificates WHERE registration_id = :registration_id')->execute(['registration_id' => $registrationId]);
    (new CertificateArtifactService($root))->delete((string) $certificate['pdf_path']);
    fwrite(STDOUT, "Native MySQL certificate locking, idempotency, privacy, and artifact verification passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    foreach ($resultFiles as $path) {
        @unlink($path);
    }
    if (is_dir($root)) {
        foreach (scandir($root) ?: [] as $entry) {
            if (!in_array($entry, ['.', '..'], true)) {
                @unlink($root . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($root);
    }
}

exit($failed ? 1 : 0);
