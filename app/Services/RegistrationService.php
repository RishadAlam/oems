<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\PaymentRepositoryInterface;
use OEMS\App\Contracts\RegistrationRepositoryInterface;
use OEMS\App\Contracts\UserRepositoryInterface;
use OEMS\Core\Logger;
use PDO;
use Throwable;

final class RegistrationService
{
    private const PAYMENT_CHANNELS = [
        'bank',
        'mobile',
        'cash',
        'bank_transfer',
        'mobile_banking',
        'cash_deposit',
    ];

    public function __construct(
        private readonly PDO $connection,
        private readonly UserRepositoryInterface $users,
        private readonly RegistrationRepositoryInterface $registrations,
        private readonly PaymentRepositoryInterface $payments,
        private readonly TicketService $tickets,
        private readonly TransactionMailer $mailer,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function register(int $actorId, int $eventId, array $paymentDetails = []): array
    {
        $participant = $this->authorizedUser($actorId, 'participant');

        if ($participant === null) {
            return $this->failure(['account' => ['An active, verified participant account is required.']]);
        }

        $issuance = null;

        try {
            $this->connection->beginTransaction();
            $event = $this->registrations->findEligibleEventForReservation($eventId);

            if ($event === null) {
                $existing = $this->registrations->findForParticipantEventCurrent($actorId, $eventId);

                if ($this->isSeatConsuming($existing)) {
                    $result = $this->truthfulRegistrationResult($existing);
                    $this->connection->commit();

                    return $result;
                }

                return $this->rollbackFailure(['event' => ['This event is not available for registration.']]);
            }

            $existing = $this->registrations->findForParticipantEventCurrent($actorId, $eventId);

            if ($this->isSeatConsuming($existing)) {
                $result = $this->truthfulRegistrationResult($existing);
                $this->connection->commit();

                return $result;
            }

            $isFree = (float) $event['ticket_price'] <= 0.0;
            $methodSlug = $isFree ? 'free' : 'manual';
            $method = $this->payments->findActiveMethodBySlug($methodSlug);

            if ($method === null) {
                return $this->rollbackFailure(['payment_method' => ['The required payment method is unavailable.']]);
            }

            if (!$isFree) {
                $errors = $this->paymentDetailsErrors($paymentDetails);

                if ($errors !== []) {
                    return $this->rollbackFailure($errors);
                }
            }

            $attributes = [
                'coupon_id' => null,
                'registration_number' => $this->registrationNumber(),
                'status' => 'pending',
                'registered_at' => date('Y-m-d H:i:s'),
            ];

            if ($existing !== null) {
                if (!$this->registrations->reactivate((int) $existing['id'], $attributes)) {
                    return $this->rollbackFailure(['event' => ['A seat could not be reserved.']]);
                }

                $registration = $this->registrations->findForParticipant($actorId, (int) $existing['id']);
            } else {
                $registration = $this->registrations->reserve($actorId, $eventId, $attributes);
            }

            if ($registration === null) {
                $winner = $this->registrations->findForParticipantEventCurrent($actorId, $eventId);

                if ($this->isSeatConsuming($winner)) {
                    $result = $this->truthfulRegistrationResult($winner);
                    $this->connection->commit();

                    return $result;
                }

                return $this->rollbackFailure(['event' => ['A seat could not be reserved.']]);
            }

            $paymentId = $this->payments->createForRegistration((int) $registration['id'], [
                'payment_method_id' => (int) $method['id'],
                'transaction_reference' => $isFree
                    ? 'FREE-' . strtoupper(bin2hex(random_bytes(16)))
                    : trim((string) $paymentDetails['transaction_reference']),
                'amount' => (string) $registration['amount'],
                'currency' => (string) $registration['currency'],
                'status' => $isFree ? 'paid' : 'pending',
                'gateway_response' => $isFree
                    ? null
                    : ['channel' => strtolower(trim((string) $paymentDetails['channel']))],
                'paid_at' => $isFree ? date('Y-m-d H:i:s') : null,
            ]);

            if ($paymentId <= 0) {
                throw new \RuntimeException('The payment record could not be created.');
            }

            if ($isFree) {
                if (!$this->registrations->confirm((int) $registration['id'])) {
                    throw new \RuntimeException('The registration could not be confirmed.');
                }

                $registration = $this->registrations->findForParticipant($actorId, (int) $registration['id']);

                if ($registration === null) {
                    throw new \RuntimeException('The confirmed registration could not be read.');
                }

                $issuance = $this->tickets->issue($registration, $participant, $event);
            }

            $payment = $this->payments->findForAdmin($paymentId);

            if ($payment === null) {
                throw new \RuntimeException('The payment record could not be read.');
            }

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            if (is_array($issuance)) {
                $this->tickets->cleanupCreated($issuance);
            }

            $this->logFailure('registration', $actorId, $eventId, null, $exception);

            return $this->failure(['registration' => ['The registration could not be completed.']]);
        }

        if (is_array($issuance)) {
            $this->tickets->cleanupReplaced($issuance);
        }

        $deliveryStatus = $isFree
            ? $this->deliveryStatus([
                $this->mailer->sendConfirmation($participant, $registration),
                $this->mailer->sendTicket($participant, $registration, $issuance['ticket']),
            ])
            : $this->deliveryStatus([$this->mailer->sendPending($participant, $registration)]);

        return $this->success([
            'registration' => $registration,
            'payment' => $payment,
            'ticket' => $issuance['ticket'] ?? null,
            'delivery_status' => $deliveryStatus,
        ]);
    }

    public function verifyPayment(int $administratorId, int $paymentId, ?string $note = null): array
    {
        if ($this->authorizedUser($administratorId, 'super-admin') === null) {
            return $this->failure(['account' => ['An active, verified administrator account is required.']]);
        }

        $issuance = null;

        try {
            $this->connection->beginTransaction();
            $current = $this->payments->findForAdmin($paymentId);

            if ($current === null) {
                return $this->rollbackFailure(['payment' => ['Payment not found.']]);
            }

            if (!$this->registrations->lockEventCurrent((int) $current['event_id'])) {
                throw new \RuntimeException('The payment registration event could not be locked.');
            }

            $registration = $this->registrations->findForParticipantCurrent(
                (int) $current['participant_id'],
                (int) $current['registration_id'],
            );

            $current = $this->payments->findForAdminCurrent($paymentId);

            if ($registration === null
                || $current === null
                || (int) $current['registration_id'] !== (int) $registration['id']) {
                throw new \RuntimeException('The payment registration could not be read.');
            }

            if ((string) $current['payment_status'] === 'paid'
                && (string) $registration['registration_status'] === 'confirmed') {
                $ticket = $this->tickets->forRegistrationCurrent((int) $registration['id']);

                if ($ticket === null) {
                    throw new \RuntimeException('The confirmed ticket could not be read.');
                }

                $this->connection->commit();

                return $this->success([
                    'registration' => $registration,
                    'payment' => $current,
                    'ticket' => $ticket,
                    'delivery_status' => 'not_attempted',
                ]);
            }

            if ((string) $current['payment_status'] !== 'pending') {
                return $this->rollbackFailure(['payment' => ['This payment is no longer pending.']]);
            }

            $payment = $this->payments->review(
                $paymentId,
                $administratorId,
                'paid',
                $this->boundedNote($note),
            );

            if ($payment === null) {
                $winner = $this->verifiedTerminalState(
                    (int) $current['participant_id'],
                    (int) $registration['id'],
                    $paymentId,
                );

                if ($winner !== null) {
                    $this->connection->commit();

                    return $this->success($winner);
                }

                throw new \RuntimeException('The payment could not be verified.');
            }

            if (!$this->registrations->confirm((int) $registration['id'])) {
                $winner = $this->verifiedTerminalState(
                    (int) $current['participant_id'],
                    (int) $registration['id'],
                    $paymentId,
                );

                if ($winner !== null) {
                    $this->connection->commit();

                    return $this->success($winner);
                }

                throw new \RuntimeException('The registration could not be confirmed.');
            }

            $registration = $this->registrations->findForParticipantCurrent(
                (int) $current['participant_id'],
                (int) $registration['id'],
            );

            if ($registration === null) {
                throw new \RuntimeException('The confirmed registration could not be read.');
            }

            $participant = $this->paymentParticipant($payment);
            $issuance = $this->tickets->issue($registration, $participant, $registration);
            $payment = $this->payments->findForAdminCurrent($paymentId);

            if ($payment === null) {
                throw new \RuntimeException('The verified payment could not be read.');
            }

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            if (is_array($issuance)) {
                $this->tickets->cleanupCreated($issuance);
            }

            $this->logFailure('payment_verification', $administratorId, null, $paymentId, $exception);

            return $this->failure(['payment' => ['The payment could not be verified.']]);
        }

        $this->tickets->cleanupReplaced($issuance);
        $participant = $this->paymentParticipant($payment);
        $deliveryStatus = $this->deliveryStatus([
            $this->mailer->sendPaid($participant, $registration),
            $this->mailer->sendTicket($participant, $registration, $issuance['ticket']),
        ]);

        return $this->success([
            'registration' => $registration,
            'payment' => $payment,
            'ticket' => $issuance['ticket'],
            'delivery_status' => $deliveryStatus,
        ]);
    }

    public function rejectPayment(int $administratorId, int $paymentId, ?string $note = null): array
    {
        if ($this->authorizedUser($administratorId, 'super-admin') === null) {
            return $this->failure(['account' => ['An active, verified administrator account is required.']]);
        }

        try {
            $this->connection->beginTransaction();
            $current = $this->payments->findForAdmin($paymentId);

            if ($current === null) {
                return $this->rollbackFailure(['payment' => ['Payment not found.']]);
            }

            $identity = $this->registrations->findForParticipant(
                (int) $current['participant_id'],
                (int) $current['registration_id'],
            );

            if ($identity === null
                || !$this->registrations->lockEventCurrent((int) $identity['event_id'])) {
                throw new \RuntimeException('The payment registration event could not be locked.');
            }

            $registration = $this->registrations->findForParticipantCurrent(
                (int) $current['participant_id'],
                (int) $current['registration_id'],
            );
            $current = $this->payments->findForAdminCurrent($paymentId);

            if ($registration === null
                || $current === null
                || (int) $current['registration_id'] !== (int) $registration['id']) {
                throw new \RuntimeException('The payment registration could not be read.');
            }

            if ((string) $current['payment_status'] === 'failed'
                && ($registration['registration_status'] ?? null) === 'cancelled') {
                $this->connection->commit();

                return $this->success([
                    'registration' => $registration,
                    'payment' => $current,
                    'ticket' => $this->tickets->forRegistrationCurrent((int) $current['registration_id']),
                    'delivery_status' => 'not_attempted',
                ]);
            }

            if ($registration === null || (string) $current['payment_status'] !== 'pending') {
                return $this->rollbackFailure(['payment' => ['This payment is no longer pending.']]);
            }

            $payment = $this->payments->review(
                $paymentId,
                $administratorId,
                'failed',
                $this->boundedNote($note),
            );

            if ($payment === null) {
                $winner = $this->rejectedTerminalState(
                    (int) $current['participant_id'],
                    (int) $registration['id'],
                    $paymentId,
                );

                if ($winner !== null) {
                    $this->connection->commit();

                    return $this->success($winner);
                }

                throw new \RuntimeException('The payment could not be rejected.');
            }

            if (!$this->registrations->cancel((int) $registration['id'], 'Payment rejected')) {
                $winner = $this->rejectedTerminalState(
                    (int) $current['participant_id'],
                    (int) $registration['id'],
                    $paymentId,
                );

                if ($winner !== null) {
                    $this->connection->commit();

                    return $this->success($winner);
                }

                throw new \RuntimeException('The registration could not be rejected.');
            }

            $registration = $this->registrations->findForParticipantCurrent(
                (int) $current['participant_id'],
                (int) $registration['id'],
            );
            $payment = $this->payments->findForAdminCurrent($paymentId);

            if ($registration === null || $payment === null) {
                throw new \RuntimeException('The rejected payment state could not be read.');
            }

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->logFailure('payment_rejection', $administratorId, null, $paymentId, $exception);

            return $this->failure(['payment' => ['The payment could not be rejected.']]);
        }

        $participant = $this->paymentParticipant($payment);

        return $this->success([
            'registration' => $registration,
            'payment' => $payment,
            'ticket' => $this->tickets->forRegistration((int) $registration['id']),
            'delivery_status' => $this->deliveryStatus([
                $this->mailer->sendRejected($participant, $registration),
            ]),
        ]);
    }

    public function cancel(int $actorId, int $registrationId, string $reason): array
    {
        $participant = $this->authorizedUser($actorId, 'participant');

        if ($participant === null) {
            return $this->failure(['account' => ['An active, verified participant account is required.']]);
        }

        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 500) {
            return $this->failure(['reason' => ['Enter a cancellation reason of no more than 500 characters.']]);
        }

        try {
            $this->connection->beginTransaction();
            $identity = $this->registrations->findForParticipant($actorId, $registrationId);

            if ($identity === null) {
                return $this->rollbackFailure(['registration' => ['Registration not found.']]);
            }

            if (!$this->registrations->lockEventCurrent((int) $identity['event_id'])) {
                throw new \RuntimeException('The registration event could not be locked.');
            }

            $registration = $this->registrations->findForParticipantCurrent($actorId, $registrationId);

            if ($registration === null) {
                return $this->rollbackFailure(['registration' => ['Registration not found.']]);
            }

            $priorPayment = $this->payments->findForRegistrationCurrent($registrationId);
            $priorTicket = $this->tickets->forRegistrationCurrent($registrationId);

            if ((string) $registration['registration_status'] === 'cancelled') {
                $result = $this->truthfulRegistrationResult($registration);
                $this->connection->commit();

                return $result;
            }

            $registration = $this->registrations->cancelForParticipant($actorId, $registrationId, $reason);

            if ($registration === null) {
                $winner = $this->registrations->findForParticipantCurrent($actorId, $registrationId);

                if (($winner['registration_status'] ?? null) === 'cancelled') {
                    $result = $this->truthfulRegistrationResult($winner);
                    $this->connection->commit();

                    return $result;
                }

                return $this->rollbackFailure([
                    'registration' => ['This registration can no longer be cancelled.'],
                ]);
            }

            if (in_array((string) ($priorPayment['payment_status'] ?? ''), ['pending', 'paid'], true)
                && !$this->payments->cancelForRegistration($registrationId)) {
                $winner = $this->cancelledTerminalState(
                    $actorId,
                    $registrationId,
                    $priorPayment,
                    $priorTicket,
                );

                if ($winner !== null) {
                    $this->connection->commit();

                    return $this->success($winner);
                }

                throw new \RuntimeException('The related payment could not be cancelled.');
            }

            if ((string) ($priorTicket['ticket_status'] ?? '') === 'valid'
                && !$this->tickets->voidForRegistration($registrationId)) {
                $winner = $this->cancelledTerminalState(
                    $actorId,
                    $registrationId,
                    $priorPayment,
                    $priorTicket,
                );

                if ($winner !== null) {
                    $this->connection->commit();

                    return $this->success($winner);
                }

                throw new \RuntimeException('The related ticket could not be voided.');
            }

            if ((string) ($priorTicket['ticket_status'] ?? '') === 'used') {
                throw new \RuntimeException('An attended ticket cannot be cancelled.');
            }

            $payment = $this->payments->findForRegistrationCurrent($registrationId);
            $ticket = $this->tickets->forRegistrationCurrent($registrationId);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->logFailure('registration_cancellation', $actorId, null, null, $exception);

            return $this->failure(['registration' => ['The registration could not be cancelled.']]);
        }

        return $this->success([
            'registration' => $registration,
            'payment' => $payment,
            'ticket' => $ticket,
            'delivery_status' => $this->deliveryStatus([
                $this->mailer->sendCancelled($participant, $registration),
            ]),
        ]);
    }

