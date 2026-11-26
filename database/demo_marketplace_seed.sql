-- Synthetic marketplace demo data for local development only.
-- Safe to re-run: demo users, applications and books are keyed by unique emails/ISBNs/slugs.

START TRANSACTION;

INSERT INTO users (role, first_name, last_name, email, phone, password_hash, status)
VALUES ('seller', 'Ama', 'Mensah', 'demo.pending@example.test', '+233240000001', 'demo-seed-account-not-for-login', 'active')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_user_pending = LAST_INSERT_ID();
INSERT INTO sellers (user_id, store_name, seller_type, description, phone, region, city, business_address, verification_status)
VALUES (@seller_user_pending, 'Asempa Reads', 'bookstore', 'A community bookstore focused on Ghanaian literature and thoughtful reading.', '+233240000001', 'Greater Accra', 'Accra', '12 Osu Reading Lane', 'pending')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_pending = LAST_INSERT_ID();
INSERT INTO seller_applications (seller_id, application_data, status)
SELECT @seller_pending, JSON_OBJECT('business_registration_details', 'Demo registration pending review', 'seeded_demo', true), 'pending'
WHERE NOT EXISTS (SELECT 1 FROM seller_applications WHERE seller_id = @seller_pending);

INSERT INTO users (role, first_name, last_name, email, phone, password_hash, status)
VALUES ('seller', 'Kofi', 'Owusu', 'demo.review@example.test', '+233240000002', 'demo-seed-account-not-for-login', 'active')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_user_review = LAST_INSERT_ID();
INSERT INTO sellers (user_id, store_name, seller_type, description, phone, region, city, business_address, verification_status)
VALUES (@seller_user_review, 'Heritage Pages', 'publisher', 'An independent publisher bringing contemporary African voices to readers.', '+233240000002', 'Ashanti', 'Kumasi', '4 Adum Market Street', 'under_review')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_review = LAST_INSERT_ID();
INSERT INTO seller_applications (seller_id, application_data, status, admin_notes)
SELECT @seller_review, JSON_OBJECT('business_registration_details', 'Publisher documentation uploaded for review', 'seeded_demo', true), 'under_review', 'Confirm publisher documentation and business address.'
WHERE NOT EXISTS (SELECT 1 FROM seller_applications WHERE seller_id = @seller_review);

INSERT INTO users (role, first_name, last_name, email, phone, password_hash, status)
VALUES ('seller', 'Nana', 'Boateng', 'demo.approved@example.test', '+233240000003', 'demo-seed-account-not-for-login', 'active')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_user_approved = LAST_INSERT_ID();
INSERT INTO sellers (user_id, store_name, seller_type, description, phone, region, city, business_address, verification_status, verified_at, verified_by)
VALUES (@seller_user_approved, 'Golden Shelf Books', 'distributor', 'A verified distributor supplying educational and general-interest books.', '+233240000003', 'Greater Accra', 'Tema', '8 Harbour Road', 'approved', CURRENT_TIMESTAMP, 1)
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_approved = LAST_INSERT_ID();
INSERT INTO seller_applications (seller_id, application_data, status, reviewed_by, reviewed_at, admin_notes)
SELECT @seller_approved, JSON_OBJECT('business_registration_details', 'Verified distributor demo record', 'seeded_demo', true), 'approved', 1, CURRENT_TIMESTAMP, 'Demo application approved.'
WHERE NOT EXISTS (SELECT 1 FROM seller_applications WHERE seller_id = @seller_approved);

INSERT INTO users (role, first_name, last_name, email, phone, password_hash, status)
VALUES ('seller', 'Esi', 'Adjei', 'demo.rejected@example.test', '+233240000004', 'demo-seed-account-not-for-login', 'active')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_user_rejected = LAST_INSERT_ID();
INSERT INTO sellers (user_id, store_name, seller_type, description, phone, region, city, business_address, verification_status)
VALUES (@seller_user_rejected, 'Coastal Classics', 'individual', 'A small independent seller of pre-loved classics and modern fiction.', '+233240000004', 'Central', 'Cape Coast', '21 Castle View Road', 'rejected')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_rejected = LAST_INSERT_ID();
INSERT INTO seller_applications (seller_id, application_data, status, rejection_reason, reviewed_by, reviewed_at)
SELECT @seller_rejected, JSON_OBJECT('business_registration_details', 'Demo rejected application', 'seeded_demo', true), 'rejected', 'Please provide a clearer business address and verification document.', 1, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM seller_applications WHERE seller_id = @seller_rejected);

INSERT INTO users (role, first_name, last_name, email, phone, password_hash, status)
VALUES ('seller', 'Yaw', 'Asante', 'demo.suspended@example.test', '+233240000005', 'demo-seed-account-not-for-login', 'active')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_user_suspended = LAST_INSERT_ID();
INSERT INTO sellers (user_id, store_name, seller_type, description, phone, region, city, business_address, verification_status)
VALUES (@seller_user_suspended, 'North Star Learning', 'educational_institution', 'Educational resources and revision books for secondary school learners.', '+233240000005', 'Northern', 'Tamale', '3 School Road', 'suspended')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @seller_suspended = LAST_INSERT_ID();
INSERT INTO seller_applications (seller_id, application_data, status, rejection_reason, reviewed_by, reviewed_at)
SELECT @seller_suspended, JSON_OBJECT('business_registration_details', 'Demo suspended seller record', 'seeded_demo', true), 'suspended', 'Seller account suspended pending verification update.', 1, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM seller_applications WHERE seller_id = @seller_suspended);

