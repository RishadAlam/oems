<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Controllers\ParticipantRegistrationController;
use OEMS\App\Services\RegistrationService;
use OEMS\App\Services\TicketArtifactService;
use OEMS\App\Services\TicketService;
use OEMS\App\Services\TransactionMailer;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Request;
use OEMS\Core\RateLimiter;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use OEMS\Tests\Support\FakeCategoryRepository;
use OEMS\Tests\Support\FakeEmailLogRepository;
use OEMS\Tests\Support\FakeEventRepository;
use OEMS\Tests\Support\FakeMailTransport;
use OEMS\Tests\Support\FakePaymentRepository;
use OEMS\Tests\Support\FakeRegistrationRepository;
use OEMS\Tests\Support\FakeTicketRepository;
use OEMS\Tests\Support\FakeUserRepository;
use OEMS\Tests\Support\FakeWaitlistRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use ErrorException;

final class ParticipantRegistrationControllerTest extends TestCase
{
    private mixed $controller = null;

    private Session $session;

    private FakeEventRepository $events;

    private FakeRegistrationRepository $registrations;

    private FakePaymentRepository $payments;

    private FakeTicketRepository $tickets;

    private FakeWaitlistRepository $waitlists;

    private string $ticketRoot;

    private string $limitRoot;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new Session(false);
        $users = new FakeUserRepository();
        $users->users[7] = [
            'id' => 7,
            'role_id' => 3,
            'name' => 'Nusrat Participant',
            'email' => 'nusrat@example.test',
            'password' => password_hash('secret-password', PASSWORD_DEFAULT),
            'status' => 'active',
            'email_verified_at' => '2026-08-01 09:00:00',
        ];
        $this->authenticateSession($this->session, $users, 7);
        $auth = new Auth($this->session, $users);
        $this->events = new FakeEventRepository();
        $this->registrations = new FakeRegistrationRepository();
        $this->payments = new FakePaymentRepository();
        $this->tickets = new FakeTicketRepository();
        $this->waitlists = new FakeWaitlistRepository();
        $event = $this->eventFixture();
        $this->events->events[31] = $event;
        $this->registrations->eligibleEvents[31] = $event;
        $this->payments->methods['manual'] = [
            'id' => 2,
            'name' => 'Manual <Demo> payment',
            'slug' => 'manual',
            'is_active' => 1,
            'configuration' => [
                'account_title' => 'OEMS <Demo> Payments',
                'account_identifier' => 'DEMO-<NOT-REAL>',
                'instructions' => 'DEMO ONLY: use a fictional reference. Do not send money.',
                'gateway_secret' => 'PRIVATE-GATEWAY-SECRET',
            ],
        ];
        $this->payments->methods['free'] = ['id' => 1, 'slug' => 'free', 'is_active' => 1];
        $connection = new PDO('sqlite::memory:');
        $this->ticketRoot = sys_get_temp_dir() . '/oems-controller-ticket-' . bin2hex(random_bytes(6));
        $this->limitRoot = sys_get_temp_dir() . '/oems-controller-registration-limit-' . bin2hex(random_bytes(6));
        $artifacts = new TicketArtifactService($this->ticketRoot, 'uploads/tickets');
        $service = new RegistrationService(
            $connection,
            $users,
            $this->registrations,
            $this->payments,
            new TicketService($connection, $this->tickets, $artifacts),
            new TransactionMailer(
                new FakeMailTransport('<message-id>'),
                new FakeEmailLogRepository(),
                new Config(['name' => 'OEMS', 'url' => 'https://events.example.test']),
            ),
            waitlists: $this->waitlists,
        );

