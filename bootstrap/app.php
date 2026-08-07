<?php

declare(strict_types=1);

use OEMS\App\Contracts\EmailLogRepositoryInterface;
use OEMS\App\Contracts\CategoryRepositoryInterface;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\MailTransportInterface;
use OEMS\App\Contracts\OrganizerRepositoryInterface;
use OEMS\App\Contracts\ProfileRepositoryInterface;
use OEMS\App\Contracts\UserRepositoryInterface;
use OEMS\App\Contracts\VenueRepositoryInterface;
use OEMS\App\Middleware\AuthMiddleware;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\GuestMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Repositories\DashboardMetricsRepository;
use OEMS\App\Repositories\CategoryRepository;
use OEMS\App\Repositories\EmailLogRepository;
use OEMS\App\Repositories\EventRepository;
use OEMS\App\Repositories\OrganizerRepository;
use OEMS\App\Repositories\UserRepository;
use OEMS\App\Repositories\ProfileRepository;
use OEMS\App\Repositories\VenueRepository;
use OEMS\App\Mail\PhpMailerTransport;
use OEMS\App\Services\AccountMailer;
use OEMS\App\Services\AuthService;
use OEMS\App\Services\CategoryService;
use OEMS\App\Services\EventService;
use OEMS\App\Services\ImageUploadService;
use OEMS\App\Services\VenueService;
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
    ProfileRepositoryInterface::class,
    static fn (Container $container): ProfileRepository => new ProfileRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    EmailLogRepositoryInterface::class,
    static fn (Container $container): EmailLogRepository => new EmailLogRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    CategoryRepositoryInterface::class,
    static fn (Container $container): CategoryRepository => new CategoryRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    VenueRepositoryInterface::class,
    static fn (Container $container): VenueRepository => new VenueRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    EventRepositoryInterface::class,
    static fn (Container $container): EventRepository => new EventRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    OrganizerRepositoryInterface::class,
    static fn (Container $container): OrganizerRepository => new OrganizerRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    ImageUploadService::class,
    static fn (): ImageUploadService => new ImageUploadService($basePath . '/public/uploads/events'),
);
$container->singleton(
    CategoryService::class,
    static fn (Container $container): CategoryService => new CategoryService(
        $container->get(CategoryRepositoryInterface::class),
    ),
);
$container->singleton(
    EventService::class,
    static fn (Container $container): EventService => new EventService(
        $container->get(EventRepositoryInterface::class),
        $container->get(CategoryRepositoryInterface::class),
        $container->get(VenueRepositoryInterface::class),
        $container->get(ImageUploadService::class),
        $container->get(OrganizerRepositoryInterface::class),
    ),
);
$container->singleton(
    VenueService::class,
    static fn (Container $container): VenueService => new VenueService(
        $container->get(VenueRepositoryInterface::class),
    ),
);
$container->singleton(
    MailTransportInterface::class,
    static fn (Container $container): PhpMailerTransport => new PhpMailerTransport(
        $container->get(Config::class),
    ),
);
$container->singleton(
    AccountMailer::class,
    static fn (Container $container): AccountMailer => new AccountMailer(
        $container->get(MailTransportInterface::class),
        $container->get(EmailLogRepositoryInterface::class),
        $container->get(Config::class),
        $container->get(Logger::class),
    ),
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
