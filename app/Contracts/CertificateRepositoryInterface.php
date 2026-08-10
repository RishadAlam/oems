<?php

declare(strict_types=1);

namespace OEMS\App\Contracts;

interface CertificateRepositoryInterface
{
    public function lockEligibleRegistration(int $participantId, int $registrationId): ?array;

    public function create(int $registrationId, int $participantId, array $attributes): int;

    public function findForRegistration(int $participantId, int $registrationId): ?array;

    public function findForParticipant(int $participantId, int $certificateId): ?array;

    public function forParticipant(int $participantId): array;

    public function findValidByTokenDigest(string $digest): ?array;
}
