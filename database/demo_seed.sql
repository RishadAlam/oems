-- Optional local-development data. Import after schema.sql and seed.sql.
-- Every non-administrator demo account uses the password: DemoPass!2026

START TRANSACTION;

SET @demo_password = '$2y$10$jgpoan2Mw3QGbb/ADEz5UebGZI9U7rGifg/ulZ98qHkt/aQWJqCIS';
SET @admin_password = '$2y$10$Ocxf/IY88bqDM/QqAJnjCu.fOuRAp0FuA56DhB9ahEbz.YxP3PEeW';
SET @admin_user_id = (SELECT id FROM users WHERE email = 'admin@oems.local');
SET @organizer_role_id = (SELECT id FROM roles WHERE slug = 'organizer');
SET @participant_role_id = (SELECT id FROM roles WHERE slug = 'participant');

INSERT INTO payment_methods (name, slug, configuration, is_active, sort_order) VALUES
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
        TRUE,
        20
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    configuration = VALUES(configuration),
    is_active = TRUE,
    sort_order = VALUES(sort_order);

UPDATE users
SET password = @admin_password
WHERE id = @admin_user_id;

INSERT INTO users (role_id, name, email, password, status, email_verified_at) VALUES
    (@organizer_role_id, 'Ayesha Rahman', 'ayesha.organizer@oems.local', @demo_password, 'active', NOW()),
    (@organizer_role_id, 'Farhan Kabir', 'farhan.organizer@oems.local', @demo_password, 'active', NOW()),
    (@organizer_role_id, 'Nusrat Jahan', 'nusrat.organizer@oems.local', @demo_password, 'active', NOW()),
    (@participant_role_id, 'Tahmid Hasan', 'tahmid.participant@oems.local', @demo_password, 'active', NOW()),
    (@participant_role_id, 'Mim Akter', 'mim.participant@oems.local', @demo_password, 'active', NOW()),
    (@participant_role_id, 'Rakib Chowdhury', 'rakib.participant@oems.local', @demo_password, 'active', NOW()),
    (@participant_role_id, 'Sohana Islam', 'sohana.participant@oems.local', @demo_password, 'active', NOW()),
    (@participant_role_id, 'Arif Hossain', 'arif.participant@oems.local', @demo_password, 'active', NOW()),
    (@participant_role_id, 'Nabila Ahmed', 'nabila.participant@oems.local', @demo_password, 'active', NOW()),
    (@participant_role_id, 'Imran Khan', 'imran.participant@oems.local', @demo_password, 'active', NOW()),
    (@participant_role_id, 'Jannat Karim', 'jannat.participant@oems.local', @demo_password, 'active', NOW())
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    name = VALUES(name),
    password = VALUES(password),
    status = VALUES(status),
    email_verified_at = VALUES(email_verified_at);

SET @ayesha_user_id = (SELECT id FROM users WHERE email = 'ayesha.organizer@oems.local');
SET @farhan_user_id = (SELECT id FROM users WHERE email = 'farhan.organizer@oems.local');
SET @nusrat_user_id = (SELECT id FROM users WHERE email = 'nusrat.organizer@oems.local');
SET @tahmid_user_id = (SELECT id FROM users WHERE email = 'tahmid.participant@oems.local');
SET @mim_user_id = (SELECT id FROM users WHERE email = 'mim.participant@oems.local');
SET @rakib_user_id = (SELECT id FROM users WHERE email = 'rakib.participant@oems.local');
SET @sohana_user_id = (SELECT id FROM users WHERE email = 'sohana.participant@oems.local');
SET @arif_user_id = (SELECT id FROM users WHERE email = 'arif.participant@oems.local');
SET @nabila_user_id = (SELECT id FROM users WHERE email = 'nabila.participant@oems.local');
SET @imran_user_id = (SELECT id FROM users WHERE email = 'imran.participant@oems.local');
SET @jannat_user_id = (SELECT id FROM users WHERE email = 'jannat.participant@oems.local');

