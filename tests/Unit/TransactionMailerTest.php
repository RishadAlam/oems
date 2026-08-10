<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\TransactionMailer;
use OEMS\App\Services\MailOutboxService;
use OEMS\Core\Config;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeEmailLogRepository;
use OEMS\Tests\Support\FakeMailTransport;
use OEMS\Tests\Support\FakeMailOutboxRepository;
use OEMS\Tests\Support\TestCase;
use RuntimeException;

final class TransactionMailerTest extends TestCase
{
    public function testLifecycleMessagesUseSafeInternalLinksAndBoundedContent(): void
    {
        $transport = new FakeMailTransport('<message-id>');
        $logs = new FakeEmailLogRepository();
        $mailer = $this->mailer($transport, $logs);
        $participant = [
            'id' => 7,
            'name' => '<script>Amina</script>',
            'email' => 'amina@example.test',
        ];
        $registration = [
            'id' => 41,
            'registration_number' => str_repeat('R', 400),
            'event_title' => str_repeat('Event ', 100),
        ];
        $ticket = ['id' => 91, 'raw_token' => 'RAW-SECRET', 'qr_payload_hash' => 'DIGEST-SECRET'];

        $this->assertTrue($mailer->sendConfirmation($participant, $registration));
        $this->assertTrue($mailer->sendPending($participant, $registration));
        $this->assertTrue($mailer->sendPaid($participant, $registration));
        $this->assertTrue($mailer->sendRejected($participant, $registration));
        $this->assertTrue($mailer->sendCancelled($participant, $registration));
        $this->assertTrue($mailer->sendTicket($participant, $registration, $ticket));
        $this->assertSame(6, count($transport->messages));
        $this->assertSame(6, count($logs->records));
        $this->assertSame([
            'registration_confirmation',
            'payment_pending',
            'payment_paid',
            'payment_rejected',
            'registration_cancelled',
            'ticket_issued',
        ], array_column($logs->records, 'template'));

        foreach ($transport->messages as $message) {
            $combined = $message->subject . $message->htmlBody . $message->textBody;
            $this->assertFalse(str_contains($combined, 'RAW-SECRET'));
            $this->assertFalse(str_contains($combined, 'DIGEST-SECRET'));
            $this->assertFalse(str_contains($message->htmlBody, '<script>'));
            $this->assertTrue(strlen($message->subject) <= 190);
        }

        $this->assertTrue(str_contains($transport->messages[0]->htmlBody, 'http://oems.test/participant/registrations/41'));
        $this->assertTrue(str_contains($transport->messages[5]->htmlBody, 'http://oems.test/participant/tickets/91'));
    }

    public function testDeliveryFailureStoresGenericSafeOutcomeAndNeverThrows(): void
    {
        $transport = new FakeMailTransport();
        $transport->failure = new RuntimeException('SMTP credential=secret transaction=MANUAL-SECRET');
        $logs = new FakeEmailLogRepository();
        $path = sys_get_temp_dir() . '/oems-transaction-mail-' . bin2hex(random_bytes(5)) . '.log';
        $mailer = new TransactionMailer(
            $transport,
            $logs,
            new Config(['url' => 'http://oems.test']),
            new Logger($path),
        );

        $sent = $mailer->sendPending(
            ['id' => 7, 'name' => 'Amina', 'email' => 'amina@example.test'],
            ['id' => 41, 'event_title' => 'Event'],
        );

        $this->assertFalse($sent);
        $this->assertSame('failed', $logs->records[0]['status']);
        $this->assertSame('Email delivery failed.', $logs->records[0]['error_message']);
        $this->assertFalse(str_contains((string) $logs->records[0]['error_message'], 'secret'));

        if (is_file($path)) {
            $contents = file_get_contents($path) ?: '';
            $this->assertFalse(str_contains($contents, 'MANUAL-SECRET'));
            unlink($path);
        }
    }

    public function testConfiguredOutboxQueuesLifecycleMessagesIdempotentlyWithoutSynchronousSmtp(): void
    {
        $transport = new FakeMailTransport('<must-not-send>');
        $logs = new FakeEmailLogRepository();
        $outbox = new FakeMailOutboxRepository();
        $mailer = new TransactionMailer(
            $transport,
            $logs,
            new Config(['url' => 'https://events.example.test']),
            null,
            new MailOutboxService($outbox),
        );
        $participant = ['id' => 7, 'name' => 'Amina', 'email' => 'amina@example.test'];
        $registration = ['id' => 41, 'event_title' => 'Dhaka Product Night'];

        $this->assertTrue($mailer->sendPending($participant, $registration));
        $this->assertTrue($mailer->sendPending($participant, $registration));
        $this->assertTrue($mailer->sendTicket($participant, $registration, ['id' => 91]));

        $this->assertSame([], $transport->messages);
        $this->assertSame([], $logs->records);
        $this->assertSame(2, count($outbox->jobs));
        $this->assertSame(['payment_pending', 'ticket_issued'], array_column($outbox->jobs, 'template'));
        $this->assertSame('/participant/tickets/91', $outbox->jobs[1]['payload']['action_url']);
    }

    private function mailer(
        FakeMailTransport $transport,
        FakeEmailLogRepository $logs,
    ): TransactionMailer {
        return new TransactionMailer(
            $transport,
            $logs,
            new Config(['url' => 'http://oems.test']),
        );
    }
}
