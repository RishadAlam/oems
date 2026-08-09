<?php

declare(strict_types=1);

use OEMS\App\Contracts\EmailLogRepositoryInterface;
use OEMS\App\Contracts\FavoriteRepositoryInterface;
use OEMS\App\Contracts\GeocoderInterface;
use OEMS\App\Contracts\GeocodingCacheRepositoryInterface;
use OEMS\App\Contracts\CategoryRepositoryInterface;
use OEMS\App\Contracts\EventRepositoryInterface;
use OEMS\App\Contracts\HttpClientInterface;
use OEMS\App\Contracts\MailTransportInterface;
use OEMS\App\Contracts\OrganizerRepositoryInterface;
use OEMS\App\Contracts\NotificationRepositoryInterface;
use OEMS\App\Contracts\PaymentRepositoryInterface;
use OEMS\App\Contracts\ProfileRepositoryInterface;
use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\App\Contracts\ReviewRepositoryInterface;
use OEMS\App\Contracts\TicketRepositoryInterface;
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
use OEMS\App\Repositories\FavoriteRepository;
use OEMS\App\Repositories\GeocodingCacheRepository;
use OEMS\App\Repositories\OrganizerRepository;
use OEMS\App\Repositories\NotificationRepository;
use OEMS\App\Repositories\PaymentRepository;
use OEMS\App\Repositories\RegistrationRepository;
use OEMS\App\Repositories\ReviewRepository;
use OEMS\App\Repositories\TicketRepository;
use OEMS\App\Repositories\UserRepository;
use OEMS\App\Repositories\ProfileRepository;
use OEMS\App\Repositories\VenueRepository;
use OEMS\App\Mail\PhpMailerTransport;
use OEMS\App\Services\AccountMailer;
use OEMS\App\Services\AuthService;
use OEMS\App\Services\CategoryService;
use OEMS\App\Services\DashboardLayoutDataProvider;
use OEMS\App\Services\EventService;
use OEMS\App\Services\FavoriteService;
use OEMS\App\Services\ImageUploadService;
use OEMS\App\Services\LocationService;
use OEMS\App\Services\NotificationService;
use OEMS\App\Services\NominatimGeocoder;
use OEMS\App\Services\RegistrationService;
use OEMS\App\Services\ReviewService;
use OEMS\App\Services\TicketArtifactService;
use OEMS\App\Services\TicketService;
use OEMS\App\Services\TransactionMailer;
use OEMS\App\Services\VenueService;
use OEMS\App\Services\VenueGeocodingService;
use OEMS\App\Support\StreamHttpClient;
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
$container->instance(
    View::class,
    new View(
        $basePath . '/app/Views',
        static function (array $data, string $layout) use ($container): array {
            return $container->get(DashboardLayoutDataProvider::class)->forLayout($data, $layout);
        },
    ),
);
$container->singleton(Session::class, static fn (): Session => new Session(true, [
    'name' => $appConfig['session_name'],
    'secure' => $appConfig['secure_cookies'],
]));
$container->singleton(Database::class, static fn (): Database => new Database($databaseConfig));
$container->singleton(
    HttpClientInterface::class,
    static fn (Container $container): StreamHttpClient => new StreamHttpClient(
        (string) $container->get(Config::class)->get('map.user_agent', 'OEMS/1.0'),
    ),
);
$container->singleton(
    GeocoderInterface::class,
    static fn (Container $container): NominatimGeocoder => new NominatimGeocoder(
        $container->get(HttpClientInterface::class),
        (string) $container->get(Config::class)->get('map.geocoder_url', 'https://nominatim.openstreetmap.org/search'),
        (string) $container->get(Config::class)->get('map.user_agent', 'OEMS/1.0'),
        (string) $container->get(Config::class)->get('map.contact_email', ''),
    ),
);
$container->singleton(
    GeocodingCacheRepositoryInterface::class,
    static fn (Container $container): GeocodingCacheRepository => new GeocodingCacheRepository(
        $container->get(Database::class)->connection(),
        $container->get(Logger::class),
    ),
);
$container->singleton(
    VenueGeocodingService::class,
    static fn (Container $container): VenueGeocodingService => new VenueGeocodingService(
        $container->get(GeocodingCacheRepositoryInterface::class),
        $container->get(GeocoderInterface::class),
        (string) $container->get(Config::class)->get('map.provider_name', 'OpenStreetMap Nominatim'),
        $container->get(Logger::class),
    ),
);
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
    LocationService::class,
    static fn (Container $container): LocationService => new LocationService(
        (int) $container->get(Config::class)->get('map.location_session_ttl', 1209600),
        null,
        (array) $container->get(Config::class)->get('map.directions_hosts', []),
    ),
);
$container->singleton(
    FavoriteRepositoryInterface::class,
    static fn (Container $container): FavoriteRepository => new FavoriteRepository(
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
    NotificationRepositoryInterface::class,
    static fn (Container $container): NotificationRepository => new NotificationRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    DashboardLayoutDataProvider::class,
    static fn (Container $container): DashboardLayoutDataProvider => new DashboardLayoutDataProvider(
        $container->get(NotificationRepositoryInterface::class),
    ),
);
$container->singleton(
    NotificationService::class,
    static fn (Container $container): NotificationService => new NotificationService(
        $container->get(NotificationRepositoryInterface::class),
        $container->get(Logger::class),
    ),
);
$container->singleton(
    RegistrationRepositoryInterface::class,
    static fn (Container $container): RegistrationRepository => new RegistrationRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    PaymentRepositoryInterface::class,
    static fn (Container $container): PaymentRepository => new PaymentRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    TicketRepositoryInterface::class,
    static fn (Container $container): TicketRepository => new TicketRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->singleton(
    ReviewRepositoryInterface::class,
    static fn (Container $container): ReviewRepository => new ReviewRepository(
        $container->get(Database::class)->connection(),
        static fn (): DateTimeImmutable => new DateTimeImmutable(
            'now',
            new DateTimeZone((string) $container->get(Config::class)->get('timezone', 'Asia/Dhaka')),
        ),
    ),
);
$container->singleton(
    ImageUploadService::class,
    static fn (): ImageUploadService => new ImageUploadService($basePath . '/public/uploads/events'),
);
$container->singleton(
    TicketArtifactService::class,
    static fn (Container $container): TicketArtifactService => new TicketArtifactService(
        $basePath . '/public/uploads/tickets',
        'uploads/tickets',
        (string) $container->get(Config::class)->get('url', 'http://localhost:8000') . '/organizer/check-in',
    ),
);
$container->singleton(
    TicketService::class,
    static fn (Container $container): TicketService => new TicketService(
        $container->get(Database::class)->connection(),
        $container->get(TicketRepositoryInterface::class),
        $container->get(TicketArtifactService::class),
        (string) $container->get(Config::class)->get('url', 'http://localhost:8000') . '/organizer/check-in',
        $container->get(Logger::class),
    ),
);
$container->singleton(
    CategoryService::class,
    static fn (Container $container): CategoryService => new CategoryService(
        $container->get(CategoryRepositoryInterface::class),
        $container->get(Logger::class),
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
        $container->get(Logger::class),
        $container->get(NotificationService::class),
        $container->get(LocationService::class),
    ),
);
$container->singleton(
    VenueService::class,
    static fn (Container $container): VenueService => new VenueService(
        $container->get(VenueRepositoryInterface::class),
        $container->get(Logger::class),
        $container->get(LocationService::class),
    ),
);
$container->singleton(
    FavoriteService::class,
    static fn (Container $container): FavoriteService => new FavoriteService(
        $container->get(FavoriteRepositoryInterface::class),
        $container->get(UserRepositoryInterface::class),
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
    TransactionMailer::class,
    static fn (Container $container): TransactionMailer => new TransactionMailer(
        $container->get(MailTransportInterface::class),
        $container->get(EmailLogRepositoryInterface::class),
        $container->get(Config::class),
        $container->get(Logger::class),
    ),
);
$container->singleton(
    RegistrationService::class,
    static fn (Container $container): RegistrationService => new RegistrationService(
        $container->get(Database::class)->connection(),
        $container->get(UserRepositoryInterface::class),
        $container->get(RegistrationRepositoryInterface::class),
        $container->get(PaymentRepositoryInterface::class),
        $container->get(TicketService::class),
        $container->get(TransactionMailer::class),
        $container->get(Logger::class),
        $container->get(NotificationService::class),
    ),
);
$container->singleton(
    ReviewService::class,
    static fn (Container $container): ReviewService => new ReviewService(
        $container->get(Database::class)->connection(),
        $container->get(UserRepositoryInterface::class),
        $container->get(ReviewRepositoryInterface::class),
        $container->get(Logger::class),
        $container->get(NotificationService::class),
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
