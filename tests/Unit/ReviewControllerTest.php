<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\AdminReviewController;
use OEMS\App\Controllers\OrganizerReviewController;
use OEMS\App\Controllers\ParticipantReviewController;
use OEMS\App\Middleware\CsrfMiddleware;
use OEMS\App\Middleware\RoleMiddleware;
use OEMS\App\Services\ReviewService;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Container;
use OEMS\Core\Request;
use OEMS\Core\RateLimiter;
use OEMS\Core\Router;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeReviewRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class ReviewControllerTest extends TestCase
{
    private FakeReviewRepository $reviews;

    private FakeUserRepository $users;

    private Session $session;

    private Security $security;

    private string $limitRoot;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new Session(false);
        $this->security = new Security($this->session);
        $this->limitRoot = sys_get_temp_dir() . '/oems-review-controller-limit-' . bin2hex(random_bytes(6));
        $this->users = new FakeUserRepository();
        $this->users->users = [
            7 => $this->user(7, 3, 'Participant Owner'),
            8 => $this->user(8, 2, 'Organizer Owner'),
            9 => $this->user(9, 1, 'Administrator'),
            12 => $this->user(12, 2, 'Other Organizer'),
        ];
        $this->reviews = new FakeReviewRepository();
        $this->reviews->events[41] = [
            'id' => 41,
            'title' => '<script>Completed Event</script>',
            'slug' => 'completed-event',
            'eligible_participants' => [7],
        ];
        $this->reviews->reviews[15] = [
            'id' => 15,
            'event_id' => 41,
            'event_title' => '<script>Completed Event</script>',
            'event_slug' => 'completed-event',
            'user_id' => 7,
            'participant_name' => '<img src=x onerror=alert(1)>',
            'organizer_user_id' => 8,
            'rating' => 5,
            'review' => '<script>Thoughtful participant review</script>',
            'status' => 'published',
            'organizer_reply' => '<b>Existing reply</b>',
            'replied_at' => '2026-08-08 10:00:00',
            'created_at' => '2026-08-07 09:00:00',
            'updated_at' => '2026-08-08 10:00:00',
        ];
        $_SERVER['REQUEST_URI'] = '/participant/reviews';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
        foreach (glob($this->limitRoot . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->limitRoot)) {
            rmdir($this->limitRoot);
        }
    }

    public function testParticipantListAndFormEscapeValuesAndUseAccessibleRatingControls(): void
    {
        $controller = $this->participantController();
        $index = $controller->index(Request::create('GET', '/participant/reviews'));
        $_SERVER['REQUEST_URI'] = '/participant/events/41/review';
        $form = $controller->create($this->idRequest('GET', '/participant/events/41/review', 41));

        $this->assertTrue(str_contains($index->body(), '&lt;script&gt;Completed Event&lt;/script&gt;'));
        $this->assertTrue(str_contains($index->body(), '&lt;script&gt;Thoughtful participant review&lt;/script&gt;'));
        $this->assertFalse(str_contains($index->body(), '<script>Completed Event</script>'));
        $this->assertTrue(str_contains($form->body(), '<fieldset'));
        $this->assertTrue(str_contains($form->body(), '<legend>Rating</legend>'));
        $this->assertTrue(str_contains($form->body(), 'type="radio" name="rating" value="5"'));
        $this->assertTrue(str_contains($form->body(), 'aria-describedby="rating-help"'));
        $this->assertTrue(str_contains($form->body(), 'id="review-help"'));
        $this->assertTrue(str_contains($form->body(), 'class="review-rating-option'));
        $this->assertTrue(str_contains($form->body(), '&lt;script&gt;Thoughtful participant review&lt;/script&gt;'));
        $this->assertFalse(str_contains($form->body(), '<script>Thoughtful participant review</script>'));
        $this->assertSame(1, substr_count($form->body(), 'aria-current="page"'));
    }


    public function testRatingRadioKeyboardFocusHasACompiledVisibleLabelRule(): void
    {
        $stylesheet = file_get_contents(base_path('public/assets/css/app.css'));

        $this->assertTrue(is_string($stylesheet));
        $this->assertTrue(str_contains($stylesheet, '.review-rating-option:has(input[type=radio]:focus-visible)'));
        $this->assertTrue(str_contains($stylesheet, 'outline-color:var(--accent)'));
        $this->assertTrue(str_contains($stylesheet, 'outline-offset:2px'));
    }

    public function testParticipantWorkspaceLinksEligibleEventsForFirstReviewCreation(): void
    {
        $this->reviews->reviews = [];
        $body = $this->participantController()->index(Request::create('GET', '/participant/reviews'))->body();

        $this->assertTrue(str_contains($body, 'Events ready for review'));
        $this->assertTrue(str_contains($body, 'href="/participant/events/41/review"'));
        $this->assertTrue(str_contains($body, '&lt;script&gt;Completed Event&lt;/script&gt;'));
    }

    public function testParticipantSubmissionUsesAuthenticatedOwnerAndReturnsFieldErrorsToForm(): void
    {
        $controller = $this->participantController();
        $invalid = $controller->store($this->idRequest('POST', '/participant/events/41/review', 41, [
            'user_id' => '999',
            'rating' => '0',
            'review' => 'short',
        ]));

        $this->assertSame('/participant/events/41/review', $invalid->header('Location'));
        $formBody = $controller->create($this->idRequest('GET', '/participant/events/41/review', 41))->body();
        $this->assertTrue(str_contains($formBody, 'id="rating-error"'));
        $this->assertTrue(str_contains($formBody, 'aria-describedby="rating-help rating-error"'));
        $this->assertTrue(str_contains($formBody, 'id="review-error"'));
        $this->assertTrue(str_contains($formBody, 'aria-describedby="review-help review-error"'));

        $valid = $controller->store($this->idRequest('POST', '/participant/events/41/review', 41, [
            'user_id' => '999',
            'rating' => '4',
            'review' => '  A revised and valid participant review.  ',
        ]));
        $this->assertSame('/participant/reviews', $valid->header('Location'));
        $this->assertSame(7, $this->reviews->saved[0]['user_id']);
        $this->assertSame('pending', $this->reviews->saved[0]['status']);
    }

    public function testParticipantReviewSubmissionIsRateLimitedPerParticipantEventAndIp(): void
    {
        $directory = sys_get_temp_dir() . '/oems-review-limit-' . bin2hex(random_bytes(6));
        $controller = $this->participantController(new RateLimiter($directory, 1, 900));

        try {
            $first = $controller->store($this->idRequest('POST', '/participant/events/41/review', 41, [
                'rating' => '4',
                'review' => 'A valid first review submission for moderation.',
            ]));
            $limited = $controller->store($this->idRequest('POST', '/participant/events/41/review', 41, [
                'rating' => '5',
                'review' => 'PRIVATE SECOND REVIEW MUST NOT BE PROCESSED',
            ]));

            $this->assertSame(302, $first->status());
            $this->assertSame(429, $limited->status());
            $this->assertTrue(str_contains($limited->body(), 'Too many review attempts'));
            $this->assertFalse(str_contains($limited->body(), 'PRIVATE SECOND REVIEW MUST NOT BE PROCESSED'));
            $this->assertSame(1, count($this->reviews->saved));
        } finally {
            foreach (glob($directory . '/*') ?: [] as $path) {
                unlink($path);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testParticipantFormUses404ForInvalidOrIneligibleEventIds(): void
    {
        $controller = $this->participantController();

        $this->assertSame(404, $controller->create($this->idRequest('GET', '/participant/events/0/review', 0))->status());
        $this->assertSame(404, $controller->create($this->idRequest('GET', '/participant/events/42/review', 42))->status());
        $this->assertSame(404, $controller->store($this->idRequest('POST', '/participant/events/0/review', 0))->status());
    }

    public function testOrganizerListEscapesReviewsAndRepliesAndCrossOrganizerReplyIs404(): void
    {
        $_SERVER['REQUEST_URI'] = '/organizer/reviews';
        $controller = $this->organizerController(8);
        $body = $controller->index(Request::create('GET', '/organizer/reviews'))->body();

        $this->assertTrue(str_contains($body, '&lt;img src=x onerror=alert(1)&gt;'));
        $this->assertTrue(str_contains($body, '&lt;script&gt;Thoughtful participant review&lt;/script&gt;'));
        $this->assertTrue(str_contains($body, '&lt;b&gt;Existing reply&lt;/b&gt;'));
        $this->assertFalse(str_contains($body, '<b>Existing reply</b>'));
        $this->assertTrue(str_contains($body, 'maxlength="1000"'));
        $this->assertTrue(str_contains($body, 'aria-describedby="reply-15-help"'));

        $foreign = $this->organizerController(12)->reply($this->idRequest('POST', '/organizer/reviews/15/reply', 15, [
            'reply' => 'x',
        ]));
        $this->assertSame(404, $foreign->status());

        $saved = $controller->reply($this->idRequest('POST', '/organizer/reviews/15/reply', 15, [
            'reply' => '  Thank you for joining us.  ',
        ]));
        $this->assertSame('/organizer/reviews', $saved->header('Location'));
        $this->assertSame('Thank you for joining us.', $this->reviews->reviews[15]['organizer_reply']);
    }

    public function testOrganizerValidationErrorTargetsAndRepopulatesOnlySubmittedReplyForm(): void
    {
        $_SERVER['REQUEST_URI'] = '/organizer/reviews';
        $this->reviews->reviews[16] = array_merge($this->reviews->reviews[15], [
            'id' => 16,
            'review' => 'A second published review.',
            'organizer_reply' => null,
        ]);
        $controller = $this->organizerController(8);
        $invalid = $controller->reply($this->idRequest('POST', '/organizer/reviews/15/reply', 15, ['reply' => 'x']));
        $body = $controller->index(Request::create('GET', '/organizer/reviews'))->body();

        $this->assertSame('/organizer/reviews', $invalid->header('Location'));
        $this->assertTrue(str_contains($body, 'id="reply-15-error"'));
        $this->assertFalse(str_contains($body, 'id="reply-16-error"'));
        $this->assertTrue(str_contains($body, 'aria-describedby="reply-15-help reply-15-error"'));
        $this->assertTrue(str_contains($body, '>x</textarea>'));
    }

    public function testAdminQueueBoundsStatusEscapesContentAndProvidesCsrfModerationControls(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/reviews';
        $this->reviews->reviews[15]['status'] = 'pending';
        $controller = $this->adminController();
        $body = $controller->index(Request::create('GET', '/admin/reviews', ['status' => 'pending OR 1=1']))->body();

        $this->assertTrue(str_contains($body, '&lt;script&gt;Thoughtful participant review&lt;/script&gt;'));
        $this->assertFalse(str_contains($body, '<script>Thoughtful participant review</script>'));
        $this->assertTrue(str_contains($body, 'action="/admin/reviews/15/publish"'));
        $this->assertTrue(str_contains($body, 'action="/admin/reviews/15/hide"'));
        $this->assertTrue(str_contains($body, 'name="_token"'));
        $this->assertTrue(str_contains($body, '<option value="pending">Pending</option>'));

        $published = $controller->publish($this->idRequest('POST', '/admin/reviews/15/publish', 15));
        $this->assertSame('/admin/reviews', $published->header('Location'));
        $conflict = $controller->hide($this->idRequest('POST', '/admin/reviews/15/hide', 15));
        $this->assertSame(409, $conflict->status());
    }

    public function testReviewWriteRoutesEnforceRoleCsrfMethodAndPositiveIds(): void
    {
        [$guest] = $this->router();
        $this->assertSame('/login', $guest->dispatch(Request::create('POST', '/participant/events/41/review'))->header('Location'));

        [$participant, $participantSecurity] = $this->router(7, 'participant');
        $this->assertSame(419, $participant->dispatch(Request::create('POST', '/participant/events/41/review', input: ['_token' => 'invalid']))->status());
        $this->assertSame(403, $participant->dispatch(Request::create('POST', '/admin/reviews/15/publish', input: ['_token' => $participantSecurity->csrfToken()]))->status());
        $this->assertSame(405, $participant->dispatch(Request::create('GET', '/admin/reviews/15/publish'))->status());

        [$organizer] = $this->router(8, 'organizer');
        $this->assertSame(419, $organizer->dispatch(Request::create('POST', '/organizer/reviews/15/reply', input: ['_token' => 'invalid']))->status());

        [$admin] = $this->router(9, 'super-admin');
        $this->assertSame(419, $admin->dispatch(Request::create('POST', '/admin/reviews/15/hide', input: ['_token' => 'invalid']))->status());
    }

    private function participantController(?RateLimiter $limiter = null): ParticipantReviewController
    {
        $controller = $this->controllerFor(
            ParticipantReviewController::class,
            7,
            $limiter ?? new RateLimiter($this->limitRoot, 1000, 900),
        );
        $this->assertTrue($controller instanceof ParticipantReviewController, 'Participant review controller is missing.');

        return $controller;
    }

    private function organizerController(int $userId): OrganizerReviewController
    {
        $controller = $this->controllerFor(OrganizerReviewController::class, $userId);
        $this->assertTrue($controller instanceof OrganizerReviewController, 'Organizer review controller is missing.');

        return $controller;
    }

    private function adminController(): AdminReviewController
    {
        $controller = $this->controllerFor(AdminReviewController::class, 9);
        $this->assertTrue($controller instanceof AdminReviewController, 'Admin review controller is missing.');

        return $controller;
    }

    private function controllerFor(string $class, int $userId, ?RateLimiter $limiter = null): mixed
    {
        if (!class_exists($class)) {
            return null;
        }

        $session = $userId === 7 ? $this->session : new Session(false);
        $session->put('auth.user_id', $userId);
        $security = $userId === 7 ? $this->security : new Security($session);
        $service = new ReviewService(new PDO('sqlite::memory:'), $this->users, $this->reviews);

        $arguments = [
            new View(base_path('app/Views')),
            $session,
            $security,
            new Auth($session, $this->users),
            new Config(['name' => 'OEMS', 'timezone' => 'Asia/Dhaka']),
            $service,
        ];
        if ($class === ParticipantReviewController::class) {
            if ($limiter === null) {
                throw new \LogicException('Participant review controller tests require a rate limiter.');
            }
            $arguments[] = $limiter;
        }

        return new $class(...$arguments);
    }

    private function idRequest(string $method, string $uri, int $id, array $input = []): Request
    {
        return Request::create($method, $uri, input: $input)->withRouteParameters(['id' => (string) $id]);
    }

    private function router(?int $userId = null, string $role = 'participant'): array
    {
        $_SESSION = [];
        $session = new Session(false);
        $users = new FakeUserRepository();
        if ($userId !== null) {
            $roleId = ['super-admin' => 1, 'organizer' => 2, 'participant' => 3][$role];
            $users->users[$userId] = $this->user($userId, $roleId, 'Route User');
            $session->put('auth.user_id', $userId);
        }
        $auth = new Auth($session, $users);
        $security = new Security($session);
        $router = new Router(new Container());
        $router->aliasMiddleware('role', new RoleMiddleware($auth));
        $router->aliasMiddleware('csrf', new CsrfMiddleware($security));
        $registerRoutes = require base_path('routes/web.php');
        $registerRoutes($router);

        return [$router, $security];
    }

    private function user(int $id, int $roleId, string $name): array
    {
        return [
            'id' => $id,
            'role_id' => $roleId,
            'name' => $name,
            'email' => 'user-' . $id . '@example.test',
            'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ];
    }
}
