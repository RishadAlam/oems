<?php

declare(strict_types=1);

use OEMS\App\Repositories\BlogRepository;
use OEMS\App\Services\BlogService;
use OEMS\App\Services\ImageUploadService;

require dirname(__DIR__) . '/vendor/autoload.php';

$dsn = getenv('OEMS_BLOG_TEST_DSN');
$user = getenv('OEMS_BLOG_TEST_USER');
$password = getenv('OEMS_BLOG_TEST_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user) || !function_exists('pcntl_fork')) {
    fwrite(STDERR, "Blog native verifier configuration or process support is missing.\n");
    exit(2);
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$connection = new PDO($dsn, $user, is_string($password) ? $password : '', $options);
$authorId = (int) $connection->query(
    "SELECT users.id FROM users INNER JOIN roles ON roles.id = users.role_id
     WHERE roles.slug = 'super-admin' AND users.status = 'active' AND users.deleted_at IS NULL
     ORDER BY users.id ASC LIMIT 1",
)->fetchColumn();
if ($authorId <= 0) {
    fwrite(STDERR, "An active super administrator fixture is required.\n");
    exit(1);
}

$slug = 'native-blog-' . bin2hex(random_bytes(8));
$root = sys_get_temp_dir() . '/oems-blog-native-' . bin2hex(random_bytes(6));
mkdir($root, 0775, true);
$service = new BlogService(new BlogRepository($connection), new ImageUploadService($root, '/uploads/blog', requireHttpUpload: false));
$created = $service->create($authorId, [
    'title' => 'Native publication concurrency proof',
    'slug' => $slug,
    'excerpt' => 'A native MySQL publication proof for the Week 4 editorial workflow.',
    'body' => "The first paragraph verifies safe draft persistence.\n\nThe second verifies concurrent publication convergence.",
    'category' => 'Engineering',
    'meta_title' => 'Native publication proof',
    'meta_description' => 'A native MySQL proof for safe OEMS Blog publication.',
], null);
if (!($created['success'] ?? false)) {
    fwrite(STDERR, "The native Blog draft could not be created.\n");
    exit(1);
}
$postId = (int) $created['post']['id'];
$expected = (string) $created['post']['updated_at'];
$connection = null;
$resultFiles = [$root . '/result-0.json', $root . '/result-1.json'];
$children = [];

for ($index = 0; $index < 2; $index++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        fwrite(STDERR, "The Blog verifier could not fork.\n");
        exit(1);
    }
    if ($pid === 0) {
        try {
            $child = new PDO($dsn, $user, is_string($password) ? $password : '', $options);
            $childService = new BlogService(new BlogRepository($child), new ImageUploadService($root, '/uploads/blog', requireHttpUpload: false));
            $result = $childService->transition($postId, 'published', $expected);
            file_put_contents($resultFiles[$index], json_encode(['success' => $result['success'] ?? false], JSON_THROW_ON_ERROR));
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
    $connection = new PDO($dsn, $user, is_string($password) ? $password : '', $options);
    $repository = new BlogRepository($connection);
    $service = new BlogService($repository, new ImageUploadService($root, '/uploads/blog', requireHttpUpload: false));
    $post = $repository->findAdmin($postId);
    $public = $service->publicDetail($slug);
    if ($failed || !is_array($post) || (string) $post['status'] !== 'published' || !is_array($public)
        || array_key_exists('id', $public) || array_key_exists('author_id', $public) || array_key_exists('deleted_at', $public)) {
        throw new RuntimeException('Concurrent native publication did not converge on one private-safe public post.');
    }
    $unpublished = $service->transition($postId, 'draft', (string) $post['updated_at']);
    if (!($unpublished['success'] ?? false) || $service->publicDetail($slug) !== null) {
        throw new RuntimeException('Native unpublication did not immediately hide the post.');
    }
    $deleted = $service->delete($postId, (string) $unpublished['post']['updated_at']);
    if (!($deleted['success'] ?? false) || $repository->findAdmin($postId) !== null) {
        throw new RuntimeException('Native soft deletion did not hide the post from administration.');
    }
    fwrite(STDOUT, "Native MySQL Blog publication convergence, privacy, unpublication, and soft deletion passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    foreach ($resultFiles as $path) {
        @unlink($path);
    }
    @rmdir($root);
}

exit($failed ? 1 : 0);
