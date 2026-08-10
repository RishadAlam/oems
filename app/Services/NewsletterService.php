<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use DateTimeImmutable;
use OEMS\App\Contracts\NewsletterRepositoryInterface;
use PDO;
use Throwable;

final class NewsletterService
{
    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletter,
        private readonly MailOutboxService $outbox,
        private readonly ?PDO $connection = null,
    ) {
    }

    public function subscribe(mixed $email): array
    {
        $email = is_scalar($email) ? mb_strtolower(trim((string) $email)) : '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) return ['success' => false, 'errors' => ['email' => ['Enter a valid email address.']]];
        $confirmationToken = bin2hex(random_bytes(32)); $unsubscribeToken = bin2hex(random_bytes(32)); $now = new DateTimeImmutable();
        $owns = $this->connection !== null && !$this->connection->inTransaction();
        try {
            if ($owns) $this->connection?->beginTransaction();
            $subscriber = $this->newsletter->savePending($email, hash('sha256', $confirmationToken), hash('sha256', $unsubscribeToken), $now->modify('+24 hours'), $now);
            if ($subscriber === null) throw new \RuntimeException('Subscription could not be persisted.');
            if (($subscriber['status'] ?? null) === 'subscribed') { if ($owns) $this->connection?->commit(); return ['success' => true, 'errors' => []]; }
            $queued = $this->outbox->enqueue('newsletter_confirmation', $email, ['subscription_id' => (int) $subscriber['id'], 'confirmation_url' => '/newsletter/confirm/' . $confirmationToken], 'newsletter-confirmation:' . (int) $subscriber['id'] . ':' . hash('sha256', $confirmationToken));
            if (!($queued['ok'] ?? false)) throw new \RuntimeException('Confirmation could not be queued.');
            if ($owns) $this->connection?->commit(); return ['success' => true, 'errors' => []];
        } catch (Throwable) { if ($owns && $this->connection?->inTransaction()) $this->connection->rollBack(); return ['success' => false, 'errors' => ['newsletter' => ['Subscription could not be saved.']]]; }
    }

    public function confirm(mixed $token): array
    {
        $token = $this->token($token); if ($token === null) return ['success' => false];
        $owns = $this->connection !== null && !$this->connection->inTransaction();
        try {
            if ($owns) $this->connection?->beginTransaction();
            $success = $this->newsletter->confirm(hash('sha256', $token), new DateTimeImmutable()) !== null;
            if ($owns) $this->connection?->commit();
            return ['success' => $success];
        } catch (Throwable) { if ($owns && $this->connection?->inTransaction()) $this->connection->rollBack(); return ['success' => false]; }
    }

    public function unsubscribe(mixed $token): array
    {
        $token = $this->token($token); if ($token === null) return ['success' => false];
        try { return ['success' => $this->newsletter->unsubscribe(hash('sha256', $token), new DateTimeImmutable()) !== null]; } catch (Throwable) { return ['success' => false]; }
    }

    public function createCampaign(int $administratorId, array $input): array
    {
        $subject = is_scalar($input['subject'] ?? null) ? trim((string) $input['subject']) : ''; $message = is_scalar($input['message'] ?? null) ? trim((string) $input['message']) : '';
        $errors = []; if (mb_strlen($subject) < 3 || mb_strlen($subject) > 180) $errors['subject'][] = 'Enter a subject using 3 to 180 characters.'; if (mb_strlen($message) < 10 || mb_strlen($message) > 4000) $errors['message'][] = 'Enter a message using 10 to 4000 characters.';
        if ($errors !== []) return ['success' => false, 'errors' => $errors];
        try { $campaign = $this->newsletter->createCampaign($administratorId, ['subject' => $subject, 'message' => $message, 'request_key' => hash('sha256', bin2hex(random_bytes(32))), 'scheduled_at' => null]); }
        catch (Throwable) { $campaign = null; }
        return $campaign === null ? ['success' => false, 'errors' => ['campaign' => ['Campaign could not be created.']]] : ['success' => true, 'errors' => [], 'campaign' => $campaign];
    }

    public function queueCampaign(int $administratorId, int $campaignId): array
    {
        $owns = $this->connection !== null && !$this->connection->inTransaction();
        try {
            if ($owns) $this->connection?->beginTransaction();
            $campaign = $this->newsletter->findCampaign($campaignId, true);
            if ($campaign === null) { if ($owns) $this->connection?->rollBack(); return ['success' => false, 'code' => 'not_found']; }
            if (($campaign['status'] ?? null) === 'queued') { if ($owns) $this->connection?->commit(); return ['success' => true, 'queued_count' => (int) $campaign['queued_count'], 'idempotent' => true]; }
            if (($campaign['status'] ?? null) !== 'draft') { if ($owns) $this->connection?->rollBack(); return ['success' => false, 'code' => 'conflict']; }
            $count = 0; $offset = 0;
            do {
                $rows = $this->newsletter->confirmedSubscribers(200, $offset);
                foreach ($rows as $subscriber) {
                    $token = bin2hex(random_bytes(32));
                    if (!$this->newsletter->rotateUnsubscribeToken((int) $subscriber['id'], hash('sha256', $token))) throw new \RuntimeException('Unsubscribe token could not be rotated.');
                    $queued = $this->outbox->enqueue('newsletter_campaign', (string) $subscriber['email'], ['campaign_id' => $campaignId, 'subject' => (string) $campaign['subject'], 'message' => (string) $campaign['message'], 'unsubscribe_url' => '/newsletter/unsubscribe/' . $token], 'newsletter-campaign:' . $campaignId . ':subscriber:' . (int) $subscriber['id']);
                    if (!($queued['ok'] ?? false)) throw new \RuntimeException('Campaign delivery could not be queued.');
                    $count++;
                }
                $offset += count($rows);
            } while (count($rows) === 200);
            if ($count === 0) { if ($owns) $this->connection?->rollBack(); return ['success' => false, 'code' => 'empty', 'queued_count' => 0]; }
            if (!$this->newsletter->markCampaignQueued($campaignId, $count, $count, new DateTimeImmutable())) throw new \RuntimeException('Campaign state could not be settled.');
            if ($owns) $this->connection?->commit(); return ['success' => true, 'queued_count' => $count, 'idempotent' => false];
        } catch (Throwable) { if ($owns && $this->connection?->inTransaction()) $this->connection->rollBack(); return ['success' => false, 'code' => 'failure', 'queued_count' => 0]; }
    }

    public function campaigns(): array { return $this->newsletter->campaigns(); }
    private function token(mixed $value): ?string { return is_scalar($value) && preg_match('/\A[a-f0-9]{64}\z/D', (string) $value) === 1 ? (string) $value : null; }
}