INSERT INTO profiles (user_id, bio, city, country, locale, timezone) VALUES
    (@ayesha_user_id, 'Technology conference producer and community builder.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@farhan_user_id, 'Business event organizer focused on founders and operators.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@nusrat_user_id, 'Arts, culture, and wellness program curator.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@tahmid_user_id, 'Software engineer interested in product and technology events.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@mim_user_id, 'University student exploring design and entrepreneurship.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@rakib_user_id, 'Community volunteer and lifelong learner.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@sohana_user_id, 'Marketing professional and startup mentor.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@arif_user_id, 'Small business owner building local partnerships.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@nabila_user_id, 'Visual artist and cultural event enthusiast.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@imran_user_id, 'Health educator and community organizer.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka'),
    (@jannat_user_id, 'Product manager interested in leadership and innovation.', 'Dhaka', 'Bangladesh', 'en', 'Asia/Dhaka')
ON DUPLICATE KEY UPDATE
    bio = VALUES(bio),
    city = VALUES(city),
    country = VALUES(country),
    locale = VALUES(locale),
    timezone = VALUES(timezone);

INSERT INTO organizers (
    user_id,
    organization_name,
    description,
    approval_status,
    approved_by,
    approved_at
) VALUES
    (@ayesha_user_id, 'Dhaka Digital Society', 'Large technology conferences and professional meetups.', 'approved', @admin_user_id, '2026-05-12 10:00:00'),
    (@farhan_user_id, 'Founders Forum Bangladesh', 'Practical events for founders, operators, and investors.', 'approved', @admin_user_id, '2026-05-18 11:30:00'),
    (@nusrat_user_id, 'Open City Collective', 'Accessible arts, culture, community, and wellness programs.', 'pending', NULL, NULL)
ON DUPLICATE KEY UPDATE
    organization_name = VALUES(organization_name),
    description = VALUES(description),
    approval_status = VALUES(approval_status),
    approved_by = VALUES(approved_by),
    approved_at = VALUES(approved_at);

SET @ayesha_organizer_id = (SELECT id FROM organizers WHERE user_id = @ayesha_user_id);
SET @farhan_organizer_id = (SELECT id FROM organizers WHERE user_id = @farhan_user_id);
SET @nusrat_organizer_id = (SELECT id FROM organizers WHERE user_id = @nusrat_user_id);

INSERT INTO venues (organizer_id, name, address_line, city, country, postal_code, capacity)
SELECT @ayesha_organizer_id, 'Bangabandhu International Conference Center', 'Sher-e-Bangla Nagar', 'Dhaka', 'Bangladesh', '1207', 1200
WHERE NOT EXISTS (
    SELECT 1 FROM venues
    WHERE name = 'Bangabandhu International Conference Center'
      AND organizer_id = @ayesha_organizer_id
);

INSERT INTO venues (organizer_id, name, address_line, city, country, postal_code, capacity)
SELECT @farhan_organizer_id, 'EMK Center', 'Midas Center, Dhanmondi', 'Dhaka', 'Bangladesh', '1209', 180
WHERE NOT EXISTS (
    SELECT 1 FROM venues
    WHERE name = 'EMK Center'
      AND organizer_id = @farhan_organizer_id
);

INSERT INTO venues (organizer_id, name, address_line, city, country, postal_code, capacity)
SELECT @nusrat_organizer_id, 'Bengal Shilpalay', 'Dhanmondi 27', 'Dhaka', 'Bangladesh', '1209', 300
WHERE NOT EXISTS (
    SELECT 1 FROM venues
    WHERE name = 'Bengal Shilpalay'
      AND organizer_id = @nusrat_organizer_id
);

SET @bic_center_id = (SELECT id FROM venues WHERE name = 'Bangabandhu International Conference Center' AND organizer_id = @ayesha_organizer_id LIMIT 1);
SET @emk_center_id = (SELECT id FROM venues WHERE name = 'EMK Center' AND organizer_id = @farhan_organizer_id LIMIT 1);
SET @bengal_shilpalay_id = (SELECT id FROM venues WHERE name = 'Bengal Shilpalay' AND organizer_id = @nusrat_organizer_id LIMIT 1);
SET @technology_category_id = (SELECT id FROM categories WHERE slug = 'technology');
SET @business_category_id = (SELECT id FROM categories WHERE slug = 'business');
SET @arts_category_id = (SELECT id FROM categories WHERE slug = 'arts-culture');
SET @education_category_id = (SELECT id FROM categories WHERE slug = 'education');
SET @wellness_category_id = (SELECT id FROM categories WHERE slug = 'health-wellness');

INSERT INTO events (
    organizer_id, category_id, venue_id, title, slug, description, banner,
    location_visibility, arrival_notes, speaker,
    start_date, end_date, registration_deadline, capacity, available_seats,
    ticket_price, currency, tags, status, approved_by, approved_at,
    published_at, is_featured
) VALUES
    (
        @ayesha_organizer_id, @technology_category_id, @bic_center_id,
        'Dhaka Tech Summit 2026', 'dhaka-tech-summit-2026',
        'A full-day gathering for developers, product teams, and technology leaders.',
        '/assets/images/hero-events.webp', 'public',
        'Use the main entrance and check in at the lobby desk from 8:30 AM.',
        'Multiple industry speakers', '2026-09-18 09:00:00', '2026-09-18 18:00:00',
        '2026-09-15 23:59:00', 200, 197, 2500.00, 'BDT',
        JSON_ARRAY('technology', 'software', 'product'), 'published',
        @admin_user_id, '2026-06-10 10:00:00', '2026-06-11 09:00:00', TRUE
    ),
    (
        @farhan_organizer_id, @business_category_id, @emk_center_id,
        'Startup Growth Forum 2026', 'startup-growth-forum-2026',
        'Focused sessions on early growth, fundraising, operations, and sustainable teams.',
        '/assets/images/event-creative.webp', 'public',
        'Enter through the Midas Center reception and take the lift to the event floor.',
        'Founders Forum faculty', '2026-10-05 10:00:00', '2026-10-05 17:00:00',
        '2026-10-02 23:59:00', 120, 118, 1200.00, 'BDT',
        JSON_ARRAY('startup', 'business', 'fundraising'), 'published',
        @admin_user_id, '2026-06-15 14:00:00', '2026-06-16 09:00:00', TRUE
    ),
    (
        @ayesha_organizer_id, @arts_category_id, @bic_center_id,
        'Community Arts Night 2026', 'community-arts-night-2026',
        'An evening of local visual art, short film, music, and conversation.',
        '/assets/images/event-community.webp', 'public',
        'Doors open at 4:45 PM; follow gallery signs from the conference center lobby.',
        'Open City artists', '2026-08-29 17:00:00', '2026-08-29 21:30:00',
        '2026-08-28 17:00:00', 180, 178, 0.00, 'BDT',
        JSON_ARRAY('arts', 'film', 'community'), 'published',
        @admin_user_id, '2026-07-01 12:00:00', '2026-07-02 10:00:00', FALSE
    ),
    (
        @farhan_organizer_id, @education_category_id, @emk_center_id,
        'Future Skills Workshop 2026', 'future-skills-workshop-2026',
        'A practical workshop on communication, AI literacy, and collaborative problem solving.',
        '/assets/images/event-creative.webp', 'public',
        'Please arrive 15 minutes early and bring a laptop for the workshop sessions.',
        'Farhan Kabir', '2026-09-10 10:00:00', '2026-09-10 16:00:00',
        '2026-09-08 23:59:00', 80, 79, 700.00, 'BDT',
        JSON_ARRAY('education', 'skills', 'workshop'), 'pending',
        NULL, NULL, NULL, FALSE
    ),
    (
        @nusrat_organizer_id, @wellness_category_id, @bengal_shilpalay_id,
        'Wellness Weekend Dhaka 2026', 'wellness-weekend-dhaka-2026',
        'A weekend program covering movement, mindfulness, nutrition, and community wellbeing.',
        '/assets/images/event-community.webp', 'public',
        'Registration opens at 7:30 AM; use the Dhanmondi 27 entrance.',
        'Open City wellness team', '2026-11-14 08:00:00', '2026-11-15 17:00:00',
        '2026-11-10 23:59:00', 150, 150, 900.00, 'BDT',
        JSON_ARRAY('wellness', 'health', 'mindfulness'), 'draft',
        NULL, NULL, NULL, FALSE
    ),
    (
        @ayesha_organizer_id, @business_category_id, @bic_center_id,
        'Product Leaders Meetup July 2026', 'product-leaders-meetup-july-2026',
        'A completed roundtable on product strategy, research, and team leadership.',
        '/assets/images/event-creative.webp', 'public',
        'Check-in began at 5:30 PM at the lobby desk for this completed meetup.',
        'Ayesha Rahman', '2026-07-20 18:00:00', '2026-07-20 21:00:00',
        '2026-07-19 18:00:00', 60, 56, 800.00, 'BDT',
        JSON_ARRAY('product', 'leadership', 'meetup'), 'completed',
        @admin_user_id, '2026-06-20 11:00:00', '2026-06-21 09:00:00', FALSE
    )
ON DUPLICATE KEY UPDATE
    organizer_id = VALUES(organizer_id),
    category_id = VALUES(category_id),
    venue_id = VALUES(venue_id),
    title = VALUES(title),
    description = VALUES(description),
    banner = VALUES(banner),
    location_visibility = VALUES(location_visibility),
    arrival_notes = VALUES(arrival_notes),
    speaker = VALUES(speaker),
    start_date = VALUES(start_date),
    end_date = VALUES(end_date),
    registration_deadline = VALUES(registration_deadline),
    capacity = VALUES(capacity),
    available_seats = VALUES(available_seats),
    ticket_price = VALUES(ticket_price),
    currency = VALUES(currency),
    tags = VALUES(tags),
    status = VALUES(status),
    approved_by = VALUES(approved_by),
    approved_at = VALUES(approved_at),
    published_at = VALUES(published_at),
    is_featured = VALUES(is_featured);

SET @tech_event_id = (SELECT id FROM events WHERE slug = 'dhaka-tech-summit-2026');
SET @startup_event_id = (SELECT id FROM events WHERE slug = 'startup-growth-forum-2026');
SET @arts_event_id = (SELECT id FROM events WHERE slug = 'community-arts-night-2026');
SET @skills_event_id = (SELECT id FROM events WHERE slug = 'future-skills-workshop-2026');
SET @wellness_event_id = (SELECT id FROM events WHERE slug = 'wellness-weekend-dhaka-2026');
SET @product_event_id = (SELECT id FROM events WHERE slug = 'product-leaders-meetup-july-2026');

INSERT INTO event_gallery (event_id, image_path, alt_text, sort_order)
SELECT @tech_event_id, '/assets/images/event-creative.webp', 'People collaborating at a technology event', 10
WHERE NOT EXISTS (
    SELECT 1 FROM event_gallery
    WHERE event_id = @tech_event_id AND image_path = '/assets/images/event-creative.webp'
);

INSERT INTO event_gallery (event_id, image_path, alt_text, sort_order)
SELECT @tech_event_id, '/assets/images/event-community.webp', 'Attendees gathering for a community session', 20
WHERE NOT EXISTS (
    SELECT 1 FROM event_gallery
    WHERE event_id = @tech_event_id AND image_path = '/assets/images/event-community.webp'
);

INSERT INTO event_gallery (event_id, image_path, alt_text, sort_order)
SELECT @startup_event_id, '/assets/images/event-community.webp', 'Founder community members in conversation', 10
WHERE NOT EXISTS (
    SELECT 1 FROM event_gallery
    WHERE event_id = @startup_event_id AND image_path = '/assets/images/event-community.webp'
);

INSERT INTO event_gallery (event_id, image_path, alt_text, sort_order)
SELECT @arts_event_id, '/assets/images/event-creative.webp', 'Creative program stage and audience', 10
WHERE NOT EXISTS (
    SELECT 1 FROM event_gallery
    WHERE event_id = @arts_event_id AND image_path = '/assets/images/event-creative.webp'
);

INSERT INTO event_schedule (event_id, title, description, speaker, starts_at, ends_at, sort_order)
SELECT @tech_event_id, 'Opening keynote', 'Platform engineering and responsible innovation.', 'Guest keynote speaker', '2026-09-18 09:30:00', '2026-09-18 10:30:00', 10
WHERE NOT EXISTS (SELECT 1 FROM event_schedule WHERE event_id = @tech_event_id AND title = 'Opening keynote');

INSERT INTO event_schedule (event_id, title, description, speaker, starts_at, ends_at, sort_order)
SELECT @tech_event_id, 'Product systems panel', 'How product, design, and engineering teams make decisions.', 'Industry panel', '2026-09-18 14:00:00', '2026-09-18 15:00:00', 20
WHERE NOT EXISTS (SELECT 1 FROM event_schedule WHERE event_id = @tech_event_id AND title = 'Product systems panel');

INSERT INTO event_schedule (event_id, title, description, speaker, starts_at, ends_at, sort_order)
SELECT @startup_event_id, 'Founder operations', 'Building a durable operating cadence for a growing company.', 'Farhan Kabir', '2026-10-05 10:30:00', '2026-10-05 11:30:00', 10
WHERE NOT EXISTS (SELECT 1 FROM event_schedule WHERE event_id = @startup_event_id AND title = 'Founder operations');

INSERT INTO event_schedule (event_id, title, description, speaker, starts_at, ends_at, sort_order)
SELECT @startup_event_id, 'Capital and growth panel', 'A practical discussion on funding choices and sustainable growth.', 'Founder panel', '2026-10-05 14:00:00', '2026-10-05 15:00:00', 20
WHERE NOT EXISTS (SELECT 1 FROM event_schedule WHERE event_id = @startup_event_id AND title = 'Capital and growth panel');

INSERT INTO event_schedule (event_id, title, description, speaker, starts_at, ends_at, sort_order)
SELECT @arts_event_id, 'Gallery and short film program', 'Local art exhibition followed by a curated short film screening.', 'Open City artists', '2026-08-29 17:30:00', '2026-08-29 19:30:00', 10
WHERE NOT EXISTS (SELECT 1 FROM event_schedule WHERE event_id = @arts_event_id AND title = 'Gallery and short film program');

INSERT INTO event_schedule (event_id, title, description, speaker, starts_at, ends_at, sort_order)
SELECT @skills_event_id, 'Applied AI literacy', 'Hands-on exercises for evaluating and using AI tools responsibly.', 'Farhan Kabir', '2026-09-10 11:00:00', '2026-09-10 12:30:00', 10
WHERE NOT EXISTS (SELECT 1 FROM event_schedule WHERE event_id = @skills_event_id AND title = 'Applied AI literacy');

INSERT INTO event_schedule (event_id, title, description, speaker, starts_at, ends_at, sort_order)
SELECT @wellness_event_id, 'Mindful movement', 'A guided morning movement and breathing session.', 'Open City wellness team', '2026-11-14 08:30:00', '2026-11-14 09:30:00', 10
WHERE NOT EXISTS (SELECT 1 FROM event_schedule WHERE event_id = @wellness_event_id AND title = 'Mindful movement');

INSERT INTO event_schedule (event_id, title, description, speaker, starts_at, ends_at, sort_order)
SELECT @product_event_id, 'Product leadership roundtable', 'Small-group discussion on research, strategy, and team health.', 'Ayesha Rahman', '2026-07-20 18:30:00', '2026-07-20 20:00:00', 10
WHERE NOT EXISTS (SELECT 1 FROM event_schedule WHERE event_id = @product_event_id AND title = 'Product leadership roundtable');

INSERT INTO registrations (
    event_id, user_id, registration_number, status, amount, currency, registered_at
) VALUES
    (@tech_event_id, @tahmid_user_id, 'OEMS-DEMO-REG-001', 'confirmed', 2500.00, 'BDT', '2026-07-05 10:00:00'),
    (@tech_event_id, @mim_user_id, 'OEMS-DEMO-REG-002', 'confirmed', 2500.00, 'BDT', '2026-07-06 11:00:00'),
    (@tech_event_id, @rakib_user_id, 'OEMS-DEMO-REG-003', 'confirmed', 2500.00, 'BDT', '2026-07-07 12:00:00'),
    (@startup_event_id, @sohana_user_id, 'OEMS-DEMO-REG-004', 'confirmed', 1200.00, 'BDT', '2026-07-08 09:00:00'),
    (@startup_event_id, @arif_user_id, 'OEMS-DEMO-REG-005', 'confirmed', 1200.00, 'BDT', '2026-07-09 13:00:00'),
    (@arts_event_id, @nabila_user_id, 'OEMS-DEMO-REG-006', 'confirmed', 0.00, 'BDT', '2026-07-10 15:00:00'),
    (@arts_event_id, @imran_user_id, 'OEMS-DEMO-REG-007', 'confirmed', 0.00, 'BDT', '2026-07-11 16:00:00'),
    (@product_event_id, @jannat_user_id, 'OEMS-DEMO-REG-008', 'confirmed', 800.00, 'BDT', '2026-07-12 17:00:00'),
    (@product_event_id, @tahmid_user_id, 'OEMS-DEMO-REG-009', 'confirmed', 800.00, 'BDT', '2026-07-13 10:00:00'),
    (@product_event_id, @mim_user_id, 'OEMS-DEMO-REG-010', 'confirmed', 800.00, 'BDT', '2026-07-13 11:00:00'),
    (@product_event_id, @sohana_user_id, 'OEMS-DEMO-REG-011', 'confirmed', 800.00, 'BDT', '2026-07-13 12:00:00'),
    (@skills_event_id, @rakib_user_id, 'OEMS-DEMO-REG-012', 'pending', 700.00, 'BDT', '2026-08-02 10:00:00')
ON DUPLICATE KEY UPDATE
    event_id = VALUES(event_id),
    user_id = VALUES(user_id),
    status = VALUES(status),
    amount = VALUES(amount),
    currency = VALUES(currency),
    registered_at = VALUES(registered_at);

UPDATE events AS demo_event
SET demo_event.available_seats = GREATEST(
    demo_event.capacity - (
        SELECT COUNT(*)
        FROM registrations AS demo_registration
        WHERE demo_registration.event_id = demo_event.id
          AND demo_registration.status IN ('pending', 'confirmed')
    ),
    0
)
WHERE demo_event.id IN (
    @tech_event_id,
    @startup_event_id,
    @arts_event_id,
    @skills_event_id,
    @wellness_event_id,
    @product_event_id
);

SET @manual_payment_method_id = (SELECT id FROM payment_methods WHERE slug = 'manual');
SET @free_payment_method_id = (SELECT id FROM payment_methods WHERE slug = 'free');

INSERT INTO payments (
    registration_id, payment_method_id, transaction_reference, amount, currency,
    status, gateway_response, paid_at
) VALUES
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-001'), @manual_payment_method_id, 'OEMS-DEMO-PAY-001', 2500.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-05 10:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-002'), @manual_payment_method_id, 'OEMS-DEMO-PAY-002', 2500.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-06 11:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-003'), @manual_payment_method_id, 'OEMS-DEMO-PAY-003', 2500.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-07 12:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-004'), @manual_payment_method_id, 'OEMS-DEMO-PAY-004', 1200.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-08 09:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-005'), @manual_payment_method_id, 'OEMS-DEMO-PAY-005', 1200.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-09 13:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-006'), @free_payment_method_id, 'OEMS-DEMO-PAY-006', 0.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-10 15:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-007'), @free_payment_method_id, 'OEMS-DEMO-PAY-007', 0.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-11 16:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-008'), @manual_payment_method_id, 'OEMS-DEMO-PAY-008', 800.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-12 17:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-009'), @manual_payment_method_id, 'OEMS-DEMO-PAY-009', 800.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-13 10:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-010'), @manual_payment_method_id, 'OEMS-DEMO-PAY-010', 800.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-13 11:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-011'), @manual_payment_method_id, 'OEMS-DEMO-PAY-011', 800.00, 'BDT', 'paid', JSON_OBJECT('source', 'demo'), '2026-07-13 12:05:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-012'), @manual_payment_method_id, 'OEMS-DEMO-PAY-012', 700.00, 'BDT', 'pending', JSON_OBJECT('source', 'demo'), NULL)
ON DUPLICATE KEY UPDATE
    registration_id = VALUES(registration_id),
    payment_method_id = VALUES(payment_method_id),
    amount = VALUES(amount),
    currency = VALUES(currency),
    status = VALUES(status),
    gateway_response = VALUES(gateway_response),
    paid_at = VALUES(paid_at);

