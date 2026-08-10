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

INSERT IGNORE INTO settings (`group`, `key`, `value`, value_type, is_public) VALUES
    ('general', 'site_name', 'OEMS', 'string', TRUE),
    ('general', 'site_tagline', 'Find your next room full of ideas.', 'string', TRUE),
    ('general', 'contact_email', 'hello@oems.local', 'string', TRUE),
    ('general', 'support_phone', '+880 2 0000 0000', 'string', TRUE),
    ('general', 'footer_blurb', 'Better tools for finding a crowd, filling a room, and running an event people remember.', 'string', TRUE),
    ('general', 'footer_location', 'Dhaka, Bangladesh', 'string', TRUE),
    ('home', 'home_hero_kicker', 'Events made for showing up', 'string', TRUE),
    ('home', 'home_hero_title', 'Find your next standout event.', 'string', TRUE),
    ('home', 'home_hero_copy', 'Discover workshops, talks, and gatherings across Dhaka, or host an event experience that feels effortless.', 'string', TRUE),
    ('general', 'default_seo_description', 'Discover published workshops, talks, and gatherings with OEMS.', 'string', TRUE);

INSERT IGNORE INTO pages (title, slug, content, status, published_at, created_by, updated_by) VALUES
    ('About', 'about', 'OEMS connects people with meaningful events and helps organizers run them confidently.', 'published', NOW(), NULL, NULL),
    ('Contact', 'contact', 'Email the public support address shown in the OEMS footer for help with accounts, events, registrations, or tickets.', 'published', NOW(), NULL, NULL),
    ('Privacy', 'privacy', 'OEMS handles account and event data according to the published privacy policy.', 'published', NOW(), NULL, NULL),
    ('Terms', 'terms', 'Use of OEMS is subject to the platform terms and organizer policies.', 'published', NOW(), NULL, NULL);

UPDATE pages
SET content = 'OEMS connects people with meaningful events and helps organizers run them confidently.'
WHERE slug = 'about' AND content = '<p>OEMS connects people with meaningful events and helps organizers run them confidently.</p>';
UPDATE pages
SET content = 'OEMS handles account and event data according to the published privacy policy.'
WHERE slug = 'privacy' AND content = '<p>OEMS handles account and event data according to the published privacy policy.</p>';
UPDATE pages
SET content = 'Use of OEMS is subject to the platform terms and organizer policies.'
WHERE slug = 'terms' AND content = '<p>Use of OEMS is subject to the platform terms and organizer policies.</p>';

SET @oems_pages_index := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE pages ADD INDEX idx_pages_status_published (status, published_at)', 'SELECT 1')
    FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pages' AND index_name = 'idx_pages_status_published'
);
PREPARE oems_pages_index_statement FROM @oems_pages_index;
EXECUTE oems_pages_index_statement;
DEALLOCATE PREPARE oems_pages_index_statement;

SET @oems_faqs_index := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE faqs ADD INDEX idx_faqs_active_sort (is_active, sort_order)', 'SELECT 1')
    FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'faqs' AND index_name = 'idx_faqs_active_sort'
);
PREPARE oems_faqs_index_statement FROM @oems_faqs_index;
EXECUTE oems_faqs_index_statement;
DEALLOCATE PREPARE oems_faqs_index_statement;

SET @oems_banners_index := (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE banners ADD INDEX idx_banners_home_schedule (location, is_active, starts_at, ends_at, sort_order)', 'SELECT 1')
    FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'banners' AND index_name = 'idx_banners_home_schedule'
);
PREPARE oems_banners_index_statement FROM @oems_banners_index;
EXECUTE oems_banners_index_statement;
DEALLOCATE PREPARE oems_banners_index_statement;