    private function truthfulRegistrationResult(array $registration): array
    {
        $registrationId = (int) $registration['id'];

        return $this->success([
            'registration' => $registration,
            'payment' => $this->payments->findForRegistrationCurrent($registrationId),
            'ticket' => $this->tickets->forRegistrationCurrent($registrationId),
            'delivery_status' => 'not_attempted',
        ]);
    }

    private function isSeatConsuming(?array $registration): bool
    {
        return $registration !== null
            && in_array((string) ($registration['registration_status'] ?? ''), ['pending', 'confirmed'], true);
    }

    private function verifiedTerminalState(
        int $participantId,
        int $registrationId,
        int $paymentId,
    ): ?array {
        $registration = $this->registrations->findForParticipantCurrent($participantId, $registrationId);
        $payment = $this->payments->findForAdminCurrent($paymentId);
        $ticket = $this->tickets->forRegistrationCurrent($registrationId);

        if (($registration['registration_status'] ?? null) !== 'confirmed'
            || ($payment['payment_status'] ?? null) !== 'paid'
            || !in_array((string) ($ticket['ticket_status'] ?? ''), ['valid', 'used'], true)) {
            return null;
        }

        return [
            'registration' => $registration,
            'payment' => $payment,
            'ticket' => $ticket,
            'delivery_status' => 'not_attempted',
        ];
    }

