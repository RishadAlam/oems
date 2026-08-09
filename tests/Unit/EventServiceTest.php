<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\EventService;
use OEMS\App\Services\ImageUploadService;
use OEMS\App\Services\NotificationService;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeCategoryRepository;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\FakeNotificationRepository;
use OEMS\Tests\Support\FakeOrganizerRepository;
use OEMS\Tests\Support\FakeVenueRepository;
use OEMS\Tests\Support\TestCase;
use OEMS\Tests\Support\TestImage;

final class EventServiceTest extends TestCase
{
    private FakeCategoryRepository $categories;

    private FakeVenueRepository $venues;

    private FakeEventRepository $events;

    private EventService $service;

    private string $temporaryDirectory;

    private string $uploadRoot;

    protected function setUp(): void
    {
        $this->categories = new FakeCategoryRepository();
        $this->venues = new FakeVenueRepository();
        $this->events = new FakeEventRepository();
        $this->temporaryDirectory = sys_get_temp_dir() . '/oems-event-service-' . bin2hex(random_bytes(6));
        $this->uploadRoot = $this->temporaryDirectory . '/public/uploads/events';
        mkdir($this->uploadRoot, 0775, true);
        $uploads = new ImageUploadService($this->uploadRoot, '/uploads/events', 5 * 1024 * 1024, false);
        $this->service = new EventService(
            $this->events,
            $this->categories,
            $this->venues,
            $uploads,
            new FakeOrganizerRepository(),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testCreateDraftBuildsACollisionSafeSlugAndNormalizesPriceTagsAndDates(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 20, 'published', [
            'slug' => 'developer-summit',
        ]);
        $input = $this->validInput([
            'title' => '  Developer Summit  ',
            'ticket_price' => '1250.5',
            'tags' => ' PHP, Community, php, Testing ',
        ]);

        $result = $this->service->createDraft(10, $input, null, []);
        $created = $this->events->events[(int) $result['event_id']];

        $this->assertTrue($result['success']);
        $this->assertSame('Developer Summit', $created['title']);
        $this->assertSame('developer-summit-2', $created['slug']);
        $this->assertSame('1250.50', $created['ticket_price']);
        $this->assertSame(['php', 'community', 'testing'], $created['tags']);
        $this->assertSame('2030-01-10 10:00:00', $created['start_date']);
        $this->assertSame(80, $created['available_seats']);
        $this->assertSame('BDT', $created['currency']);
    }

    public function testCreateDraftAllowsTheLocationSlugUsedByPublishedEvents(): void
    {
        $result = $this->service->createDraft(10, $this->validInput(['title' => 'Location']), null, []);
        $created = $this->events->events[(int) $result['event_id']];

        $this->assertTrue($result['success']);
        $this->assertSame('location', $created['slug']);
    }

    public function testEventPersistsRestrictedLocationAndArrivalNotes(): void
    {
        $this->venues->venues[1]['latitude'] = '23.8103000';
        $this->venues->venues[1]['longitude'] = '90.4125000';

        $result = $this->service->createDraft(10, $this->validInput([
            'location_visibility' => 'registered',
            'arrival_notes' => '  Use the north entrance beside the library.  ',
        ]), null, []);

        $this->assertTrue($result['success']);
        $event = $this->events->events[$result['event_id']];
        $this->assertSame('registered', $event['location_visibility']);
        $this->assertSame('Use the north entrance beside the library.', $event['arrival_notes']);
    }

    public function testEventRejectsInvalidLocationPrivacyCombinations(): void
    {
        $unknown = $this->service->createDraft(10, $this->validInput([
            'location_visibility' => 'secret',
        ]), null, []);
        $tooLong = $this->service->createDraft(10, $this->validInput([
            'arrival_notes' => str_repeat('x', 501),
        ]), null, []);
        $withoutVenue = $this->service->createDraft(10, $this->validInput([
            'venue_id' => '',
            'arrival_notes' => 'Meet beside the lift.',
        ]), null, []);
        $restrictedFailures = [];
        foreach ([
            ['latitude' => null, 'longitude' => null],
            ['latitude' => '23.8103000', 'longitude' => null],
            ['latitude' => null, 'longitude' => '90.4125000'],
        ] as $coordinates) {
            $this->venues->venues[1] = array_merge($this->venues->venues[1], $coordinates);
            $restrictedFailures[] = $this->service->createDraft(10, $this->validInput([
                'location_visibility' => 'registered',
            ]), null, []);
        }
        $this->venues->venues[1]['latitude'] = null;
        $this->venues->venues[1]['longitude'] = null;
        $publicWithoutPin = $this->service->createDraft(10, $this->validInput([
            'location_visibility' => 'public',
            'arrival_notes' => '',
        ]), null, []);

        $this->assertArrayHasKey('location_visibility', $unknown['errors']);
        $this->assertArrayHasKey('arrival_notes', $tooLong['errors']);
        $this->assertArrayHasKey('arrival_notes', $withoutVenue['errors']);
        foreach ($restrictedFailures as $restrictedWithoutPin) {
            $this->assertArrayHasKey('location_visibility', $restrictedWithoutPin['errors']);
        }
        $this->assertTrue($publicWithoutPin['success']);
    }

    public function testEventRejectsHttpsMapUrlFromUntrustedHost(): void
    {
        $result = $this->service->createDraft(10, $this->validInput([
            'map_url' => 'https://www.google.com.evil.test/event',
        ]), null, []);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('map_url', $result['errors']);
    }

    public function testCreateDraftRejectsAnInactiveCategory(): void
    {
        $result = $this->service->createDraft(10, $this->validInput(['category_id' => '2']), null, []);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('category_id', $result['errors']);
        $this->assertSame([], $this->events->events);
    }

    public function testCreateDraftRejectsAVenueOwnedByAnotherOrganizer(): void
    {
        $result = $this->service->createDraft(10, $this->validInput(['venue_id' => '2']), null, []);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('venue_id', $result['errors']);
        $this->assertSame([], $this->events->events);
    }

    public function testCreateDraftRejectsCapacityAboveTheOwnedVenueLimit(): void
    {
        $result = $this->service->createDraft(10, $this->validInput(['capacity' => '101']), null, []);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('capacity', $result['errors']);
        $this->assertSame([], $this->events->events);
    }

    public function testArrayShapedFormValuesReturnValidationErrorsWithoutPhpWarnings(): void
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            $result = $this->service->createDraft(10, $this->validInput([
                'title' => ['unexpected'],
                'tags' => [['unexpected']],
            ]), null, []);
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('title', $result['errors']);
    }

