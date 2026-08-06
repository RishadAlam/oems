<?php

declare(strict_types=1);

namespace OEMS\App\Models;

final readonly class User
{
    public function __construct(
        public int $id,
        public int $roleId,
        public string $name,
        public string $email,
        public string $role,
        public string $status,
        public ?string $emailVerifiedAt,
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            (int) $attributes['id'],
            (int) $attributes['role_id'],
            (string) $attributes['name'],
            (string) $attributes['email'],
            (string) $attributes['role_slug'],
            (string) $attributes['status'],
            isset($attributes['email_verified_at']) ? (string) $attributes['email_verified_at'] : null,
        );
    }
}

