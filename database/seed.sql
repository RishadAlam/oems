INSERT INTO roles (id, name, slug, description) VALUES
    (1, 'Super Admin', 'super-admin', 'Full platform administration access.'),
    (2, 'Organizer', 'organizer', 'Creates events and manages event operations.'),
    (3, 'Participant', 'participant', 'Discovers and registers for events.');

INSERT INTO permissions (name, slug, description) VALUES
    ('Manage users', 'users.manage', 'Create, update, suspend, and restore users.'),
    ('Manage organizers', 'organizers.manage', 'Review and manage organizer accounts.'),
    ('Manage events', 'events.manage', 'Manage any event on the platform.'),
    ('Approve events', 'events.approve', 'Approve or reject submitted events.'),
    ('Create events', 'events.create', 'Create and submit organizer events.'),
    ('Manage own events', 'events.manage-own', 'Update events owned by the organizer.'),
    ('Browse events', 'events.browse', 'Browse and view published events.'),
    ('Register for events', 'registrations.create', 'Register for available events.'),
    ('Manage participants', 'participants.manage', 'Manage participants for owned events.'),
    ('Scan tickets', 'attendance.scan', 'Validate QR tickets and record attendance.'),
    ('Manage payments', 'payments.manage', 'Inspect and update payment records.'),
    ('View own revenue', 'revenue.view-own', 'View revenue for owned events.'),
    ('View analytics', 'analytics.view', 'View platform analytics.'),
    ('View own analytics', 'analytics.view-own', 'View organizer analytics.'),
    ('Manage CMS', 'cms.manage', 'Manage pages, FAQs, and banners.'),
    ('Manage settings', 'settings.manage', 'Manage platform and SMTP settings.'),
    ('View reports', 'reports.view', 'View and export platform reports.'),
    ('View own reports', 'reports.view-own', 'View and export organizer reports.'),
    ('Manage own tickets', 'tickets.manage-own', 'View and download participant tickets.'),
    ('Manage favorites', 'favorites.manage-own', 'Save and remove favorite events.'),
    ('Manage reviews', 'reviews.manage-own', 'Create and update participant reviews.');

INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug IN (
    'events.create',
    'events.manage-own',
    'participants.manage',
    'attendance.scan',
    'revenue.view-own',
    'analytics.view-own',
    'reports.view-own'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE slug IN (
    'events.browse',
    'registrations.create',
    'tickets.manage-own',
    'favorites.manage-own',
    'reviews.manage-own'
);

INSERT INTO users (
    role_id,
    name,
    email,
    password,
    status,
    email_verified_at,
    created_at,
    updated_at
) VALUES (
    1,
    'OEMS Administrator',
    'admin@oems.local',
    '$2y$10$Ocxf/IY88bqDM/QqAJnjCu.fOuRAp0FuA56DhB9ahEbz.YxP3PEeW',
    'active',
    NOW(),
    NOW(),
    NOW()
);

INSERT INTO profiles (user_id, locale, timezone) VALUES (1, 'en', 'Asia/Dhaka');

INSERT INTO categories (name, slug, description, icon, sort_order) VALUES
    ('Business', 'business', 'Conferences, networking, and professional learning.', 'briefcase', 10),
    ('Technology', 'technology', 'Developer, product, data, and innovation events.', 'microchip', 20),
    ('Arts and Culture', 'arts-culture', 'Exhibitions, performance, film, and literature.', 'palette', 30),
    ('Community', 'community', 'Local groups, social causes, and neighborhood gatherings.', 'people-group', 40),
    ('Education', 'education', 'Workshops, seminars, and skill-building programs.', 'graduation-cap', 50),
    ('Health and Wellness', 'health-wellness', 'Fitness, health, mindfulness, and wellbeing.', 'heart-pulse', 60);

INSERT INTO payment_methods (name, slug, configuration, is_active, sort_order) VALUES
    ('Free registration', 'free', JSON_OBJECT(), TRUE, 10),
    (
        'Manual payment',
        'manual',
        JSON_OBJECT(
            'instructions',
            'DEMO ONLY: use a fictional reference such as OEMS-DEMO-REFERENCE-001. Do not send money or enter real account details.',
            'account_title',
            'OEMS Demo Payments',
            'account_identifier',
            'DEMO-NOT-A-REAL-ACCOUNT',
            'review_mode',
            'administrator_manual_review'
        ),
        FALSE,
        20
    );

INSERT INTO settings (`group`, `key`, `value`, value_type, is_public) VALUES
    ('general', 'site_name', 'OEMS', 'string', TRUE),
    ('general', 'site_tagline', 'Find your next room full of ideas.', 'string', TRUE),
    ('general', 'default_currency', 'BDT', 'string', TRUE),
    ('general', 'maintenance_mode', 'false', 'boolean', FALSE),
    ('mail', 'mail_driver', 'smtp', 'string', FALSE),
    ('mail', 'mail_from_address', 'hello@oems.local', 'string', FALSE),
    ('security', 'login_attempt_limit', '5', 'integer', FALSE),
    ('security', 'login_lockout_minutes', '15', 'integer', FALSE);

INSERT INTO pages (title, slug, content, status, published_at, created_by, updated_by) VALUES
    ('About', 'about', '<p>OEMS connects people with meaningful events and helps organizers run them confidently.</p>', 'published', NOW(), 1, 1),
    ('Privacy', 'privacy', '<p>OEMS handles account and event data according to the published privacy policy.</p>', 'published', NOW(), 1, 1),
    ('Terms', 'terms', '<p>Use of OEMS is subject to the platform terms and organizer policies.</p>', 'published', NOW(), 1, 1);

-- Development Super Admin login:
-- Email: admin@oems.local
-- Password: ChangeMe!2026
-- Change this password immediately outside local development.