    public function testUpdateNormalizesDraftDataAndDeletesTheReplacedBannerAfterPersistence(): void
    {
        $oldSource = $this->generatedJpeg('old-banner.jpg');
        $oldStored = $this->uploadRoot . '/old-banner.jpg';
        rename($oldSource, $oldStored);
        $this->events->events[1] = $this->storedEvent(1, 10, 'draft', [
            'banner' => '/uploads/events/old-banner.jpg',
        ]);
        $newSource = $this->generatedJpeg('new-banner.jpg');

        $result = $this->service->update(
            10,
            1,
            $this->validInput(['title' => '  Updated Developer Summit  ']),
            $this->upload($newSource, 'new-banner.jpg'),
            [],
        );

        $this->assertTrue($result['success']);
        $this->assertSame('Updated Developer Summit', $this->events->events[1]['title']);
        $this->assertFalse(is_file($oldStored));
        $this->assertTrue(is_file(
            $this->uploadRoot . '/' . basename((string) $this->events->events[1]['banner']),
        ));
    }

    public function testUpdateFailureDeletesOnlyNewMediaAndLeavesTheStoredEventUnchanged(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 10, 'draft');
        $this->events->failUpdate = true;
        $newSource = $this->generatedJpeg('failed-banner.jpg');

        $result = $this->service->update(
            10,
            1,
            $this->validInput(['title' => 'Changed Title']),
            $this->upload($newSource, 'failed-banner.jpg'),
            [],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Stored Event', $this->events->events[1]['title']);
        $this->assertSame([], $this->storedUploadFiles());
    }

    public function testAtomicCreateFailureRemovesNewMediaWithoutLeavingAnEventOrSlug(): void
    {
        $bannerPath = $this->generatedJpeg('atomic-create-banner.jpg');
        $galleryPath = $this->generatedJpeg('atomic-create-gallery.jpg');
        $this->events->failGalleryReplacement = true;

        $result = $this->service->createDraft(
            10,
            $this->validInput(['title' => 'Atomic Create Failure']),
            $this->upload($bannerPath, 'atomic-create-banner.jpg'),
            [$this->upload($galleryPath, 'atomic-create-gallery.jpg')],
        );

        $this->assertFalse($result['success']);
        $this->assertSame([], $this->events->events);
        $this->assertFalse($this->events->slugExists('atomic-create-failure', null));
        $this->assertSame([], $this->storedUploadFiles());
    }