    private function rejectedTerminalState(
        int $participantId,
        int $registrationId,
        int $paymentId,
    ): ?array {
        $registration = $this->registrations->findForParticipantCurrent($participantId, $registrationId);
        $payment = $this->payments->findForAdminCurrent($paymentId);

        if (($registration['registration_status'] ?? null) !== 'cancelled'
            || ($payment['payment_status'] ?? null) !== 'failed') {
            return null;
        }

        return [
            'registration' => $registration,
            'payment' => $payment,
            'ticket' => $this->tickets->forRegistrationCurrent($registrationId),
            'delivery_status' => 'not_attempted',
        ];
    }

    private function cancelledTerminalState(
        int $participantId,
        int $registrationId,
        ?array $priorPayment,
        ?array $priorTicket,
    ): ?array {
        $registration = $this->registrations->findForParticipantCurrent($participantId, $registrationId);
        $payment = $this->payments->findForRegistrationCurrent($registrationId);
        $ticket = $this->tickets->forRegistrationCurrent($registrationId);
        $expectedPayment = (string) ($priorPayment['payment_status'] ?? '') === 'paid' ? 'refunded' : 'failed';
        $ticketMatches = $priorTicket === null
            ? $ticket === null
            : ((string) ($priorTicket['ticket_status'] ?? '') === 'cancelled'
                || ((string) ($priorTicket['ticket_status'] ?? '') === 'valid'
                    && (string) ($ticket['ticket_status'] ?? '') === 'cancelled'));

        if (($registration['registration_status'] ?? null) !== 'cancelled'
            || ($payment['payment_status'] ?? null) !== $expectedPayment
            || !$ticketMatches) {
            return null;
        }

        return [
            'registration' => $registration,
            'payment' => $payment,
            'ticket' => $ticket,
            'delivery_status' => 'not_attempted',
        ];
    }

