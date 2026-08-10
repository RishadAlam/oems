<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\ContactRepositoryInterface;
use PDO;
use Throwable;

final class ContactService
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly ?MailOutboxService $outbox = null,
        private readonly ?PDO $connection = null,
    ) {
    }

    public function submit(array $input): array
    {
        if (is_scalar($input['website'] ?? null) && trim((string) $input['website']) !== '') return ['success' => true, 'errors' => [], 'message' => null];
        $attributes = [
            'name' => $this->text($input['name'] ?? null), 'email' => mb_strtolower($this->text($input['email'] ?? null)),
            'subject' => $this->text($input['subject'] ?? null), 'message' => $this->text($input['message'] ?? null),
        ];
        $errors = [];
        if (mb_strlen($attributes['name']) < 2 || mb_strlen($attributes['name']) > 100) $errors['name'][] = 'Enter your name using 2 to 100 characters.';
        if (filter_var($attributes['email'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($attributes['email']) > 190) $errors['email'][] = 'Enter a valid email address.';
        if (mb_strlen($attributes['subject']) < 3 || mb_strlen($attributes['subject']) > 180) $errors['subject'][] = 'Enter a subject using 3 to 180 characters.';
        if (mb_strlen($attributes['message']) < 10 || mb_strlen($attributes['message']) > 4000) $errors['message'][] = 'Enter a message using 10 to 4000 characters.';
        if ($errors !== []) return ['success' => false, 'errors' => $errors, 'message' => null];
        try { $message = $this->contacts->create($attributes); } catch (Throwable) { $message = null; }
        return $message === null ? ['success' => false, 'errors' => ['contact' => ['Your message could not be saved.']], 'message' => null] : ['success' => true, 'errors' => [], 'message' => $message];
    }

    public function index(array $query): array
    {
        $status = is_scalar($query['status'] ?? null) ? trim((string) $query['status']) : '';
        $search = is_scalar($query['search'] ?? null) ? trim((string) $query['search']) : '';
        $pageRaw = $query['page'] ?? 1;
        if ($pageRaw === null || $pageRaw === '') $pageRaw = 1;
        $page = filter_var($pageRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $valid = in_array($status, ['', 'new', 'read', 'replied', 'archived'], true)
            && is_scalar($query['search'] ?? '') && mb_strlen($search) <= 100 && $page !== false;
        if (!$valid) return ['messages' => [], 'total' => 0, 'filters' => ['status' => '', 'search' => ''], 'page' => 1, 'limit' => 25, 'valid' => false];
        $page = (int) $page;
        $filters = ['status' => $status, 'search' => mb_substr($search, 0, 100)]; $limit = 25;
        return ['messages' => $this->contacts->forAdmin($filters, $limit, ($page - 1) * $limit), 'total' => $this->contacts->countForAdmin($filters), 'filters' => $filters, 'page' => $page, 'limit' => $limit, 'valid' => true];
    }

    public function setStatus(int $administratorId, int $id, string $from, string $to): array
    {
        if (!in_array($from, ['new', 'read', 'replied', 'archived'], true) || !in_array($to, ['new', 'read', 'archived'], true) || $from === $to) return ['success' => false, 'code' => 'invalid'];
        try { return $this->contacts->setStatus($id, $from, $to, $administratorId) ? ['success' => true] : ['success' => false, 'code' => 'conflict']; }
        catch (Throwable) { return ['success' => false, 'code' => 'failure']; }
    }

    public function reply(int $administratorId, int $id, mixed $reply): array
    {
        $reply = $this->text($reply); if (mb_strlen($reply) < 2 || mb_strlen($reply) > 4000) return ['success' => false, 'errors' => ['reply' => ['Enter a reply using 2 to 4000 characters.']]];
        if ($this->outbox === null || $this->connection === null) return ['success' => false, 'errors' => ['reply' => ['Email delivery is unavailable.']]];
        $owns = !$this->connection->inTransaction();
        try {
            if ($owns) $this->connection->beginTransaction(); $contact = $this->contacts->findForAdmin($id, true);
            if ($contact === null) { if ($owns) $this->connection->rollBack(); return ['success' => false, 'code' => 'not_found', 'errors' => []]; }
            if (($contact['status'] ?? null) === 'replied') { if ($owns) $this->connection->commit(); return ['success' => true, 'idempotent' => true, 'errors' => []]; }
            if (($contact['status'] ?? null) === 'archived') { if ($owns) $this->connection->rollBack(); return ['success' => false, 'code' => 'conflict', 'errors' => []]; }
            $queued = $this->outbox->enqueue('contact_reply', (string) $contact['email'], ['contact_id' => $id, 'name' => (string) $contact['name'], 'reply' => $reply], 'contact-reply:' . $id . ':' . hash('sha256', $reply));
            if (!($queued['ok'] ?? false) || !$this->contacts->markReplied($id, $administratorId)) { if ($owns) $this->connection->rollBack(); return ['success' => false, 'errors' => ['reply' => ['The reply could not be queued.']]]; }
            if ($owns) $this->connection->commit(); return ['success' => true, 'idempotent' => false, 'errors' => []];
        } catch (Throwable) { if ($owns && $this->connection->inTransaction()) $this->connection->rollBack(); return ['success' => false, 'errors' => ['reply' => ['The reply could not be queued.']]]; }
    }

    private function text(mixed $value): string { return is_scalar($value) ? trim((string) $value) : ''; }
}