    public function testCaughtEventPersistenceExceptionsLogOnlySanitizedOperationContext(): void
    {
        $logPath = $this->temporaryDirectory . '/event-persistence.log';
        file_put_contents($logPath, '');
        $service = new EventService(
            $this->events,
            $this->categories,
            $this->venues,
            new ImageUploadService($this->uploadRoot, '/uploads/events', 5 * 1024 * 1024, false),
            new FakeOrganizerRepository(),
            new Logger($logPath),
        );
        $this->events->failGalleryReplacement = true;

        $created = $service->createDraft(10, $this->validInput(), null, []);
        $this->events->events[1] = $this->storedEvent(1, 10, 'draft');
        $updated = $service->update(10, 1, $this->validInput(['title' => 'Logged Update']), null, []);
        $log = file_get_contents($logPath);

        $this->assertFalse($created['success']);
        $this->assertFalse($updated['success']);
        $this->assertTrue(is_string($log));
        $this->assertTrue(str_contains($log, 'Event persistence operation failed.'));
        $this->assertTrue(str_contains($log, '"operation":"create"'));
        $this->assertTrue(str_contains($log, '"operation":"update"'));
        $this->assertTrue(str_contains($log, '"user_id":10'));
        $this->assertTrue(str_contains($log, '"event_id":1'));
        $this->assertFalse(str_contains($log, 'Gallery persistence failed'));
        $this->assertFalse(str_contains($log, 'password'));
        $this->assertFalse(str_contains($log, $this->uploadRoot));
    }

    public function testAtomicUpdateFailurePreservesOldEventGalleryAndMediaAndRemovesNewFiles(): void
    {
        $oldBanner = $this->storeExistingImage('old-atomic-banner.jpg');
        $oldGallery = $this->storeExistingImage('old-atomic-gallery.jpg');
        $this->events->events[1] = $this->storedEvent(1, 10, 'draft', ['banner' => $oldBanner]);
        $this->events->galleries[1] = [$oldGallery];
        $newBanner = $this->generatedJpeg('new-atomic-banner.jpg');
        $newGallery = $this->generatedJpeg('new-atomic-gallery.jpg');
        $this->events->failGalleryReplacement = true;

        $result = $this->service->update(
            10,
            1,
            $this->validInput(['title' => 'Must Roll Back']),
            $this->upload($newBanner, 'new-atomic-banner.jpg'),
            [$this->upload($newGallery, 'new-atomic-gallery.jpg')],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Stored Event', $this->events->events[1]['title']);
        $this->assertSame($oldBanner, $this->events->events[1]['banner']);
        $this->assertSame([$oldGallery], $this->events->galleries[1]);
        $this->assertSame(['old-atomic-banner.jpg', 'old-atomic-gallery.jpg'], $this->storedUploadFiles());
    }

    public function testSuccessfulAtomicGalleryReplacementDeletesSupersededGalleryAfterCommit(): void
    {
        $retainedBanner = $this->storeExistingImage('retained-banner.jpg');
        $oldGallery = $this->storeExistingImage('superseded-gallery.jpg');
        $this->events->events[1] = $this->storedEvent(1, 10, 'draft', ['banner' => $retainedBanner]);
        $this->events->galleries[1] = [$retainedBanner, $oldGallery];
        $newGallery = $this->generatedJpeg('replacement-gallery.jpg');

        $result = $this->service->update(
            10,
            1,
            $this->validInput(['title' => 'Gallery Replaced']),
            null,
            [$this->upload($newGallery, 'replacement-gallery.jpg')],
        );

        $this->assertTrue($result['success']);
        $this->assertSame($retainedBanner, $this->events->events[1]['banner']);
        $this->assertTrue(is_file($this->uploadRoot . '/retained-banner.jpg'));
        $this->assertFalse(is_file($this->uploadRoot . '/superseded-gallery.jpg'));
        $this->assertSame(1, count($this->events->galleries[1]));
        $this->assertTrue(is_file(
            $this->uploadRoot . '/' . basename((string) $this->events->galleries[1][0]),
        ));
    }

    public function testPendingOrganizerCannotSubmitADraft(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 11, 'draft');

        $result = $this->service->submit(11, 1);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('status', $result['errors']);
        $this->assertSame('draft', $this->events->events[1]['status']);
    }

    public function testApprovedOrganizerMustEditARejectedEventBeforeResubmission(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 10, 'rejected', [
            'rejection_reason' => 'Clarify the schedule.',
        ]);

        $beforeEdit = $this->service->submit(10, 1);
        $edit = $this->service->update(10, 1, $this->validInput(['title' => 'Revised Event']), null, []);