        if (class_exists(ParticipantRegistrationController::class)) {
            $this->controller = new ParticipantRegistrationController(
                new View(base_path('app/Views')),
                $this->session,
                new Security($this->session),
                $auth,
                new Config(['name' => 'OEMS', 'timezone' => 'Asia/Dhaka']),
                $this->events,
                $this->registrations,
                $this->payments,
                $this->tickets,
                $service,
                new RateLimiter($this->limitRoot, 5, 900),
            );
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->ticketRoot . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->ticketRoot)) {
            rmdir($this->ticketRoot);
        }
        foreach (glob($this->limitRoot . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->limitRoot)) {
            rmdir($this->limitRoot);
        }
        $_SESSION = [];
    }

    public function testPaidCheckoutShowsOneSeatAndTheExactDatabaseTotalWithoutSecretFields(): void
    {
        $body = $this->controller()->create($this->slugged('GET', 'future-craft'))->body();

        $this->assertTrue(str_contains($body, 'Future Craft'));
        $this->assertTrue(str_contains($body, 'One seat'));
        $this->assertTrue(str_contains($body, '৳1,250'));
        $this->assertTrue(str_contains($body, 'BDT'));
        $this->assertTrue(str_contains($body, 'name="channel"'));
        $this->assertTrue(str_contains($body, 'name="transaction_reference"'));
        $this->assertTrue(str_contains($body, 'maxlength="190"'));
        $this->assertTrue(str_contains($body, 'data-form-kind="entry"'));
        $this->assertFalse(str_contains($body, 'method="post" novalidate'));
        $this->assertTrue(str_contains($body, 'data-submit-label="Submitting registration…"'));
        $this->assertTrue(str_contains($body, 'Manual &lt;Demo&gt; payment'));
        $this->assertTrue(str_contains($body, 'OEMS &lt;Demo&gt; Payments'));
        $this->assertTrue(str_contains($body, 'DEMO-&lt;NOT-REAL&gt;'));
        $this->assertTrue(str_contains($body, 'DEMO ONLY: use a fictional reference. Do not send money.'));
        $this->assertTrue(str_contains($body, 'aria-labelledby="manual-payment-guidance-heading"'));
        $this->assertFalse(str_contains($body, 'PRIVATE-GATEWAY-SECRET'));
        $this->assertFalse(str_contains($body, 'gateway_secret'));
        $this->assertFalse(str_contains($body, 'card_number'));
        $this->assertFalse(str_contains($body, 'account_number'));
    }

    public function testFreeCheckoutUsesOneConfirmationActionWithoutPaymentReferenceFields(): void
    {
        $event = array_merge($this->eventFixture(), ['id' => 32, 'slug' => 'community-meetup', 'ticket_price' => '0.00']);
        $this->events->events[32] = $event;
        $this->registrations->eligibleEvents[32] = $event;

        $body = $this->controller()->create($this->slugged('GET', 'community-meetup'))->body();

        $this->assertTrue(str_contains($body, 'Confirm free registration'));
        $this->assertFalse(str_contains($body, 'manual-payment-guidance-heading'));
        $this->assertFalse(str_contains($body, 'DEMO ONLY'));
        $this->assertFalse(str_contains($body, 'name="channel"'));
        $this->assertFalse(str_contains($body, 'name="transaction_reference"'));
    }

    public function testPreRegistrationSummaryNeverExposesRestrictedVenueIdentity(): void
    {
        $restricted = array_merge($this->eventFixture(), [
            'location_visibility' => 'registered',
            'venue_name' => 'HOSTILE SECRET VENUE',
            'venue_address_line' => 'PRIVATE ROAD 99',
            'venue_city' => 'Dhaka',
            'venue_country' => 'Bangladesh',
            'venue_latitude' => '23.8103',
            'venue_longitude' => '90.4125',
            'arrival_notes' => 'PRIVATE DOOR CODE',
        ]);
        $this->events->events[31] = $restricted;
        $this->registrations->eligibleEvents[31] = $restricted;

        $body = $this->controller()->create($this->slugged('GET', 'future-craft'))->body();

        $this->assertTrue(str_contains($body, 'Dhaka, Bangladesh'));
        foreach (['HOSTILE SECRET VENUE', 'PRIVATE ROAD 99', '23.8103', '90.4125', 'PRIVATE DOOR CODE'] as $secret) {
            $this->assertFalse(str_contains($body, $secret), 'Pre-registration view leaked restricted venue data: ' . $secret);
        }
    }

    public function testPaidCheckoutBoundsManualInstructionsBeforeRendering(): void
    {
        $this->payments->methods['manual']['configuration']['instructions'] = str_repeat('A', 500)
            . 'PRIVATE-INSTRUCTION-TAIL';

        $body = $this->controller()->create($this->slugged('GET', 'future-craft'))->body();

        $this->assertFalse(str_contains($body, 'PRIVATE-INSTRUCTION-TAIL'));
        $this->assertTrue(str_contains($body, str_repeat('A', 500)));
    }

    public function testPaidSubmissionUsesAuthenticatedOwnerAndDatabaseAmount(): void
    {
        $response = $this->controller()->store($this->slugged('POST', 'future-craft', [
            'user_id' => '99',
            'amount' => '1.00',
            'currency' => 'USD',
            'channel' => 'mobile',
            'transaction_reference' => 'MOBILE-REF-20260809',
        ]));

        $this->assertSame(302, $response->status());
        $this->assertSame('/participant/registrations/1', $response->header('Location'));
        $this->assertSame(7, $this->registrations->registrations[1]['user_id']);
        $this->assertSame('1250.00', $this->payments->payments[1]['amount']);
        $this->assertSame('BDT', $this->payments->payments[1]['currency']);
        $this->assertSame(['channel' => 'mobile'], $this->payments->payments[1]['gateway_response']);
    }

    public function testPromotedWaitlistClaimShowsAndAcceptsBoundedManualPayment(): void
    {
        $registration = array_merge($this->registrationFixture(), [
            'id' => 55,
            'status' => 'pending',
            'registration_status' => 'pending',
            'promoted_at' => '2026-08-10 10:00:00',
            'waitlist_claim_expires_at' => '2099-08-11 10:00:00',
        ]);
        $this->registrations->registrations[55] = $registration;
        $this->waitlists->entries[55] = $registration;

        $body = $this->controller()->show(Request::create('GET', '/participant/registrations/55')->withRouteParameters(['id' => '55']))->body();
        $this->assertTrue(str_contains($body, 'Waitlist seat ready'));
        $this->assertTrue(str_contains($body, 'name="transaction_reference"'));
        $this->assertTrue(str_contains($body, 'name="channel"'));
        $this->assertTrue(str_contains($body, 'minlength="6" maxlength="190"'));
        $this->assertTrue(str_contains($body, 'data-form-kind="entry"'));
        $this->assertTrue(str_contains($body, 'data-submit-label="Submitting payment…"'));

        $invalid = $this->controller()->submitPromotedPayment(Request::create('POST', '/participant/registrations/55/payment', input: [
            'channel' => 'not-allowed',
            'transaction_reference' => '',
        ])->withRouteParameters(['id' => '55']));
        $this->assertSame('/participant/registrations/55', $invalid->header('Location'));
        $this->assertSame(0, count($this->payments->payments));

        $valid = $this->controller()->submitPromotedPayment(Request::create('POST', '/participant/registrations/55/payment', input: [
            'channel' => 'bank_transfer',
            'transaction_reference' => 'WAITLIST-CLAIM-55',
        ])->withRouteParameters(['id' => '55']));
        $this->assertSame('/participant/registrations/55', $valid->header('Location'));
        $this->assertSame('1250.00', $this->payments->payments[1]['amount']);
        $this->assertSame(null, $this->waitlists->entries[55]['waitlist_claim_expires_at'] ?? null);
    }

    public function testPostRetryFindsOwnedActiveRegistrationBeforeFinalSeatAndDeadlineAvailabilityChecks(): void
    {
        $soldOut = array_merge($this->eventFixture(), [
            'id' => 32,
            'slug' => 'last-seat-retry',
            'available_seats' => 0,
        ]);
        $deadlinePassed = array_merge($this->eventFixture(), [
            'id' => 33,
            'slug' => 'deadline-retry',
            'registration_deadline' => '2000-08-21 18:00:00',
        ]);
        $this->events->events[32] = $soldOut;
        $this->events->events[33] = $deadlinePassed;
        $this->registrations->registrations[4] = array_merge($this->registrationFixture(), [
            'id' => 4,
            'event_id' => 32,
            'user_id' => 7,
            'status' => 'pending',
        ]);
        $this->registrations->registrations[5] = array_merge($this->registrationFixture(), [
            'id' => 5,
            'event_id' => 33,
            'user_id' => 7,
            'status' => 'confirmed',
        ]);

        $soldOutRetry = $this->controller()->store($this->slugged('POST', 'last-seat-retry'));
        $deadlineRetry = $this->controller()->store($this->slugged('POST', 'deadline-retry'));

        $this->assertSame('/participant/registrations/4', $soldOutRetry->header('Location'));
        $this->assertSame('/participant/registrations/5', $deadlineRetry->header('Location'));
        $this->assertSame([4, 5], array_keys($this->registrations->registrations));
    }

    public function testPaymentErrorsAreInlineAndNeverRepopulateTheReference(): void
    {
        $response = $this->controller()->store($this->slugged('POST', 'future-craft', [
            'channel' => 'card',
            'transaction_reference' => 'abc',
        ]));

        $this->assertSame('/participant/events/future-craft/register', $response->header('Location'));
        $this->assertSame(['channel' => 'card'], $this->session->get('_flash.old'));
        $this->assertFalse(array_key_exists('transaction_reference', $this->session->get('_flash.old', [])));

        $body = $this->controller()->create($this->slugged('GET', 'future-craft'))->body();
        $this->assertTrue(str_contains($body, 'Select a supported payment channel.'));
        $this->assertTrue(str_contains($body, 'Enter a transaction reference between 6 and 190 characters.'));
        $this->assertTrue(str_contains($body, 'aria-describedby="channel-help channel-error"'));
        $this->assertFalse(str_contains($body, 'value="abc"'));
    }

    public function testHistoryAndDetailAreOwnershipScopedAndEscapeStoredValues(): void
    {
        $this->registrations->registrations = [
            4 => array_merge($this->registrationFixture(), ['id' => 4, 'user_id' => 7, 'event_title' => '<script>Owned</script>']),
            5 => array_merge($this->registrationFixture(), ['id' => 5, 'user_id' => 88, 'event_title' => 'Foreign event']),
        ];
        $this->payments->payments[9] = [
            'id' => 9, 'registration_id' => 4, 'status' => 'pending', 'amount' => '1250.00', 'currency' => 'BDT',
        ];

        $index = $this->controller()->index(Request::create('GET', '/participant/registrations'));
        $owned = $this->controller()->show($this->idRouted('GET', 4));
        $foreign = $this->controller()->show($this->idRouted('GET', 5));

        $this->assertTrue(str_contains($index->body(), '&lt;script&gt;Owned&lt;/script&gt;'));
        $this->assertFalse(str_contains($index->body(), 'Foreign event'));
        $this->assertTrue(str_contains($owned->body(), 'Payment review pending'));
        $this->assertTrue(str_contains($owned->body(), 'Status timeline'));
        $this->assertSame(404, $foreign->status());
        $this->assertSame(404, $this->controller()->show($this->idRouted('GET', 0))->status());
    }

    public function testOnlyConfirmedRegistrationDetailExposesRestrictedVenueIdentity(): void
    {
        foreach (['pending', 'cancelled', 'refunded'] as $index => $status) {
            $id = 20 + $index;
            $this->registrations->registrations[$id] = array_merge($this->registrationFixture(), [
                'id' => $id,
                'user_id' => 7,
                'status' => $status,
                'registration_status' => $status,
                'location_visibility' => 'registered',
                'venue_name' => 'HOSTILE SECRET VENUE',
                'venue_address_line' => 'PRIVATE ROAD 99',
                'venue_city' => 'Dhaka',
                'venue_country' => 'Bangladesh',
                'venue_latitude' => '23.8103',
                'venue_longitude' => '90.4125',
                'arrival_notes' => 'PRIVATE DOOR CODE',
            ]);

            $body = $this->controller()->show($this->idRouted('GET', $id))->body();

            $this->assertTrue(str_contains($body, 'Dhaka, Bangladesh'));
            foreach (['HOSTILE SECRET VENUE', 'PRIVATE ROAD 99', '23.8103', '90.4125', 'PRIVATE DOOR CODE'] as $secret) {
                $this->assertFalse(str_contains($body, $secret), ucfirst($status) . ' detail leaked: ' . $secret);
            }
        }

        $this->registrations->registrations[30] = array_merge($this->registrationFixture(), [
            'id' => 30,
            'user_id' => 7,
            'status' => 'confirmed',
            'registration_status' => 'confirmed',
            'location_visibility' => 'registered',
            'venue_name' => 'HOSTILE SECRET VENUE',
            'venue_city' => 'Dhaka',
            'venue_country' => 'Bangladesh',
        ]);

        $confirmed = $this->controller()->show($this->idRouted('GET', 30))->body();
        $this->assertTrue(str_contains($confirmed, 'HOSTILE SECRET VENUE'));
    }

    public function testCancellationUsesAuthenticatedOwnershipAndRedirectsToTheOwnedDetail(): void
    {
        $this->registrations->registrations[4] = array_merge($this->registrationFixture(), ['id' => 4, 'user_id' => 7]);
        $this->payments->payments[9] = [
            'id' => 9, 'registration_id' => 4, 'status' => 'pending', 'amount' => '1250.00', 'currency' => 'BDT',
        ];

        $response = $this->controller()->cancel($this->idRouted('POST', 4, [
            'user_id' => '88',
            'reason' => 'Schedule conflict',
        ]));

        $this->assertSame('/participant/registrations/4', $response->header('Location'));
        $this->assertSame('cancelled', $this->registrations->registrations[4]['status']);
        $this->assertSame('Schedule conflict', $this->registrations->registrations[4]['cancellation_reason']);
    }

    public function testRejectedPaymentExplainsReleasedPlaceAndIneligibleRegistrationCannotCancel(): void
    {
        $this->registrations->registrations[4] = array_merge($this->registrationFixture(), [
            'id' => 4,
            'user_id' => 7,
            'status' => 'cancelled',
            'registration_status' => 'cancelled',
            'cancellation_reason' => 'Payment rejected',
        ]);
        $this->payments->payments[9] = [
            'id' => 9, 'registration_id' => 4, 'status' => 'failed', 'amount' => '1250.00', 'currency' => 'BDT',
        ];

        $body = $this->controller()->show($this->idRouted('GET', 4))->body();

        $this->assertTrue(str_contains($body, 'payment reference was rejected'));
        $this->assertTrue(str_contains($body, 'place was released'));
        $this->assertFalse(str_contains($body, '>Cancel registration</span>'));
    }

    public function testDetailExplainsStartedAndCheckedInCancellationStatesWithoutDisclosingForeignRecords(): void
    {
        $this->registrations->registrations = [
            4 => array_merge($this->registrationFixture(), [
                'id' => 4,
                'user_id' => 7,
                'event_start_date' => '2000-08-22 10:00:00',
            ]),
            5 => array_merge($this->registrationFixture(), [
                'id' => 5,
                'user_id' => 7,
                'status' => 'confirmed',
            ]),
            6 => array_merge($this->registrationFixture(), ['id' => 6, 'user_id' => 88]),
        ];
        $this->tickets->tickets[12] = [
            'id' => 12,
            'registration_id' => 5,
            'participant_id' => 7,
            'ticket_number' => 'OEMS-CHECKED-IN',
            'status' => 'used',
            'attendance_id' => 91,
        ];

        $started = $this->controller()->show($this->idRouted('GET', 4));
        $checkedIn = $this->controller()->show($this->idRouted('GET', 5));
        $foreign = $this->controller()->show($this->idRouted('GET', 6));

        $this->assertTrue(str_contains($started->body(), 'Cancellation unavailable'));
        $this->assertTrue(str_contains($started->body(), 'event has already started'));
        $this->assertTrue(str_contains($checkedIn->body(), 'Cancellation unavailable'));
        $this->assertTrue(str_contains($checkedIn->body(), 'after event check-in'));
        $this->assertFalse(str_contains($started->body(), '>Cancel registration</span>'));
        $this->assertFalse(str_contains($checkedIn->body(), '>Cancel registration</span>'));
        $this->assertSame(404, $foreign->status());
    }

    public function testServiceLevelCancellationErrorIsBoundToTheReasonTextarea(): void
    {
        $this->registrations->registrations[4] = array_merge($this->registrationFixture(), [
            'id' => 4,
            'user_id' => 7,
        ]);
        $this->session->flash('errors', [
            'registration' => ['This registration can no longer be cancelled.'],
        ]);

        $body = $this->controller()->show($this->idRouted('GET', 4))->body();

        $this->assertTrue(str_contains(
            $body,
            'name="reason" rows="3" maxlength="500" required aria-describedby="reason-help reason-error" aria-invalid="true"',
        ));
        $this->assertTrue(str_contains($body, 'id="reason-help"'));
        $this->assertTrue(str_contains($body, 'id="reason-error"'));
        $this->assertTrue(str_contains($body, 'This registration can no longer be cancelled.'));
    }

    public function testArrayShapedPaymentFieldsFailSafelyWithoutWarningsOrOldSecrets(): void
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });

        try {
            $response = $this->controller()->store($this->slugged('POST', 'future-craft', [
                'channel' => ['mobile'],
                'transaction_reference' => ['PRIVATE-REFERENCE'],
            ]));
        } finally {
            restore_error_handler();
        }

        $this->assertSame(302, $response->status());
        $this->assertSame(['channel' => ''], $this->session->get('_flash.old'));
        $this->assertFalse(array_key_exists('transaction_reference', $this->session->get('_flash.old', [])));
        $this->assertArrayHasKey('channel', $this->session->get('_flash.errors', []));
        $this->assertArrayHasKey('transaction_reference', $this->session->get('_flash.errors', []));
    }

    public function testRegistrationAndPaymentSubmissionIsRateLimitedPerParticipantEventAndIp(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->controller()->store($this->slugged('POST', 'future-craft', [
                'channel' => 'card',
                'transaction_reference' => 'short',
            ]));
            $this->assertSame(302, $response->status());
        }

        $limited = $this->controller()->store($this->slugged('POST', 'future-craft', [
            'channel' => 'mobile',
            'transaction_reference' => 'PRIVATE-REFERENCE-MUST-NOT-ECHO',
        ]));

        $this->assertSame(429, $limited->status());
        $this->assertTrue(str_contains($limited->body(), 'Too many registration attempts'));
        $this->assertFalse(str_contains($limited->body(), 'PRIVATE-REFERENCE-MUST-NOT-ECHO'));
    }

    private function controller(): ParticipantRegistrationController
    {
        $this->assertTrue($this->controller instanceof ParticipantRegistrationController, 'Participant registration controller is missing.');

        return $this->controller;
    }

    private function slugged(string $method, string $slug, array $input = []): Request
    {
        return Request::create($method, '/participant/events/' . $slug . '/register', input: $input)
            ->withRouteParameters(['slug' => $slug]);
    }

    private function idRouted(string $method, int $id, array $input = []): Request
    {
        return Request::create($method, '/participant/registrations/' . $id, input: $input)
            ->withRouteParameters(['id' => (string) $id]);
    }

    private function eventFixture(): array
    {
        return [
            'id' => 31,
            'title' => 'Future Craft',
            'slug' => 'future-craft',
            'status' => 'published',
            'deleted_at' => null,
            'start_date' => '2026-08-22 10:00:00',
            'end_date' => '2026-08-22 12:30:00',
            'registration_deadline' => '2026-08-21 18:00:00',
            'available_seats' => 18,
            'capacity' => 120,
            'ticket_price' => '1250.00',
            'currency' => 'BDT',
            'venue_name' => 'Dhaka Arts Hall',
        ];
    }

    private function registrationFixture(): array
    {
        return [
            'event_id' => 31,
            'user_id' => 7,
            'registration_number' => 'REG-OWNED-4',
            'status' => 'pending',
            'registration_status' => 'pending',
            'amount' => '1250.00',
            'currency' => 'BDT',
            'registered_at' => '2026-08-09 10:00:00',
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'event_title' => 'Future Craft',
            'event_slug' => 'future-craft',
            'event_start_date' => '2026-08-22 10:00:00',
            'registration_deadline' => '2026-08-21 18:00:00',
            'event_status' => 'published',
            'venue_name' => 'Dhaka Arts Hall',
        ];
    }
}
