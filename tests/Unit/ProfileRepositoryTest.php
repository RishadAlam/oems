<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\ProfileRepository;
use OEMS\Tests\Support\TestCase;
use PDO;
use RuntimeException;

final class ProfileRepositoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createTables();
        $this->seedAccounts();
    }

    public function testFindForUserReturnsJoinedAccountAndProfile(): void
    {
        $repository = new ProfileRepository($this->connection);

        $profile = $repository->findForUser(7);

        $this->assertNotNull($profile);
        $this->assertSame('Nusrat Jahan', $profile['name']);
        $this->assertSame('participant', $profile['role_slug']);
        $this->assertSame('Dhaka', $profile['city']);
    }

    public function testUpdateForUserChangesOnlyTheRequestedAccount(): void
    {
        $repository = new ProfileRepository($this->connection);

        $repository->updateForUser(7, $this->updatedAttributes());

        $updated = $repository->findForUser(7);
        $other = $repository->findForUser(8);
        $this->assertSame('Nusrat Sultana', $updated['name']);
        $this->assertSame('+8801712345678', $updated['phone']);
        $this->assertSame('Community event volunteer.', $updated['bio']);
        $this->assertSame('Chattogram', $updated['city']);
        $this->assertSame('Farhan Kabir', $other['name']);
        $this->assertSame('Sylhet', $other['city']);
    }

    public function testUpdateForUserRollsBackWhenTheProfileRowIsMissing(): void
    {
        $this->connection->exec('DELETE FROM profiles WHERE user_id = 7');
        $repository = new ProfileRepository($this->connection);
        $message = '';

        try {
            $repository->updateForUser(7, $this->updatedAttributes());
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
        }

        $name = $this->connection->query('SELECT name FROM users WHERE id = 7')->fetchColumn();
        $this->assertSame('Profile not found.', $message);
        $this->assertSame('Nusrat Jahan', $name);
    }

    private function createTables(): void
    {
        $this->connection->exec(
            'CREATE TABLE roles (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE
            )',
        );
        $this->connection->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                role_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                phone TEXT NULL,
                status TEXT NOT NULL,
                deleted_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
        );
        $this->connection->exec(
            'CREATE TABLE profiles (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL UNIQUE,
                bio TEXT NULL,
                date_of_birth TEXT NULL,
                gender TEXT NULL,
                address_line TEXT NULL,
                city TEXT NULL,
                country TEXT NULL,
                postal_code TEXT NULL,
                website TEXT NULL,
                locale TEXT NOT NULL,
                timezone TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
        );
    }

    private function seedAccounts(): void
    {
        $this->connection->exec(
            "INSERT INTO roles (id, name, slug) VALUES (3, 'Participant', 'participant')",
        );
        $this->connection->exec(
            "INSERT INTO users (id, role_id, name, email, phone, status, created_at, updated_at)
             VALUES
                (7, 3, 'Nusrat Jahan', 'nusrat@example.test', NULL, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (8, 3, 'Farhan Kabir', 'farhan@example.test', NULL, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
        );
        $this->connection->exec(
            "INSERT INTO profiles
                (id, user_id, bio, city, country, locale, timezone, created_at, updated_at)
             VALUES
                (11, 7, 'Volunteer.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (12, 8, 'Speaker.', 'Sylhet', 'Bangladesh', 'en', 'Asia/Dhaka', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
        );
    }

    private function updatedAttributes(): array
    {
        return [
            'name' => 'Nusrat Sultana',
            'phone' => '+8801712345678',
            'bio' => 'Community event volunteer.',
            'date_of_birth' => '1997-04-13',
            'gender' => 'female',
            'address_line' => 'Agrabad',
            'city' => 'Chattogram',
            'country' => 'Bangladesh',
            'postal_code' => '4100',
            'website' => 'https://example.test/nusrat',
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
        ];
    }
}
