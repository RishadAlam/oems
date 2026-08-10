-- Forward-only OEMS original-specification completion migration.
-- Safe to run repeatedly after a complete or partially completed deployment.

CREATE TABLE IF NOT EXISTS event_announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    sent_by BIGINT UNSIGNED NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    audience ENUM('confirmed') NOT NULL DEFAULT 'confirmed',
    recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
    request_key CHAR(64) NOT NULL UNIQUE,
    sent_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_announcements_event_sent (event_id, sent_at),
    CONSTRAINT fk_event_announcements_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
    CONSTRAINT fk_event_announcements_sender FOREIGN KEY (sent_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
