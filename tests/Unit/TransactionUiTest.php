<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\Core\View;
use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class TransactionUiTest extends TestCase
{
    public function testRegistrationDetailCommunicatesTheTransactionAsSemanticSteps(): void
    {
        $html = $this->render('participant/registrations/show', [
            'registration' => $this->registration(),
            'errors' => [],
        ]);

        $this->assertTrue(str_contains($html, 'class="transaction-steps"'));
        $this->assertTrue(str_contains($html, 'class="transaction-step transaction-step--complete"'));
        $this->assertTrue(str_contains($html, 'aria-current="step"'));
        $this->assertTrue(str_contains($html, 'class="money-summary dashboard-panel"'));
    }

    public function testRegistrationTimelineUsesParticipantFacingTicketStatusCopy(): void
    {
        $registration = $this->registration();
        $registration['ticket']['ticket_status'] = 'used';
        $html = $this->render('participant/registrations/show', [
            'registration' => $registration,
            'errors' => [],
        ]);

        $this->assertTrue(str_contains($html, '>Checked in</span>'));
        $this->assertFalse(str_contains($html, '>Used</span>'));
    }

    public function testTerminalRegistrationTimelineDoesNotClaimACurrentOrCompletedFulfillmentStep(): void
    {
        $rejected = $this->registration();
        $rejected['registration_status'] = 'cancelled';
        $rejected['payment_status'] = 'failed';
        $rejected['ticket'] = null;
        $rejectedHtml = $this->render('participant/registrations/show', [
            'registration' => $rejected,
            'errors' => [],
        ]);

        $refunded = $this->registration();
        $refunded['registration_status'] = 'cancelled';
        $refunded['payment_status'] = 'refunded';
        $refunded['ticket']['ticket_status'] = 'cancelled';
        $refundedHtml = $this->render('participant/registrations/show', [
            'registration' => $refunded,
            'errors' => [],
        ]);

        $this->assertTrue(str_contains($rejectedHtml, 'transaction-step--failed'));
        $this->assertTrue(str_contains($rejectedHtml, '>Payment rejected</span>'));
        $this->assertTrue(str_contains($rejectedHtml, 'transaction-step--unavailable'));
        $this->assertFalse(str_contains($rejectedHtml, 'aria-current="step"'));
        $this->assertTrue(str_contains($refundedHtml, 'transaction-step--terminal'));
        $this->assertTrue(str_contains($refundedHtml, '>Refunded</span>'));
        $this->assertTrue(str_contains($refundedHtml, 'transaction-step--failed'));
        $this->assertTrue(str_contains($refundedHtml, '>Cancelled</span>'));
        $this->assertFalse(str_contains($refundedHtml, 'aria-current="step"'));
    }

    public function testEventCancellationOutranksTheDerivedFailedPaymentStateInDetailAndTimelineCopy(): void
    {
        $cancelled = $this->registration();
        $cancelled['registration_status'] = 'cancelled';
        $cancelled['event_status'] = 'cancelled';
        $cancelled['cancellation_reason'] = 'Event cancelled';
        $cancelled['payment_status'] = 'failed';
        $cancelled['ticket']['ticket_status'] = 'cancelled';

        $html = $this->render('participant/registrations/show', [
            'registration' => $cancelled,
            'errors' => [],
        ]);

        $this->assertTrue(str_contains($html, 'The event was cancelled.'));
        $this->assertTrue(str_contains($html, '<strong>Payment</strong><span>Event cancelled</span>'));
        $this->assertTrue(str_contains($html, '<dt>Payment</dt><dd>Event cancelled</dd>'));
        $this->assertFalse(str_contains($html, 'payment reference was rejected'));
        $this->assertFalse(str_contains($html, '>Payment rejected</span>'));
    }

    public function testCancellationFieldAlwaysAssociatesItsHelpAndConditionalError(): void
    {
        $registration = $this->registration();
        $registration['can_cancel'] = true;
        $html = $this->render('participant/registrations/show', [
            'registration' => $registration,
            'errors' => ['reason' => ['Tell us why you need to cancel.']],
        ]);

        $this->assertTrue(str_contains($html, 'aria-describedby="reason-help reason-error"'));
        $this->assertTrue(str_contains($html, 'id="reason-help"'));
        $this->assertTrue(str_contains($html, 'id="reason-error"'));
    }

    public function testTicketDetailUsesAStatusAwareTicketAndQrFrame(): void
    {
        $html = $this->render('participant/tickets/show', [
            'ticket' => [
                'id' => 18,
                'registration_id' => 12,
                'ticket_number' => 'OEMS-TKT-018',
                'ticket_status' => 'used',
                'event_title' => 'Completed Product Forum',
                'event_slug' => 'completed-product-forum',
                'event_start_display' => 'August 8, 2026 at 10:00 AM',
                'registration_number' => 'OEMS-REG-012',
                'issued_display' => 'August 1, 2026',
                'has_qr_artifact' => true,
                'has_pdf_artifact' => true,
            ],
        ]);

        $this->assertTrue(str_contains($html, 'class="ticket-panel'));
        $this->assertTrue(str_contains($html, 'class="qr-frame'));
        $this->assertTrue(str_contains($html, 'class="ticket-panel dashboard-panel min-w-0'));
        $this->assertTrue(str_contains($html, 'class="text-link break-all"'));
        $this->assertTrue(str_contains($html, '>Checked in<'));
        $this->assertTrue(str_contains($html, 'aria-label="Ticket status: Checked in"'));
    }

    public function testPaymentQueueKeepsExplicitLabelsWhenItsTableBecomesCards(): void
    {
        $html = $this->render('admin/payments/index', [
            'filters' => ['search' => '', 'status' => 'pending'],
            'statuses' => ['pending', 'paid', 'failed', 'refunded'],
            'payments' => [[
                'id' => 4,
                'payment_status' => 'pending',
                'participant_name' => 'Demo Participant',
                'participant_email' => 'demo@example.test',
                'event_title' => 'Demo Forum',
                'organizer_name' => 'Example Events',
                'currency' => 'BDT',
                'amount' => '1200.00',
                'payment_method_name' => 'Manual payment',
                'payment_channel' => 'bank_transfer',
                'transaction_reference' => 'DEMO-REFERENCE-04',
                'created_at' => '2026-08-08 10:00:00',
            ]],
            'perPage' => 20,
            'total' => 1,
            'page' => 1,
            'lastPage' => 1,
        ]);

        $this->assertTrue(str_contains($html, 'class="operations-table'));
        foreach (['Participant', 'Event', 'Amount', 'Method', 'Reference', 'Status', 'Action'] as $label) {
            $this->assertTrue(str_contains($html, 'data-label="' . $label . '"'));
        }
    }

    public function testRatingAndNotificationComponentsExposeStateWithoutColorAlone(): void
    {
        $rating = $this->render('participant/reviews/form', [
            'review' => null,
            'event' => ['id' => 7, 'title' => 'Completed Product Forum'],
            'errors' => [],
            'old' => [],
        ]);
        $notifications = $this->render('participant/notifications/index', [
            'notifications' => [
                'items' => [[
                    'id' => 9,
                    'title' => 'Ticket ready',
                    'message' => 'Your ticket is ready for the event.',
                    'action_url' => '/participant/tickets/18',
                    'read_at' => null,
                    'created_at' => '2026-08-08 10:00:00',
                ]],
                'pagination' => ['page' => 1, 'last_page' => 1],
            ],
            'unreadCount' => 1,
        ]);

        $this->assertTrue(str_contains($rating, 'class="rating-control"'));
        $this->assertTrue(str_contains($rating, 'aria-label="1 out of 5, poor"'));
        $this->assertTrue(str_contains($rating, 'aria-label="5 out of 5, excellent"'));
        $this->assertTrue(str_contains($notifications, 'class="notification-row notification-row--unread"'));
        $this->assertTrue(str_contains($notifications, 'aria-label="Unread notification"'));
        $this->assertTrue(str_contains($notifications, 'data-notification-row'));
        $this->assertTrue(str_contains($notifications, 'data-notification-status-form'));

        $javascript = file_get_contents(base_path('public/assets/js/app.js'));
        $this->assertTrue(is_string($javascript));
        $this->assertTrue(str_contains($javascript, "'[data-notification-status-form]'"));
        $this->assertTrue(str_contains($javascript, "setAttribute('aria-busy', 'true')"));
    }

    public function testTransactionComponentsUseThemeTokensInBothThemeStates(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));

        if (!is_string($css)) {
            throw new RuntimeException('Unable to read the source stylesheet.');
        }

        $this->assertTrue(str_contains($css, '--transaction-surface:'));
        $this->assertSame(2, substr_count($css, '--transaction-surface:'));
        $this->assertTrue(str_contains($css, '.transaction-steps'));
        $this->assertTrue(str_contains($css, '.money-summary'));
        $this->assertTrue(str_contains($css, '.ticket-panel'));
        $this->assertTrue(str_contains($css, '.qr-frame'));
        $this->assertTrue(str_contains($css, '.notification-row'));
        $this->assertTrue(str_contains($css, ".operations-table {\n        min-width: 0;"));
    }

    public function testTransactionStatusesAndModerationQueueUseSharedSemanticComponents(): void
    {
        $css = file_get_contents(base_path('resources/css/app.css'));
        $queue = $this->render('admin/reviews/index', [
            'reviews' => [[
                'id' => 4,
                'status' => 'pending',
                'event_title' => 'Completed Product Forum',
                'participant_name' => 'Demo Participant',
                'rating' => 5,
                'review' => 'Clear sessions and useful discussion.',
            ]],
            'status' => 'pending',
        ]);

        $this->assertTrue(is_string($css));
        foreach (['paid', 'valid', 'used', 'failed', 'refunded', 'hidden'] as $status) {
            $this->assertTrue(str_contains($css, '.status-chip--' . $status));
        }
        $this->assertTrue(str_contains($queue, 'class="queue-list"'));
        $this->assertTrue(str_contains($queue, 'class="queue-item'));
    }

    public function testVisibleTransactionUiHasNoUnavailableMilestoneCopyOrDashGlyphs(): void
    {
        $rendered = [
            $this->render('participant/registrations/show', ['registration' => $this->registration(), 'errors' => []]),
            $this->render('participant/tickets/show', ['ticket' => [
                'id' => 18, 'registration_id' => 12, 'ticket_number' => 'OEMS-TKT-018', 'ticket_status' => 'valid',
                'event_title' => 'Product Forum', 'event_slug' => 'product-forum', 'event_start_display' => 'August 8, 2026',
                'registration_number' => 'OEMS-REG-012', 'issued_display' => 'August 1, 2026',
            ]]),
        ];
        $visible = html_entity_decode(strip_tags(implode(' ', $rendered)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertFalse(str_contains($visible, 'Week 3'));
        $this->assertFalse(str_contains($visible, 'coming soon'));
        $this->assertFalse(str_contains($visible, 'later milestone'));
        $this->assertFalse(str_contains($visible, '—'));
        $this->assertFalse(str_contains($visible, '–'));
    }

    private function registration(): array
    {
        return [
            'id' => 12,
            'registration_number' => 'OEMS-REG-012',
            'registration_status' => 'confirmed',
            'payment_status' => 'paid',
            'event_title' => 'Product Forum',
            'registered_display' => 'August 1, 2026',
            'event_start_display' => 'August 8, 2026 at 10:00 AM',
            'venue_name' => 'Demo Hall',
            'amount_display' => '1,200.00',
            'currency' => 'BDT',
            'ticket' => ['id' => 18, 'ticket_number' => 'OEMS-TKT-018', 'ticket_status' => 'valid'],
            'can_cancel' => false,
            'cancellation_state' => ['allowed' => false, 'reason' => null],
        ];
    }

    private function render(string $template, array $data): string
    {
        $_SERVER['REQUEST_URI'] = '/' . $template;

        return (new View(base_path('app/Views')))->render($template, array_merge([
            'app' => ['name' => 'OEMS'],
            'csrfToken' => 'test-token',
            'currentUser' => [
                'name' => 'Test User',
                'email' => 'test@example.test',
                'role_name' => str_starts_with($template, 'admin/') ? 'Super Admin' : 'Participant',
                'role_slug' => str_starts_with($template, 'admin/') ? 'super-admin' : 'participant',
            ],
            'flash' => [],
            'pageTitle' => 'Transaction test',
            'old' => [],
            'errors' => [],
            'unreadNotifications' => 0,
        ], $data), 'dashboard');
    }
}