INSERT INTO tickets (
    registration_id, ticket_number, qr_payload_hash, qr_path, pdf_path, status, issued_at
) VALUES
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-001'), 'OEMS-DEMO-TKT-001', SHA2('OEMS-DEMO-TKT-001', 256), NULL, NULL, 'valid', '2026-07-05 10:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-002'), 'OEMS-DEMO-TKT-002', SHA2('OEMS-DEMO-TKT-002', 256), NULL, NULL, 'valid', '2026-07-06 11:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-003'), 'OEMS-DEMO-TKT-003', SHA2('OEMS-DEMO-TKT-003', 256), NULL, NULL, 'valid', '2026-07-07 12:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-004'), 'OEMS-DEMO-TKT-004', SHA2('OEMS-DEMO-TKT-004', 256), NULL, NULL, 'valid', '2026-07-08 09:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-005'), 'OEMS-DEMO-TKT-005', SHA2('OEMS-DEMO-TKT-005', 256), NULL, NULL, 'valid', '2026-07-09 13:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-006'), 'OEMS-DEMO-TKT-006', SHA2('OEMS-DEMO-TKT-006', 256), NULL, NULL, 'valid', '2026-07-10 15:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-007'), 'OEMS-DEMO-TKT-007', SHA2('OEMS-DEMO-TKT-007', 256), NULL, NULL, 'valid', '2026-07-11 16:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-008'), 'OEMS-DEMO-TKT-008', SHA2('OEMS-DEMO-TKT-008', 256), NULL, NULL, 'used', '2026-07-12 17:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-009'), 'OEMS-DEMO-TKT-009', SHA2('OEMS-DEMO-TKT-009', 256), NULL, NULL, 'valid', '2026-07-13 10:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-010'), 'OEMS-DEMO-TKT-010', SHA2('OEMS-DEMO-TKT-010', 256), NULL, NULL, 'valid', '2026-07-13 11:06:00'),
    ((SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-011'), 'OEMS-DEMO-TKT-011', SHA2('OEMS-DEMO-TKT-011', 256), NULL, NULL, 'valid', '2026-07-13 12:06:00')
ON DUPLICATE KEY UPDATE
    registration_id = VALUES(registration_id),
    qr_payload_hash = VALUES(qr_payload_hash),
    qr_path = VALUES(qr_path),
    pdf_path = VALUES(pdf_path),
    status = VALUES(status),
    issued_at = VALUES(issued_at);

INSERT INTO attendance (
    registration_id, ticket_id, scanned_by, status, scanned_at, scanner_ip
) VALUES
    (
        (SELECT id FROM registrations WHERE registration_number = 'OEMS-DEMO-REG-008'),
        (SELECT id FROM tickets WHERE ticket_number = 'OEMS-DEMO-TKT-008'),
        @ayesha_user_id,
        'present',
        '2026-07-20 18:35:00',
        '203.0.113.8'
    )
ON DUPLICATE KEY UPDATE
    registration_id = VALUES(registration_id),
    ticket_id = VALUES(ticket_id),
    scanned_by = VALUES(scanned_by),
    status = VALUES(status),
    scanned_at = VALUES(scanned_at),
    scanner_ip = VALUES(scanner_ip);

INSERT INTO favorites (user_id, event_id) VALUES
    (@tahmid_user_id, @startup_event_id),
    (@mim_user_id, @arts_event_id),
    (@rakib_user_id, @tech_event_id),
    (@sohana_user_id, @tech_event_id),
    (@arif_user_id, @startup_event_id),
    (@nabila_user_id, @wellness_event_id),
    (@imran_user_id, @arts_event_id),
    (@jannat_user_id, @skills_event_id)
ON DUPLICATE KEY UPDATE created_at = created_at;

INSERT INTO notifications (user_id, type, title, message, action_url, data)
SELECT @tahmid_user_id, 'registration.confirmed', 'Registration confirmed', 'Your Dhaka Tech Summit registration is confirmed.', '/events/dhaka-tech-summit-2026', JSON_OBJECT('registration_number', 'OEMS-DEMO-REG-001')
WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE user_id = @tahmid_user_id AND title = 'Registration confirmed');

INSERT INTO notifications (user_id, type, title, message, action_url, data)
SELECT @mim_user_id, 'ticket.ready', 'Ticket ready', 'Your event ticket is ready to download.', '/participant/dashboard', JSON_OBJECT('ticket_number', 'OEMS-DEMO-TKT-002')
WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE user_id = @mim_user_id AND title = 'Ticket ready');

