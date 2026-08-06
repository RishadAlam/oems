<?php

declare(strict_types=1);

namespace OEMS\App\Repositories;

use OEMS\App\Contracts\ProfileRepositoryInterface;
use PDO;
use RuntimeException;
use Throwable;

final class ProfileRepository implements ProfileRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findForUser(int $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT
                users.id,
                users.name,
                users.email,
                users.phone,
                users.status,
                roles.name AS role_name,
                roles.slug AS role_slug,
                profiles.bio,
                profiles.date_of_birth,
                profiles.gender,
                profiles.address_line,
                profiles.city,
                profiles.country,
                profiles.postal_code,
                profiles.website,
                profiles.locale,
                profiles.timezone
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             LEFT JOIN profiles ON profiles.user_id = users.id
             WHERE users.id = :user_id AND users.deleted_at IS NULL
             LIMIT 1',
        );
        $statement->execute(['user_id' => $userId]);
        $profile = $statement->fetch();

        return is_array($profile) ? $profile : null;
    }

    public function updateForUser(int $userId, array $attributes): void
    {
        $this->connection->beginTransaction();

        try {
            $exists = $this->connection->prepare(
                'SELECT profiles.id
                 FROM users
                 INNER JOIN profiles ON profiles.user_id = users.id
                 WHERE users.id = :user_id AND users.deleted_at IS NULL
                 LIMIT 1',
            );
            $exists->execute(['user_id' => $userId]);

            if ($exists->fetchColumn() === false) {
                throw new RuntimeException('Profile not found.');
            }

            $user = $this->connection->prepare(
                'UPDATE users
                 SET name = :name, phone = :phone, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :user_id AND deleted_at IS NULL',
            );
            $user->execute([
                'name' => $attributes['name'],
                'phone' => $attributes['phone'],
                'user_id' => $userId,
            ]);

            $profile = $this->connection->prepare(
                'UPDATE profiles
                 SET bio = :bio,
                     date_of_birth = :date_of_birth,
                     gender = :gender,
                     address_line = :address_line,
                     city = :city,
                     country = :country,
                     postal_code = :postal_code,
                     website = :website,
                     locale = :locale,
                     timezone = :timezone,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE user_id = :user_id',
            );
            $profile->execute([
                'bio' => $attributes['bio'],
                'date_of_birth' => $attributes['date_of_birth'],
                'gender' => $attributes['gender'],
                'address_line' => $attributes['address_line'],
                'city' => $attributes['city'],
                'country' => $attributes['country'],
                'postal_code' => $attributes['postal_code'],
                'website' => $attributes['website'],
                'locale' => $attributes['locale'],
                'timezone' => $attributes['timezone'],
                'user_id' => $userId,
            ]);

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
