<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\AdminPeopleRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class AdminPeopleRepositoryTest extends TestCase
{
    private PDO $connection;

    private AdminPeopleRepository $people;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedRows();
        $this->people = new AdminPeopleRepository($this->connection);
    }

    public function testUsersUseBoundedFiltersPaginationAndExposeOnlyAdministrativeFields(): void
    {
        $page = $this->people->users([
            'search' => 'participant',
            'role' => 'participant',
            'status' => 'active',
        ], 1, 1);

        $this->assertSame(1, count($page['items']));
        $this->assertSame('second-participant@example.test', $page['items'][0]['email']);
        $this->assertSame(2, $page['pagination']['total']);
        $this->assertSame(2, $page['pagination']['last_page']);
        $this->assertFalse(array_key_exists('password', $page['items'][0]));
        $this->assertFalse(array_key_exists('email_verification_token_hash', $page['items'][0]));
    }

    public function testSuspensionIsCasProtectedRevokesAuthenticationArtifactsAndWritesAudit(): void
    {
        $changed = $this->people->changeUserStatus(99, 10, 'active', 'suspended', [
            'ip_address' => '203.0.113.7',
            'user_agent' => 'Admin browser',
        ]);
        $stale = $this->people->changeUserStatus(99, 10, 'active', 'suspended', []);
        $superAdmin = $this->people->changeUserStatus(99, 99, 'active', 'suspended', []);

        $this->assertTrue($changed);
        $this->assertFalse($stale);
        $this->assertFalse($superAdmin);
        $this->assertSame('suspended', $this->scalar('SELECT status FROM users WHERE id = 10'));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM sessions WHERE user_id = 10'));
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM password_resets WHERE email = 'participant@example.test'"));
        $this->assertSame('user.suspended', $this->scalar('SELECT action FROM activity_logs ORDER BY id DESC LIMIT 1'));
        $this->assertSame('203.0.113.7', $this->scalar('SELECT ip_address FROM activity_logs ORDER BY id DESC LIMIT 1'));
    }

    public function testInactiveLifecycleRevokesAuthenticationArtifactsAndCanReactivate(): void
    {
        $deactivated = $this->people->changeUserStatus(99, 10, 'active', 'inactive', []);
        $reactivated = $this->people->changeUserStatus(99, 10, 'inactive', 'active', []);

        $this->assertTrue($deactivated);
        $this->assertTrue($reactivated);
        $this->assertSame('active', $this->scalar('SELECT status FROM users WHERE id = 10'));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM sessions WHERE user_id = 10'));
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM password_resets WHERE email = 'participant@example.test'"));
        $this->assertSame('user.active', $this->scalar('SELECT action FROM activity_logs ORDER BY id DESC LIMIT 1'));
    }

    public function testUserDetailAndOrganizerPagesReturnSafeOperationalEvidence(): void
    {
        $user = $this->people->findUser(10);
        $organizers = $this->people->organizers([
            'search' => 'events',
            'approval_status' => 'pending',
        ], 1, 10);
        $organizer = $this->people->findOrganizer(20);

        $this->assertSame('participant@example.test', $user['email'] ?? null);
        $this->assertSame(1, $user['session_count'] ?? null);
        $this->assertSame(1, $user['session_count'] ?? null);
        $this->assertFalse(array_key_exists('password', $user));
        $this->assertSame(3, $organizers['pagination']['total']);
        $this->assertSame('Pending Verified Events', $organizer['organization_name'] ?? null);
        $this->assertSame('organizer', $organizer['role_slug'] ?? null);
        $this->assertFalse(array_key_exists('password', $organizer));
        $this->assertNull($this->people->findUser(999));
        $this->assertNull($this->people->findOrganizer(999));
    }

    public function testOrganizerApprovalRequiresEligibleUserAndAllowedCasTransition(): void
    {
        $approved = $this->people->changeOrganizerApproval(99, 20, 'pending', 'approved', null, []);
        $stale = $this->people->changeOrganizerApproval(99, 20, 'pending', 'rejected', 'Stale', []);
        $approvedRejection = $this->people->changeOrganizerApproval(99, 20, 'approved', 'rejected', 'Policy evidence is incomplete.', []);
        $unverified = $this->people->changeOrganizerApproval(99, 21, 'pending', 'approved', null, []);
        $rejectedUnverified = $this->people->changeOrganizerApproval(99, 21, 'pending', 'rejected', 'Verification is incomplete.', []);
        $rejected = $this->people->changeOrganizerApproval(99, 22, 'pending', 'rejected', 'Missing legal identity.', []);
        $reapproved = $this->people->changeOrganizerApproval(99, 22, 'rejected', 'approved', null, []);

        $this->assertSame(12, $approved['user_id'] ?? null);
        $this->assertNull($stale);
        $this->assertSame(12, $approvedRejection['user_id'] ?? null);
        $this->assertSame(99, (int) $this->scalar('SELECT approved_by FROM organizers WHERE id = 20'));
        $this->assertTrue((string) $this->scalar('SELECT approved_at FROM organizers WHERE id = 20') !== '');
        $this->assertNull($unverified);
        $this->assertSame(13, $rejectedUnverified['user_id'] ?? null);
        $this->assertSame(14, $rejected['user_id'] ?? null);
        $this->assertSame(14, $reapproved['user_id'] ?? null);
        $this->assertSame('approved', $this->scalar('SELECT approval_status FROM organizers WHERE id = 22'));
        $this->assertNull($this->scalar('SELECT rejection_reason FROM organizers WHERE id = 22'));
        $this->assertSame('organizer.approved', $this->scalar('SELECT action FROM activity_logs ORDER BY id DESC LIMIT 1'));
    }

    private function createSchema(): void
    {
        $this->connection->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE)');
        $this->connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role_id INTEGER NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL, phone TEXT NULL, avatar TEXT NULL, status TEXT NOT NULL, email_verification_token_hash TEXT NULL, email_verified_at TEXT NULL, last_login_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE profiles (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, bio TEXT NULL, city TEXT NULL, country TEXT NULL, timezone TEXT NULL)');
        $this->connection->exec('CREATE TABLE organizers (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL UNIQUE, organization_name TEXT NOT NULL, description TEXT NULL, logo TEXT NULL, tax_identifier TEXT NULL, approval_status TEXT NOT NULL, approved_by INTEGER NULL, approved_at TEXT NULL, rejection_reason TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, organizer_id INTEGER NOT NULL, deleted_at TEXT NULL)');
        $this->connection->exec('CREATE TABLE registrations (id INTEGER PRIMARY KEY, event_id INTEGER NOT NULL, user_id INTEGER NOT NULL)');
        $this->connection->exec('CREATE TABLE sessions (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, expires_at TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE password_resets (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
        $this->connection->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, action TEXT NOT NULL, subject_type TEXT NULL, subject_id INTEGER NULL, description TEXT NOT NULL, properties TEXT NULL, ip_address TEXT NULL, user_agent TEXT NULL, created_at TEXT NOT NULL)');
    }

    private function seedRows(): void
    {
        $this->connection->exec("INSERT INTO roles (id, name, slug) VALUES (1, 'Super Administrator', 'super-admin'), (2, 'Organizer', 'organizer'), (3, 'Participant', 'participant')");
        $this->connection->exec("INSERT INTO users (id, role_id, name, email, password, status, email_verified_at, created_at, updated_at) VALUES
            (99, 1, 'Nadia Administrator', 'admin@example.test', 'secret-admin-hash', 'active', '2026-08-01 09:00:00', '2026-08-01 09:00:00', '2026-08-01 09:00:00'),
            (10, 3, 'Active Participant', 'participant@example.test', 'secret-participant-hash', 'active', '2026-08-01 09:00:00', '2026-08-02 09:00:00', '2026-08-02 09:00:00'),
            (11, 3, 'Second Participant', 'second-participant@example.test', 'secret-second-hash', 'active', '2026-08-01 09:00:00', '2026-08-03 09:00:00', '2026-08-03 09:00:00'),
            (12, 2, 'Approved Organizer User', 'organizer@example.test', 'secret-organizer-hash', 'active', '2026-08-01 09:00:00', '2026-08-04 09:00:00', '2026-08-04 09:00:00'),
            (13, 2, 'Unverified Organizer User', 'unverified@example.test', 'secret-unverified-hash', 'active', NULL, '2026-08-05 09:00:00', '2026-08-05 09:00:00'),
            (14, 2, 'Reconsidered Organizer User', 'reconsidered@example.test', 'secret-reconsidered-hash', 'active', '2026-08-01 09:00:00', '2026-08-06 09:00:00', '2026-08-06 09:00:00')");
        $this->connection->exec("INSERT INTO profiles (id, user_id, city, country) VALUES (1, 10, 'Dhaka', 'Bangladesh')");
        $this->connection->exec("INSERT INTO organizers (id, user_id, organization_name, approval_status, created_at, updated_at) VALUES
            (20, 12, 'Pending Verified Events', 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            (21, 13, 'Unverified Events', 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            (22, 14, 'Reconsidered Events', 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $this->connection->exec("INSERT INTO sessions (id, user_id, expires_at) VALUES
            (1, 10, '2099-01-01 00:00:00'),
            (2, 10, '2000-01-01 00:00:00')");
        $this->connection->exec("INSERT INTO password_resets (id, email) VALUES (1, 'participant@example.test')");
    }

    private function scalar(string $query): mixed
    {
        return $this->connection->query($query)->fetchColumn();
    }
}