INSERT INTO notifications (user_id, type, title, message, action_url, data)
SELECT @rakib_user_id, 'registration.pending', 'Registration pending', 'Your Future Skills Workshop registration is awaiting confirmation.', '/events/future-skills-workshop-2026', JSON_OBJECT('registration_number', 'OEMS-DEMO-REG-012')
WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE user_id = @rakib_user_id AND title = 'Registration pending');

INSERT INTO notifications (user_id, type, title, message, action_url, data)
SELECT @ayesha_user_id, 'event.published', 'Event published', 'Dhaka Tech Summit 2026 is now visible to participants.', '/events/dhaka-tech-summit-2026', JSON_OBJECT('event_id', @tech_event_id)
WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE user_id = @ayesha_user_id AND title = 'Event published');

INSERT INTO notifications (user_id, type, title, message, action_url, data)
SELECT @farhan_user_id, 'event.registration', 'New registrations', 'Startup Growth Forum has new confirmed registrations.', '/organizer/dashboard', JSON_OBJECT('event_id', @startup_event_id)
WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE user_id = @farhan_user_id AND title = 'New registrations');

INSERT INTO notifications (user_id, type, title, message, action_url, data)
SELECT @nusrat_user_id, 'organizer.review', 'Organizer review in progress', 'Your organizer profile is awaiting administrator review.', '/organizer/dashboard', JSON_OBJECT('organizer_id', @nusrat_organizer_id)
WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE user_id = @nusrat_user_id AND title = 'Organizer review in progress');

INSERT INTO reviews (event_id, user_id, rating, review, status) VALUES
    (@product_event_id, @jannat_user_id, 5, 'Focused discussion, strong facilitation, and useful peer perspectives.', 'published'),
    (@product_event_id, @tahmid_user_id, 4, 'A practical meetup with thoughtful examples from local teams.', 'published'),
    (@product_event_id, @mim_user_id, 5, 'The small-group format made the leadership topics easy to discuss.', 'published'),
    (@product_event_id, @sohana_user_id, 4, 'Well organized and valuable for people moving into product leadership.', 'published')
ON DUPLICATE KEY UPDATE
    rating = VALUES(rating),
    review = VALUES(review),
    status = VALUES(status);

COMMIT;