INSERT INTO seller_notifications (seller_id, title, message, notification_type)
SELECT @seller_pending, 'Application received', 'Your demo seller application is waiting for administrator review.', 'application_received'
WHERE NOT EXISTS (SELECT 1 FROM seller_notifications WHERE seller_id = @seller_pending AND notification_type = 'application_received');
INSERT INTO seller_notifications (seller_id, title, message, notification_type)
SELECT @seller_approved, 'Seller account approved', 'Your demo seller account is approved. Book listings still require book-level approval.', 'application_approved'
WHERE NOT EXISTS (SELECT 1 FROM seller_notifications WHERE seller_id = @seller_approved AND notification_type = 'application_approved');

INSERT INTO books (seller_id, category_id, publisher_id, isbn, title, slug, description, author_text, publication_date, edition, condition_label, language, price, discount_price, stock_quantity, status, submitted_at)
VALUES (@seller_approved, 1, 1, '9780000001001', 'Demo: Voices from the Coast', 'demo-voices-from-the-coast', 'A synthetic demo listing used to exercise the marketplace approval queue.', 'Demo Author', '2024-01-15', 'First edition', 'like_new', 'English', 82.00, 75.00, 12, 'pending_review', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @book_pending = LAST_INSERT_ID();

INSERT INTO books (seller_id, category_id, publisher_id, isbn, title, slug, description, author_text, publication_date, edition, condition_label, language, price, discount_price, stock_quantity, status, submitted_at, review_reason)
VALUES (@seller_approved, 4, 3, '9780000001002', 'Demo: Learning across Ghana', 'demo-learning-across-ghana', 'A synthetic educational listing awaiting detailed moderation.', 'Demo Author', '2023-09-01', 'Second edition', 'new', 'English', 120.00, NULL, 25, 'under_review', CURRENT_TIMESTAMP, 'Administrator is checking category and supporting information.')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @book_under_review = LAST_INSERT_ID();

INSERT INTO books (seller_id, category_id, publisher_id, isbn, title, slug, description, author_text, publication_date, edition, condition_label, language, price, discount_price, stock_quantity, status, submitted_at, review_reason)
VALUES (@seller_approved, 1, 2, '9780000001003', 'Demo: The Revision Shelf', 'demo-the-revision-shelf', 'A synthetic listing with requested corrections for the seller.', 'Demo Author', '2022-05-20', 'First edition', 'good', 'English', 65.00, NULL, 4, 'revision_requested', CURRENT_TIMESTAMP, 'Please add a clearer condition description and supporting documentation.')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @book_revision = LAST_INSERT_ID();

INSERT INTO books (seller_id, category_id, publisher_id, isbn, title, slug, description, author_text, publication_date, edition, condition_label, language, price, discount_price, stock_quantity, status, submitted_at, review_reason)
VALUES (@seller_rejected, 5, 3, '9780000001004', 'Demo: History of the Coast', 'demo-history-of-the-coast', 'A synthetic rejected listing used to demonstrate moderation history.', 'Demo Author', '2020-11-10', 'First edition', 'acceptable', 'English', 48.00, NULL, 0, 'rejected', CURRENT_TIMESTAMP, 'ISBN and condition information require correction.')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @book_rejected = LAST_INSERT_ID();

INSERT INTO books (seller_id, category_id, publisher_id, isbn, title, slug, description, author_text, publication_date, edition, condition_label, language, price, discount_price, stock_quantity, status, submitted_at, review_reason)
VALUES (@seller_suspended, 3, 4, '9780000001005', 'Demo: Ananse Learns to Read', 'demo-ananse-learns-to-read', 'A synthetic suspended listing used to demonstrate seller moderation.', 'Demo Author', '2021-03-03', 'First edition', 'very_good', 'English', 55.00, NULL, 8, 'suspended', CURRENT_TIMESTAMP, 'Listing suspended while seller verification is suspended.')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @book_suspended = LAST_INSERT_ID();

INSERT INTO book_approval_history (book_id, seller_id, administrator_id, previous_status, new_status, action, reason)
SELECT @book_revision, @seller_approved, 1, 'pending_review', 'revision_requested', 'revision', 'Please add a clearer condition description and supporting documentation.'
WHERE NOT EXISTS (SELECT 1 FROM book_approval_history WHERE book_id = @book_revision AND new_status = 'revision_requested');
INSERT INTO book_approval_history (book_id, seller_id, administrator_id, previous_status, new_status, action, reason)
SELECT @book_rejected, @seller_rejected, 1, 'under_review', 'rejected', 'reject', 'ISBN and condition information require correction.'
WHERE NOT EXISTS (SELECT 1 FROM book_approval_history WHERE book_id = @book_rejected AND new_status = 'rejected');
INSERT INTO book_approval_history (book_id, seller_id, administrator_id, previous_status, new_status, action, reason)
SELECT @book_suspended, @seller_suspended, 1, 'approved', 'suspended', 'suspend', 'Listing suspended while seller verification is suspended.'
WHERE NOT EXISTS (SELECT 1 FROM book_approval_history WHERE book_id = @book_suspended AND new_status = 'suspended');

COMMIT;