        $this->assertFalse($beforeEdit['success']);
        $this->assertTrue($edit['success']);
        $this->assertSame('draft', $this->events->events[1]['status']);
        $this->assertNull($this->events->events[1]['rejection_reason']);

        $afterEdit = $this->service->submit(10, 1);

        $this->assertTrue($afterEdit['success']);
        $this->assertSame('pending', $this->events->events[1]['status']);
    }

    public function testOrganizerCannotCancelFromAnInvalidLifecycleState(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 10, 'draft');

        $result = $this->service->cancel(10, 1);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('status', $result['errors']);
        $this->assertSame('draft', $this->events->events[1]['status']);
    }

    public function testSuccessfulCancellationNotifiesAffectedParticipantsAfterTheTransition(): void
    {
        $notifications = new FakeNotificationRepository();
        $this->events->events[1] = $this->storedEvent(1, 10, 'published');
        $this->events->cancellationParticipantIds[1] = [31, 32];
        $service = new EventService(
            $this->events,
            $this->categories,
            $this->venues,
            new ImageUploadService($this->uploadRoot, '/uploads/events', 5 * 1024 * 1024, false),
            new FakeOrganizerRepository(),
            null,
            new NotificationService($notifications),
        );

        $result = $service->cancel(10, 1);

        $this->assertTrue($result['success']);
        $this->assertSame('cancelled', $this->events->events[1]['status']);
        $this->assertSame([31, 32], array_column($notifications->notifications, 'user_id'));
        $this->assertSame(
            ['event_cancelled', 'event_cancelled'],
            array_column($notifications->notifications, 'type'),
        );
    }

    public function testNotificationFailureCannotUndoACommittedCancellationAndIsSanitized(): void
    {
        $logPath = $this->temporaryDirectory . '/event-cancellation-notification.log';
        $notifications = new FakeNotificationRepository();
        $notifications->throwOnCreate = true;
        $this->events->events[1] = $this->storedEvent(1, 10, 'published');
        $this->events->cancellationParticipantIds[1] = [31];
        $service = new EventService(
            $this->events,
            $this->categories,
            $this->venues,
            new ImageUploadService($this->uploadRoot, '/uploads/events', 5 * 1024 * 1024, false),
            new FakeOrganizerRepository(),
            null,
            new NotificationService($notifications, new Logger($logPath)),
        );

        $result = $service->cancel(10, 1);
        $log = file_get_contents($logPath);

        $this->assertTrue($result['success']);
        $this->assertSame('cancelled', $this->events->events[1]['status']);
        $this->assertTrue(is_string($log));
        $this->assertTrue(str_contains($log, 'notification_dispatch'));
        $this->assertTrue(str_contains($log, '"user_id":31'));
        $this->assertTrue(str_contains($log, '"type":"event_cancelled"'));
        $this->assertFalse(str_contains($log, 'Notification delivery failed.'));
    }

    public function testOrganizerMayPublishOnlyAnOwnedApprovedEventAndRepeatIsTruthful(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 10, 'approved');
        $this->events->events[2] = $this->storedEvent(2, 20, 'approved');
        $this->events->events[3] = $this->storedEvent(3, 10, 'pending');

        $foreign = $this->service->publish(10, 2);
        $wrongState = $this->service->publish(10, 3);
        $published = $this->service->publish(10, 1);
        $repeat = $this->service->publish(10, 1);

        $this->assertFalse($foreign['success']);
        $this->assertArrayHasKey('event', $foreign['errors']);
        $this->assertFalse($wrongState['success']);
        $this->assertArrayHasKey('status', $wrongState['errors']);
        $this->assertTrue($published['success']);
        $this->assertSame('published', $published['status']);
        $this->assertTrue($repeat['success']);
        $this->assertSame('published', $repeat['status']);
    }

    public function testOrganizerPublicationReturnsConcurrentWinnersTruthfulPublishedState(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 10, 'approved');
        $this->events->publishLostToConcurrentWinner = true;

        $result = $this->service->publish(10, 1);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['event_id']);
        $this->assertSame('published', $result['status']);
        $this->assertSame('published', $this->events->events[1]['status']);
    }

    public function testAdministratorLifecycleAllowsOnlyTheSpecifiedNextState(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 10, 'pending');

        $invalid = $this->service->moderate(99, 1, 'published', null);
        $approved = $this->service->moderate(99, 1, 'approved', null);
        $published = $this->service->moderate(99, 1, 'published', null);
        $completed = $this->service->moderate(99, 1, 'completed', null);

        $this->assertFalse($invalid['success']);
        $this->assertTrue($approved['success']);
        $this->assertTrue($published['success']);
        $this->assertTrue($completed['success']);
        $this->assertSame('completed', $this->events->events[1]['status']);
    }

    public function testRejectionRequiresANonEmptyBoundedReason(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 10, 'pending');

        $missing = $this->service->moderate(99, 1, 'rejected', '   ');
        $tooLong = $this->service->moderate(99, 1, 'rejected', str_repeat('x', 501));
        $valid = $this->service->moderate(99, 1, 'rejected', '  Add venue accessibility details.  ');

        $this->assertFalse($missing['success']);
        $this->assertArrayHasKey('reason', $missing['errors']);
        $this->assertFalse($tooLong['success']);
        $this->assertArrayHasKey('reason', $tooLong['errors']);
        $this->assertTrue($valid['success']);
        $this->assertSame('Add venue accessibility details.', $this->events->events[1]['rejection_reason']);
    }

    public function testNewMediaIsRemovedWhenEventPersistenceFails(): void
    {
        $bannerPath = $this->generatedJpeg('banner.jpg');
        $galleryPath = $this->generatedJpeg('gallery.jpg');
        $this->events->failCreate = true;

        $result = $this->service->createDraft(
            10,
            $this->validInput(),
            $this->upload($bannerPath, 'banner.jpg'),
            [$this->upload($galleryPath, 'gallery.jpg')],
        );

        $this->assertFalse($result['success']);
        $this->assertSame([], $this->storedUploadFiles());
    }

    public function testCreateDraftRejectsMoreThanSixGalleryImages(): void
    {
        $result = $this->service->createDraft(10, $this->validInput(), null, array_fill(0, 7, null));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('gallery', $result['errors']);
        $this->assertSame([], $this->events->events);
    }

    public function testOrganizerMayDeleteOnlyDraftRejectedOrCancelledEvents(): void
    {
        $this->events->events[1] = $this->storedEvent(1, 10, 'published');
        $this->events->events[2] = $this->storedEvent(2, 10, 'draft');

        $published = $this->service->delete(10, 1);
        $draft = $this->service->delete(10, 2);

        $this->assertFalse($published['success']);
        $this->assertNull($this->events->events[1]['deleted_at']);
        $this->assertTrue($draft['success']);
        $this->assertSame('now', $this->events->events[2]['deleted_at']);
    }

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'category_id' => '1',
            'venue_id' => '1',
            'title' => 'Developer Summit',
            'description' => 'A practical event for developers to learn and connect with peers.',
            'map_url' => 'https://www.google.com/maps/developer-summit',
            'speaker' => 'OEMS Community',
            'start_date' => '2030-01-10T10:00',
            'end_date' => '2030-01-10T13:00',
            'registration_deadline' => '2030-01-09T18:00',
            'capacity' => '80',
            'ticket_price' => '0',
            'tags' => 'php, community',
            'location_visibility' => 'public',
            'arrival_notes' => '',
        ], $overrides);
    }

    private function storedEvent(int $id, int $userId, string $status, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'user_id' => $userId,
            'category_id' => 1,
            'venue_id' => 1,
            'title' => 'Stored Event',
            'slug' => 'stored-event-' . $id,
            'description' => 'A stored event with enough descriptive content for the fixture.',
            'banner' => null,
            'map_url' => null,
            'speaker' => null,
            'start_date' => '2030-01-10 10:00:00',
            'end_date' => '2030-01-10 13:00:00',
            'registration_deadline' => '2030-01-09 18:00:00',
            'capacity' => 80,
            'available_seats' => 80,
            'ticket_price' => '0.00',
            'currency' => 'BDT',
            'tags' => [],
            'status' => $status,
            'rejection_reason' => null,
            'is_featured' => false,
            'location_visibility' => 'public',
            'arrival_notes' => null,
            'deleted_at' => null,
        ], $overrides);
    }

    private function generatedJpeg(string $filename): string
    {
        return TestImage::writeJpeg($this->temporaryDirectory . '/' . $filename);
    }

    private function storeExistingImage(string $filename): string
    {
        $source = $this->generatedJpeg($filename);
        rename($source, $this->uploadRoot . '/' . $filename);

        return '/uploads/events/' . $filename;
    }

    private function upload(string $path, string $name): array
    {
        return [
            'name' => $name,
            'full_path' => $name,
            'type' => 'image/jpeg',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($path),
        ];
    }

    private function storedUploadFiles(): array
    {
        return array_values(array_filter(
            scandir($this->uploadRoot) ?: [],
            static fn (string $entry): bool => !in_array($entry, ['.', '..'], true),
        ));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
