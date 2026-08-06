<?php

declare(strict_types=1);

use OEMS\App\Contracts\UserRepositoryInterface;
use OEMS\App\Middleware\AuthMiddleware;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\GuestMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Repositories\DashboardMetricsRepository;
use OEMS\App\Repositories\UserRepository;
use OEMS\App\Services\AuthService;
use OEMS\Core\Auth;
use OEMS\Core\Container;
use OEMS\Core\Config;
use OEMS\Core\Database;
use OEMS\Core\Logger;
use OEMS\Core\RateLimiter;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$environmentFile = $basePath . '/.env';

if (is_file($environmentFile)) {
    $lines = file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $trimmed, 2));

        if (getenv($key) === false) {
            $value = trim($value, "\"'");
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

$appConfig = require $basePath . '/config/app.php';
$databaseConfig = require $basePath . '/config/database.php';
date_default_timezone_set((string) $appConfig['timezone']);

$container = new Container();
$container->instance(Container::class, $container);
$container->instance(Config::class, new Config($appConfig));
$container->instance(View::class, new View($basePath . '/app/Views'));
$container->singleton(Session::class, static fn (): Session => new Session(true, [
    'name' => $appConfig['session_name'],
]));
$container->singleton(Database::class, static fn (): Database => new Database($databaseConfig));
$container->singleton(
    DashboardMetricsRepository::class,
    static fn (Container $container): DashboardMetricsRepository => new DashboardMetricsRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    RateLimiter::class,
    static fn (): RateLimiter => new RateLimiter($basePath . '/storage/cache/rate-limits'),
);
$container->singleton(
    UserRepositoryInterface::class,
    static fn (Container $container): UserRepository => new UserRepository($container->get(Database::class)),
);
$container->singleton(
    Auth::class,
    static fn (Container $container): Auth => new Auth(
        $container->get(Session::class),
        $container->get(UserRepositoryInterface::class),
    ),
);
$container->singleton(
    AuthService::class,
    static fn (Container $container): AuthService => new AuthService(
        $container->get(UserRepositoryInterface::class),
        $container->get(Session::class),
        $container->get(RateLimiter::class),
    ),
);
$container->singleton(
    Security::class,
    static fn (Container $container): Security => new Security($container->get(Session::class)),
);
$container->singleton(Logger::class, static fn (): Logger => new Logger($basePath . '/storage/logs/oems.log'));

$router = new Router($container);
$router->aliasMiddleware('auth', new AuthMiddleware($container->get(Auth::class)));
$router->aliasMiddleware('guest', new GuestMiddleware($container->get(Auth::class)));
$router->aliasMiddleware('role', new RoleMiddleware($container->get(Auth::class)));
$router->aliasMiddleware('csrf', new CsrfMiddleware($container->get(Security::class)));

return [
    'config' => $appConfig,
    'container' => $container,
    'router' => $router,
];
