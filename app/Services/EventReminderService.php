<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use DateTimeZone;
use OEMS\App\Contracts\RegistrationRepositoryInterface;

final class EventReminderService
{
    public function __construct(
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly MailOutboxService $outbox,
        private readonly string $timezone,
    ) {
    }

    public function queueDue(DateTimeImmutable $now, int $limit): array
    {
        $limit = min(100, max(1, $limit));
        $zone = new DateTimeZone($this->timezone);
        $from = $now->setTimezone($zone);
        $to = $from->modify('+24 hours');
        $queued = 0;
        $examined = 0;
        $offset = 0;

        while ($queued < $limit) {
            $rows = $this->registrations->dueReminderRecipients($from, $to, 100, $offset);
            if ($rows === []) {
                break;
            }
            $offset += count($rows);
            foreach ($rows as $row) {
                $examined++;
                if (!$this->eligible($row, $from, $to)) {
                    continue;
                }
                $registrationId = (int) $row['registration_id'];
                $eventId = (int) $row['event_id'];
                $result = $this->outbox->enqueue(
                    'event_reminder',
                    (string) $row['recipient_email'],
                    [
                        'user_id' => (int) $row['user_id'],
                        'event_id' => $eventId,
                        'registration_id' => $registrationId,
                        'event_title' => (string) $row['event_title'],
                        'starts_at' => (new DateTimeImmutable((string) $row['start_date'], $zone))->format(DATE_ATOM),
                        'calendar_url' => '/participant/registrations/' . $registrationId . '/calendar.ics',
                    ],
                    "reminder:event:{$eventId}:registration:{$registrationId}:24h",
                    $from,
                );
                if (($result['ok'] ?? false) && ($result['created'] ?? false)) {
                    $queued++;
                    if ($queued >= $limit) {
                        break;
                    }
                }
            }
            if (count($rows) < 100) {
                break;
            }
        }

        return [
            'queued' => $queued,
            'examined' => $examined,
            'limit' => $limit,
            'window_ends_at' => $to->format('Y-m-d H:i:s'),
        ];
    }

    private function eligible(array $row, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        if (($row['user_status'] ?? null) !== 'active'
            || empty($row['email_verified_at'])
            || !empty($row['user_deleted_at'])
            || ($row['registration_status'] ?? null) !== 'confirmed'
            || ($row['event_status'] ?? null) !== 'published'
            || !empty($row['event_deleted_at'])
            || filter_var($row['recipient_email'] ?? null, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        try {
            $start = new DateTimeImmutable((string) ($row['start_date'] ?? ''), new DateTimeZone($this->timezone));
        } catch (\Throwable) {
            return false;
        }

        return $start > $from && $start <= $to;
    }
}
