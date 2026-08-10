-- OEMS Week 3 operations forward migration.
-- Repeatable for populated MySQL databases that already include the
-- 2026-08-10 specification-completion migration.

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE newsletter ADD COLUMN confirmation_token_hash CHAR(64) NULL UNIQUE AFTER status',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'newsletter' AND column_name = 'confirmation_token_hash'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE newsletter ADD COLUMN confirmation_expires_at DATETIME NULL AFTER confirmation_token_hash',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'newsletter' AND column_name = 'confirmation_expires_at'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE newsletter ADD COLUMN confirmed_at DATETIME NULL AFTER confirmation_expires_at',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'newsletter' AND column_name = 'confirmed_at'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE newsletter ADD COLUMN unsubscribe_token_hash CHAR(64) NULL UNIQUE AFTER confirmed_at',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'newsletter' AND column_name = 'unsubscribe_token_hash'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

ALTER TABLE newsletter
    MODIFY COLUMN status ENUM('pending', 'subscribed', 'unsubscribed') NOT NULL DEFAULT 'pending';

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE newsletter ADD INDEX idx_newsletter_status_created (status, created_at)',
        'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'newsletter' AND index_name = 'idx_newsletter_status_created'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE newsletter ADD INDEX idx_newsletter_confirmation_expiry (confirmation_token_hash, confirmation_expires_at)',
        'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'newsletter' AND index_name = 'idx_newsletter_confirmation_expiry'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE newsletter ADD CONSTRAINT chk_newsletter_confirmation CHECK ((status = ''pending'' AND confirmation_token_hash IS NOT NULL AND confirmation_expires_at IS NOT NULL) OR status IN (''subscribed'', ''unsubscribed''))',
        'SELECT 1')
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND table_name = 'newsletter' AND constraint_name = 'chk_newsletter_confirmation'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

CREATE TABLE IF NOT EXISTS newsletter_campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('draft', 'queued', 'sent', 'failed') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NULL,
    recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
    queued_count INT UNSIGNED NOT NULL DEFAULT 0,
    request_key CHAR(64) NOT NULL UNIQUE,
    scheduled_at DATETIME NULL,
    queued_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_newsletter_campaigns_status_schedule (status, scheduled_at, id),
    CONSTRAINT fk_newsletter_campaigns_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_newsletter_campaign_counts CHECK (queued_count <= recipient_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template VARCHAR(100) NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    payload JSON NOT NULL,
    idempotency_key CHAR(64) NOT NULL UNIQUE,
    status ENUM('queued', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL,
    lock_token CHAR(64) NULL,
    locked_at DATETIME NULL,
    sent_at DATETIME NULL,
    provider_message_id VARCHAR(190) NULL,
    last_error VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mail_outbox_delivery (status, available_at, id),
    INDEX idx_mail_outbox_lock (status, locked_at),
    CONSTRAINT chk_mail_outbox_attempts CHECK (attempts <= 20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE coupons ADD INDEX idx_coupons_organizer_event_active (organizer_id, event_id, is_active)',
        'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'coupons' AND index_name = 'idx_coupons_organizer_event_active'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE coupons ADD INDEX idx_coupons_active_window (is_active, starts_at, expires_at)',
        'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'coupons' AND index_name = 'idx_coupons_active_window'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE coupons ADD CONSTRAINT chk_coupons_discount CHECK ((discount_type = ''percentage'' AND discount_value > 0 AND discount_value <= 100) OR (discount_type = ''fixed'' AND discount_value > 0))',
        'SELECT 1')
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND table_name = 'coupons' AND constraint_name = 'chk_coupons_discount'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE coupons ADD CONSTRAINT chk_coupons_usage CHECK (usage_limit IS NULL OR used_count <= usage_limit)',
        'SELECT 1')
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND table_name = 'coupons' AND constraint_name = 'chk_coupons_usage'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE coupons ADD CONSTRAINT chk_coupons_dates CHECK (starts_at IS NULL OR expires_at IS NULL OR expires_at >= starts_at)',
        'SELECT 1')
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND table_name = 'coupons' AND constraint_name = 'chk_coupons_dates'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE coupon_usage ADD UNIQUE INDEX uq_coupon_usage_coupon_user (coupon_id, user_id)',
        'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'coupon_usage' AND index_name = 'uq_coupon_usage_coupon_user'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE coupon_usage ADD INDEX idx_coupon_usage_user_used (user_id, used_at)',
        'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'coupon_usage' AND index_name = 'idx_coupon_usage_user_used'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

SET @statement = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE contact_messages ADD INDEX idx_contact_messages_email_date (email, created_at)',
        'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'contact_messages' AND index_name = 'idx_contact_messages_email_date'
);
PREPARE oems_statement FROM @statement; EXECUTE oems_statement; DEALLOCATE PREPARE oems_statement;

INSERT INTO settings (`group`, `key`, `value`, value_type, is_public) VALUES
    ('mail', 'outbox_max_attempts', '5', 'integer', FALSE),
    ('mail', 'reminder_lead_hours', '24', 'integer', FALSE),
    ('operations', 'backup_retention', '14', 'integer', FALSE)
ON DUPLICATE KEY UPDATE
    `group` = VALUES(`group`),
    value_type = VALUES(value_type),
    is_public = FALSE;
