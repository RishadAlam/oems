<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use InvalidArgumentException;
use OEMS\App\Services\QueuedMailTemplateService;
use OEMS\Core\Config;
use OEMS\Tests\Support\TestCase;

final class QueuedMailTemplateServiceTest extends TestCase
{
    private QueuedMailTemplateService $templates;

    protected function setUp(): void
    {
        $this->templates = new QueuedMailTemplateService(new Config(['url' => 'https://events.example.test']));
    }

    public function testRendersEveryClosedTemplateWithSafeAbsoluteInternalLinks(): void
    {
        $fixtures = [
            'registration_confirmation' => $this->transactionPayload('/participant/registrations/7'),
            'payment_pending' => $this->transactionPayload('/participant/registrations/7'),
            'payment_paid' => $this->transactionPayload('/participant/registrations/7'),
            'payment_rejected' => $this->transactionPayload('/participant/registrations/7'),
            'registration_cancelled' => $this->transactionPayload('/participant/registrations/7'),
            'ticket_issued' => $this->transactionPayload('/participant/tickets/8'),
            'event_reminder' => [
                'user_id' => 5,
                'event_id' => 6,
                'registration_id' => 7,
                'event_title' => 'Dhaka Product Night',
                'starts_at' => '2026-08-11T09:00:00+06:00',
                'calendar_url' => '/participant/registrations/7/calendar.ics',
            ],
            'event_announcement' => [
                'event_id' => 6,
                'recipient_name' => 'Amina',
                'event_title' => 'Dhaka Product Night',
                'subject' => 'Room update',
                'message' => 'Doors open at 8:30.',
                'action_url' => '/participant/registrations/7',
            ],
            'contact_reply' => ['contact_id' => 9, 'name' => 'Amina', 'reply' => 'Thanks for contacting OEMS.'],
            'newsletter_confirmation' => ['subscription_id' => 10, 'confirmation_url' => '/newsletter/confirm/token'],
            'newsletter_campaign' => [
                'campaign_id' => 11,
                'subject' => 'Events this month',
                'message' => 'Three new events are open.',
                'unsubscribe_url' => '/newsletter/unsubscribe/token',
            ],
            'newsletter_unsubscribe' => ['subscription_id' => 10],
        ];

        foreach ($fixtures as $template => $payload) {
            $rendered = $this->templates->render($template, $payload);
            $this->assertTrue(trim($rendered['subject']) !== '');
            $this->assertTrue(trim($rendered['text']) !== '');
            $this->assertTrue(trim((string) $rendered['html']) !== '');
            $this->assertFalse(str_contains($rendered['html'], '<script'));
            if (str_contains(json_encode($payload), '/participant/') || str_contains(json_encode($payload), '/newsletter/')) {
                $this->assertTrue(str_contains($rendered['text'], 'https://events.example.test/'));
            }
        }
    }

    public function testEscapesHostileDisplayValuesAndRejectsUnknownOrMalformedJobs(): void
    {
        $rendered = $this->templates->render('contact_reply', [
            'contact_id' => 9,
            'name' => '<script>Bad</script>',
            'reply' => '<img src=x onerror=alert(1)>',
        ]);

        $this->assertFalse(str_contains($rendered['html'], '<script>'));
        $this->assertFalse(str_contains($rendered['html'], '<img'));
        $this->assertTrue(str_contains($rendered['text'], 'Bad'));

        $this->assertInvalid(fn (): array => $this->templates->render('raw_php', ['body' => '<?php']));
        $this->assertInvalid(fn (): array => $this->templates->render('event_reminder', ['event_id' => 6]));
    }

    private function transactionPayload(string $action): array
    {
        return [
            'user_id' => 5,
            'participant_name' => 'Amina',
            'registration_id' => 7,
            'event_title' => 'Dhaka Product Night',
            'action_url' => $action,
        ];
    }

    private function assertInvalid(callable $callback): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
            return;
        }

        $this->assertTrue(false, 'Expected an invalid queued template payload to be rejected.');
    }
}