    private function authorizedUser(int $userId, string $role): ?array
    {
        $user = $this->users->findById($userId);

        return $user !== null
            && ($user['role_slug'] ?? null) === $role
            && ($user['status'] ?? null) === 'active'
            && ($user['email_verified_at'] ?? null) !== null
                ? $user
                : null;
    }

    private function paymentDetailsErrors(array $details): array
    {
        $reference = trim((string) ($details['transaction_reference'] ?? ''));
        $channel = strtolower(trim((string) ($details['channel'] ?? '')));
        $errors = [];

        if (mb_strlen($reference) < 6 || mb_strlen($reference) > 190) {
            $errors['transaction_reference'][] = 'Enter a transaction reference between 6 and 190 characters.';
        }

        if (!in_array($channel, self::PAYMENT_CHANNELS, true)) {
            $errors['channel'][] = 'Select a supported payment channel.';
        }

        return $errors;
    }

    private function boundedNote(?string $note): ?string
    {
        $note = $note === null ? null : trim($note);

        return $note === '' ? null : mb_substr($note, 0, 500);
    }

    private function paymentParticipant(array $payment): array
    {
        return [
            'id' => (int) ($payment['participant_id'] ?? 0),
            'name' => (string) ($payment['participant_name'] ?? 'Participant'),
            'email' => (string) ($payment['participant_email'] ?? ''),
        ];
    }

    private function registrationNumber(): string
    {
        return 'REG-' . strtoupper(bin2hex(random_bytes(16)));
    }

    private function rollbackFailure(array $errors): array
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        return $this->failure($errors);
    }

    private function logFailure(
        string $operation,
        int $actorId,
        ?int $eventId,
        ?int $paymentId,
        Throwable $exception,
    ): void {
        try {
            $context = [
                'operation' => $operation,
                'actor_id' => $actorId,
                'exception_class' => $exception::class,
            ];

            if ($eventId !== null) {
                $context['event_id'] = $eventId;
            }

            if ($paymentId !== null) {
                $context['payment_id'] = $paymentId;
            }

            $this->logger?->error('Registration transaction failed.', $context);
        } catch (Throwable) {
            // Logging must not change the safe domain response.
        }
    }

    private function deliveryStatus(array $outcomes): string
    {
        return !in_array(false, $outcomes, true) ? 'sent' : 'failed';
    }

    private function success(array $data): array
    {
        return array_merge(['success' => true, 'errors' => []], $data);
    }

    private function failure(array $errors): array
    {
        return ['success' => false, 'errors' => $errors];
    }
}
