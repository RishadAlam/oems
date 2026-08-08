<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\ReviewService;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeReviewRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class ReviewServiceTest extends TestCase
{
    private PDO $connection;

    private FakeReviewRepository $reviews;

    private FakeUserRepository $users;

    private mixed $service = null;

    private string $logPath;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->reviews = new FakeReviewRepository();
        $this->users = new FakeUserRepository();
        $this->users->users = [
            7 => $this->user(7, 3, 'Participant'),
            8 => $this->user(8, 2, 'Organizer'),
            9 => $this->user(9, 1, 'Administrator'),
            10 => array_merge($this->user(10, 3, 'Unverified'), ['email_verified_at' => null]),
            11 => array_merge($this->user(11, 3, 'Suspended'), ['status' => 'suspended']),
            12 => $this->user(12, 2, 'Other organizer'),
        ];
        $this->reviews->events[41] = [
            'id' => 41,
            'title' => 'Completed event',
            'slug' => 'completed-event',
            'eligible_participants' => [7],
        ];
        $this->logPath = sys_get_temp_dir() . '/oems-review-service-' . bin2hex(random_bytes(6)) . '.log';

        if (class_exists(ReviewService::class)) {
            $this->service = new ReviewService(
                $this->connection,
                $this->users,
                $this->reviews,
                new Logger($this->logPath),
            );
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testParticipantSubmissionValidatesActorRatingAndTrimmedCommentBounds(): void
    {
        $service = $this->service();

        $this->assertFalse($service->submit(10, 41, '5', 'A valid participant review.')['success']);
        $this->assertFalse($service->submit(11, 41, '5', 'A valid participant review.')['success']);
        $this->assertFalse($service->submit(8, 41, '5', 'A valid participant review.')['success']);
        $this->assertFalse($service->submit(7, 0, '5', 'A valid participant review.')['success']);
        $this->assertFalse($service->submit(7, 41, '0', 'A valid participant review.')['success']);
        $this->assertFalse($service->submit(7, 41, '6', 'A valid participant review.')['success']);
        $this->assertFalse($service->submit(7, 41, '4.5', 'A valid participant review.')['success']);
        $this->assertFalse($service->submit(7, 41, '5', 'too short')['success']);
        $this->assertFalse($service->submit(7, 41, '5', str_repeat('a', 2001))['success']);

        $result = $service->submit(7, 41, '5', '   A clear and useful participant review.   ');
        $this->assertTrue($result['success']);
        $this->assertSame(5, $result['review']['rating']);
        $this->assertSame('A clear and useful participant review.', $result['review']['review']);
        $this->assertSame('pending', $result['review']['status']);
    }

    public function testSubmissionRequiresRepositoryEligibilityAndEditingReturnsReviewToPending(): void
    {
        $service = $this->service();
        $this->reviews->reviews[15] = [
            'id' => 15,
            'event_id' => 41,
            'user_id' => 7,
            'rating' => 5,
            'review' => 'Original published review.',
            'status' => 'published',
            'organizer_reply' => 'Existing organizer response.',
        ];

        $updated = $service->submit(7, 41, 4, 'A revised review with enough detail.');
        $ineligible = $service->submit(7, 42, 4, 'This event is not reviewable yet.');

        $this->assertTrue($updated['success']);
        $this->assertSame(15, $updated['review']['id']);
        $this->assertSame('pending', $updated['review']['status']);
        $this->assertSame('Existing organizer response.', $updated['review']['organizer_reply']);
        $this->assertFalse($ineligible['success']);
        $this->assertArrayHasKey('event', $ineligible['errors']);
    }

    public function testOrganizerReplyRequiresVerifiedOrganizerOwnershipPublishedStateAndBounds(): void
    {
        $service = $this->service();
        $this->reviews->reviews[15] = [
            'id' => 15,
            'event_id' => 41,
            'user_id' => 7,
            'organizer_user_id' => 8,
            'rating' => 5,
            'review' => 'A published participant review.',
            'status' => 'published',
        ];

        $this->assertFalse($service->reply(7, 15, 'Thank you')['success']);
        $this->assertFalse($service->reply(8, 15, 'x')['success']);
        $this->assertFalse($service->reply(8, 15, str_repeat('x', 1001))['success']);
        $foreignValid = $service->reply(12, 15, 'Thank you for attending.');
        $foreignInvalid = $service->reply(12, 15, 'x');
        $this->assertFalse($foreignValid['success']);
        $this->assertSame('not_found', $foreignValid['code']);
        $this->assertFalse($foreignInvalid['success']);
        $this->assertSame('not_found', $foreignInvalid['code']);

        $result = $service->reply(8, 15, '  Thank you for the thoughtful feedback.  ');
        $this->assertTrue($result['success']);
        $this->assertSame('Thank you for the thoughtful feedback.', $result['review']['organizer_reply']);
        $this->assertSame([
            'recipient_user_id' => 7,
            'type' => 'review_reply',
            'review_id' => 15,
            'event_id' => 41,
        ], $result['notification']);
    }

    public function testAdminModerationIsAllowListedIdempotentAndReportsCompetingStateAsConflict(): void
    {
        $service = $this->service();
        $this->reviews->reviews[15] = [
            'id' => 15,
            'event_id' => 41,
            'user_id' => 7,
            'status' => 'pending',
            'rating' => 5,
            'review' => 'A pending review for moderation.',
        ];

        $this->assertFalse($service->moderate(8, 15, 'published')['success']);
        $this->assertFalse($service->moderate(9, 15, 'pending')['success']);
        $this->assertTrue($service->moderate(9, 15, 'published')['success']);
        $this->assertTrue($service->moderate(9, 15, 'published')['success']);
        $conflict = $service->moderate(9, 15, 'hidden');
        $this->assertFalse($conflict['success']);
        $this->assertSame('conflict', $conflict['code']);
        $this->assertSame('published', $this->reviews->reviews[15]['status']);
    }

    public function testOperationFailureReturnsFieldSafeErrorAndLogsOnlySafeIdentifiersAndClass(): void
    {
        $service = $this->service();
        $this->reviews->throwOnSave = true;

        $result = $service->submit(7, 41, 5, 'A database failure should stay private.');
        $log = is_file($this->logPath) ? (string) file_get_contents($this->logPath) : '';

        $this->assertFalse($result['success']);
        $this->assertSame(['review' => ['The review could not be saved.']], $result['errors']);
        $this->assertTrue(str_contains($log, 'review_submit'));
        $this->assertTrue(str_contains($log, '"actor_id":7'));
        $this->assertTrue(str_contains($log, '"event_id":41'));
        $this->assertTrue(str_contains($log, 'RuntimeException'));
        $this->assertFalse(str_contains($log, 'Database payload must not escape'));
        $this->assertFalse(str_contains($log, 'database failure should stay private'));
    }

    private function service(): ReviewService
    {
        $this->assertTrue($this->service instanceof ReviewService, 'Review service is missing.');

        return $this->service;
    }

    private function user(int $id, int $roleId, string $name): array
    {
        return [
            'id' => $id,
            'role_id' => $roleId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)) . '@example.test',
            'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ];
    }
}
