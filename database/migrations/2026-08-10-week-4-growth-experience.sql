-- OEMS Week 4 growth and experience forward migration.
-- Repeatable for populated MySQL databases that already include the
-- 2026-08-10 Week 3 operations and specification-completion migrations.

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE events ADD COLUMN waitlist_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER is_featured',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'events' AND column_name = 'waitlist_enabled'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE registrations ADD COLUMN waitlisted_at DATETIME NULL AFTER registered_at',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'registrations' AND column_name = 'waitlisted_at'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE registrations ADD COLUMN promoted_at DATETIME NULL AFTER waitlisted_at',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'registrations' AND column_name = 'promoted_at'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE registrations ADD COLUMN waitlist_claim_expires_at DATETIME NULL AFTER promoted_at',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'registrations' AND column_name = 'waitlist_claim_expires_at'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

UPDATE registrations
SET waitlisted_at = COALESCE(waitlisted_at, registered_at)
WHERE status = 'waitlisted' AND waitlisted_at IS NULL;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE registrations ADD CONSTRAINT chk_registrations_waitlist_state CHECK ((status <> ''waitlisted'' OR (waitlisted_at IS NOT NULL AND promoted_at IS NULL AND waitlist_claim_expires_at IS NULL)) AND (waitlist_claim_expires_at IS NULL OR (status = ''pending'' AND promoted_at IS NOT NULL)))',
        'SELECT 1')
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND table_name = 'registrations'
      AND constraint_name = 'chk_registrations_waitlist_state' AND constraint_type = 'CHECK'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE registrations ADD INDEX idx_registrations_event_waitlist (event_id, status, waitlisted_at, id)',
        'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'registrations' AND index_name = 'idx_registrations_event_waitlist'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

CREATE TABLE IF NOT EXISTS event_certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id BIGINT UNSIGNED NOT NULL UNIQUE,
    participant_id BIGINT UNSIGNED NOT NULL,
    certificate_number VARCHAR(48) NOT NULL UNIQUE,
    verification_token_hash CHAR(64) NOT NULL UNIQUE,
    pdf_path VARCHAR(255) NOT NULL,
    status ENUM('valid', 'revoked') NOT NULL DEFAULT 'valid',
    issued_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    revoked_by BIGINT UNSIGNED NULL,
    revocation_reason VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_certificates_participant_issued (participant_id, issued_at, id),
    INDEX idx_event_certificates_status_issued (status, issued_at, id),
    CONSTRAINT fk_event_certificates_registration FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_certificates_participant FOREIGN KEY (participant_id) REFERENCES users (id),
    CONSTRAINT fk_event_certificates_revoked_by FOREIGN KEY (revoked_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_event_certificates_revocation CHECK (
        (status = 'valid' AND revoked_at IS NULL AND revocation_reason IS NULL)
        OR (status = 'revoked' AND revoked_at IS NOT NULL AND revocation_reason IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    excerpt VARCHAR(500) NOT NULL,
    body LONGTEXT NOT NULL,
    category VARCHAR(100) NULL,
    cover_image VARCHAR(255) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    meta_title VARCHAR(190) NULL,
    meta_description VARCHAR(300) NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_blog_posts_public (status, published_at, id),
    INDEX idx_blog_posts_category_public (category, status, published_at, id),
    CONSTRAINT fk_blog_posts_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_blog_posts_publication CHECK (
        (status = 'draft' AND published_at IS NULL)
        OR (status = 'published' AND published_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
