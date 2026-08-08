<?php

declare(strict_types=1);

namespace OEMS\App\Controllers;

use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\Core\Auth;
use OEMS\Core\Config;
use OEMS\Core\Controller;
use OEMS\Core\Request;
use OEMS\Core\Response;
use OEMS\Core\Security;
use OEMS\Core\Session;
use OEMS\Core\View;
use RuntimeException;

final class OrganizerParticipantController extends Controller
{
    private const FILTERS = [
        'registration_status',
        'payment_status',
        'ticket_status',
        'attendance_status',
        'search',
    ];

    public function __construct(
        View $view,
        Session $session,
        Security $security,
        Auth $auth,
        Config $config,
        private readonly RegistrationRepositoryInterface $registrations,
    ) {
        parent::__construct($view, $session, $security, $auth, $config);
    }

    public function index(Request $request): Response
    {
        $context = $this->ownedContext($request);

        if ($context === null) {
            return Response::text('Not Found', 404);
        }

        [$userId, $eventId, $event] = $context;
        $filters = $this->filters($request);
        $page = $this->positiveInt($request->query('page')) ?? 1;
        $perPage = 25;
        $total = $this->registrations->countForOrganizerEvent($userId, $eventId, $filters);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $participants = $this->registrations->forOrganizerEvent(
            $userId,
            $eventId,
            $filters,
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->render('organizer/participants/index', [
            'pageTitle' => 'Participants',
            'event' => $event,
            'participants' => $participants,
            'filters' => $filters,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ], 'dashboard');
    }

    public function export(Request $request): Response
    {
        $context = $this->ownedContext($request);

        if ($context === null) {
            return Response::text('Not Found', 404);
        }

        [$userId, $eventId, $event] = $context;
        $filters = $this->filters($request);
        $filename = $this->safeFilename((string) ($event['event_slug'] ?? ''), $eventId);

        return Response::stream(function (callable $emit) use ($userId, $eventId, $filters): void {
            $emit("\xEF\xBB\xBF");
            $rowStream = fopen('php://temp/maxmemory:65536', 'w+');

            if ($rowStream === false) {
                throw new RuntimeException('The export stream could not be opened.');
            }

            $emitRow = static function (array $cells) use ($rowStream, $emit): void {
                if (!ftruncate($rowStream, 0) || !rewind($rowStream)) {
                    throw new RuntimeException('The export row stream could not be reset.');
                }

                if (fputcsv($rowStream, $cells, ',', '"', '') === false || !rewind($rowStream)) {
                    throw new RuntimeException('The export row could not be encoded.');
                }

                while (!feof($rowStream)) {
                    $chunk = fread($rowStream, 8192);

                    if ($chunk === false) {
                        throw new RuntimeException('The export row could not be read.');
                    }

                    if ($chunk === '') {
                        break;
                    }

                    $emit($chunk);
                }
            };

            try {
                $emitRow([
                    'Participant name',
                    'Participant email',
                    'Registration number',
                    'Registration status',
                    'Payment status',
                    'Ticket number',
                    'Ticket status',
                    'Attendance status',
                    'Checked in at',
                    'Registered at',
                ]);

                for ($offset = 0; ; $offset += 100) {
                    $participants = $this->registrations->forOrganizerEvent(
                        $userId,
                        $eventId,
                        $filters,
                        100,
                        $offset,
                    );

                    foreach ($participants as $row) {
                        $emitRow(array_map($this->csvCell(...), [
                            $row['participant_name'] ?? '',
                            $row['participant_email'] ?? '',
                            $row['registration_number'] ?? '',
                            $row['registration_status'] ?? '',
                            $row['payment_status'] ?? 'none',
                            $row['ticket_number'] ?? '',
                            $row['ticket_status'] ?? 'none',
                            $row['attendance_status'] ?? 'not_checked_in',
                            $row['scanned_at'] ?? '',
                            $row['registered_at'] ?? '',
                        ]));
                    }

                    if (count($participants) < 100) {
                        break;
                    }
                }
            } finally {
                fclose($rowStream);
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '-participants.csv"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ownedContext(Request $request): ?array
    {
        $userId = $this->auth->id();
        $eventId = $this->positiveInt($request->route('id'));

        if ($userId === null || $eventId === null) {
            return null;
        }

        $event = $this->registrations->findOrganizerEvent($userId, $eventId);

        return $event === null ? null : [$userId, $eventId, $event];
    }

    private function filters(Request $request): array
    {
        $filters = [];

        foreach (self::FILTERS as $key) {
            $value = $request->query($key);
            if (is_scalar($value)) {
                $filters[$key] = trim((string) $value);
            }
        }

        return $filters;
    }

    private function positiveInt(mixed $value): ?int
    {
        return (is_int($value) || is_string($value))
            && ctype_digit((string) $value)
            && (int) $value > 0
                ? (int) $value
                : null;
    }

    private function csvCell(mixed $value): string
    {
        $cell = is_scalar($value) ? (string) $value : '';
        $dangerous = preg_match('/\A[=+\-@\t\r]/u', $cell) === 1;
        $cell = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $cell) ?? '';
        $cell = preg_replace('/ {2,}/u', ' ', $cell) ?? '';

        if ($dangerous || preg_match('/\A[=+\-@]/u', $cell) === 1) {
            $cell = "'" . $cell;
        }

        return $cell;
    }

    private function safeFilename(string $slug, int $eventId): string
    {
        $slug = preg_split('/[\x00-\x1F\x7F]/', $slug, 2)[0] ?? '';
        $filename = strtolower($slug);
        $filename = preg_replace('/[^a-z0-9-]+/', '-', $filename) ?? '';
        $filename = trim(preg_replace('/-+/', '-', $filename) ?? '', '-');
        $filename = substr($filename, 0, 80);

        return $filename !== '' ? $filename : 'event-' . $eventId;
    }
}
