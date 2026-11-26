CREATE DATABASE IF NOT EXISTS bookstore_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bookstore_db;

SET NAMES utf8mb4;
-- Import into a new bookstore_db. This installer intentionally does not drop existing data.

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role ENUM('customer', 'admin', 'seller') NOT NULL DEFAULT 'customer',
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(254) NOT NULL,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  session_version INT UNSIGNED NOT NULL DEFAULT 0,
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status),
  KEY idx_users_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_hash BINARY(32) NOT NULL,
  ip_address VARBINARY(16) NOT NULL,
  action ENUM('login', 'registration') NOT NULL,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auth_attempts_email_time (email_hash, action, attempted_at, was_successful),
  KEY idx_auth_attempts_ip_time (ip_address, action, attempted_at, was_successful),
  KEY idx_auth_attempts_time (attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(150) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_parent_active (parent_id, is_active),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS authors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  biography TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_authors_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS publishers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  website VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_publishers_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id BIGINT UNSIGNED NULL,
  publisher_id BIGINT UNSIGNED NULL,
  isbn VARCHAR(20) NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(280) NOT NULL,
  description TEXT NOT NULL,
  cover_image VARCHAR(500) NULL,
  publication_date DATE NULL,
  language VARCHAR(60) NOT NULL DEFAULT 'English',
  page_count SMALLINT UNSIGNED NULL,
  price DECIMAL(10,2) NOT NULL,
  discount_price DECIMAL(10,2) NULL,
  stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5,
  status ENUM('draft', 'pending_review', 'under_review', 'approved', 'rejected', 'revision_requested', 'suspended', 'active', 'archived') NOT NULL DEFAULT 'draft',
  view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_books_isbn (isbn),
  UNIQUE KEY uq_books_slug (slug),
  KEY idx_books_catalogue (status, category_id, created_at),
  KEY idx_books_price (status, price),
  KEY idx_books_publisher (publisher_id),
  FULLTEXT KEY ft_books_search (title, description),
  CONSTRAINT fk_books_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (id) ON DELETE SET NULL,
  CONSTRAINT chk_books_price CHECK (price >= 0),
  CONSTRAINT chk_books_discount_price CHECK (discount_price IS NULL OR (discount_price >= 0 AND discount_price < price))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS book_authors (
  book_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  author_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (book_id, author_id),
  KEY idx_book_authors_author (author_id),
  CONSTRAINT fk_book_authors_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE,
  CONSTRAINT fk_book_authors_author FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS carts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  session_token CHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_user (user_id),
  UNIQUE KEY uq_carts_session (session_token),
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cart_items (
  cart_id BIGINT UNSIGNED NOT NULL,
  book_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (cart_id, book_id),
  KEY idx_cart_items_book (book_id),
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE,
  CONSTRAINT chk_cart_items_quantity CHECK (quantity > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wishlists (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT 'My wishlist',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wishlists_user_name (user_id, name),
  CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wishlist_items (
  wishlist_id BIGINT UNSIGNED NOT NULL,
  book_id BIGINT UNSIGNED NOT NULL,
  added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (wishlist_id, book_id),
  KEY idx_wishlist_items_book (book_id),
  CONSTRAINT fk_wishlist_items_wishlist FOREIGN KEY (wishlist_id) REFERENCES wishlists (id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_items_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS addresses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  recipient_name VARCHAR(200) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  region VARCHAR(100) NOT NULL,
  city VARCHAR(120) NOT NULL,
  address_line VARCHAR(255) NOT NULL,
  landmark VARCHAR(255) NULL,
  delivery_instructions TEXT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_addresses_user (user_id, is_default),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coupons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  discount_type ENUM('fixed', 'percentage') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  minimum_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  maximum_discount DECIMAL(10,2) NULL,
  usage_limit INT UNSIGNED NULL,
  per_user_limit INT UNSIGNED NULL,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupons_code (code),
  KEY idx_coupons_active_dates (is_active, starts_at, expires_at),
  CONSTRAINT chk_coupons_value CHECK (discount_value > 0),
  CONSTRAINT chk_coupons_minimum CHECK (minimum_subtotal >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(32) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  coupon_id BIGINT UNSIGNED NULL,
  currency CHAR(3) NOT NULL DEFAULT 'GHS',
  subtotal DECIMAL(10,2) NOT NULL,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('cash_on_delivery', 'mtn_momo', 'telecel_cash', 'at_money', 'paystack', 'hubtel') NOT NULL,
  payment_status ENUM('unpaid', 'pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'unpaid',
  order_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  recipient_name VARCHAR(200) NOT NULL,
  recipient_email VARCHAR(254) NOT NULL,
  recipient_phone VARCHAR(30) NOT NULL,
  delivery_region VARCHAR(100) NOT NULL,
  delivery_city VARCHAR(120) NOT NULL,
  delivery_address VARCHAR(255) NOT NULL,
  delivery_landmark VARCHAR(255) NULL,
  delivery_instructions TEXT NULL,
  placed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_order_number (order_number),
  KEY idx_orders_customer_date (user_id, placed_at),
  KEY idx_orders_status_date (order_status, placed_at),
  KEY idx_orders_payment_status (payment_status, placed_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL,
  CONSTRAINT chk_orders_amounts CHECK (
    subtotal >= 0 AND discount_amount >= 0 AND delivery_fee >= 0
    AND tax_amount >= 0 AND total_amount >= 0
  )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  book_id BIGINT UNSIGNED NULL,
  book_title VARCHAR(255) NOT NULL,
  isbn VARCHAR(20) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id),
  KEY idx_order_items_book (book_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE SET NULL,
  CONSTRAINT chk_order_items_values CHECK (quantity > 0 AND unit_price >= 0 AND line_total >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(40) NOT NULL,
  provider_reference VARCHAR(150) NULL,
  idempotency_key VARCHAR(100) NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'GHS',
  status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  provider_response JSON NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_provider_reference (provider, provider_reference),
  UNIQUE KEY uq_payments_idempotency_key (idempotency_key),
  KEY idx_payments_order_status (order_id, status),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT,
  CONSTRAINT chk_payments_amount CHECK (amount >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shipments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  carrier VARCHAR(120) NULL,
  tracking_number VARCHAR(120) NULL,
  status ENUM('pending', 'shipped', 'in_transit', 'delivered', 'failed', 'returned') NOT NULL DEFAULT 'pending',
  shipped_at DATETIME NULL,
  delivered_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shipments_order (order_id),
  KEY idx_shipments_tracking (tracking_number),
  CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coupon_usages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  discount_amount DECIMAL(10,2) NOT NULL,
  used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupon_usages_order (order_id),
  KEY idx_coupon_usages_user_coupon (user_id, coupon_id),
  CONSTRAINT fk_coupon_usages_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE RESTRICT,
  CONSTRAINT fk_coupon_usages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_coupon_usages_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  book_id BIGINT UNSIGNED NOT NULL,
  administrator_id BIGINT UNSIGNED NULL,
  movement_type ENUM('addition', 'sale', 'return', 'damage', 'adjustment', 'cancellation') NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  previous_stock INT UNSIGNED NOT NULL,
  new_stock INT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inventory_book_date (book_id, created_at),
  KEY idx_inventory_type_date (movement_type, created_at),
  CONSTRAINT fk_inventory_logs_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_logs_admin FOREIGN KEY (administrator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  book_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  review_text TEXT NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reviews_order_book (order_id, book_id),
  KEY idx_reviews_book_status (book_id, status, created_at),
  KEY idx_reviews_user (user_id, created_at),
  CONSTRAINT fk_reviews_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_moderator FOREIGN KEY (moderated_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(254) NOT NULL,
  phone VARCHAR(30) NULL,
  subject VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new', 'in_progress', 'resolved', 'spam') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_status_date (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(254) NOT NULL,
  status ENUM('subscribed', 'unsubscribed') NOT NULL DEFAULT 'subscribed',
  subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  ip_address VARBINARY(16) NULL,
  details JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_user_date (user_id, created_at),
  KEY idx_activity_entity (entity_type, entity_id, created_at),
  CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_zones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  region VARCHAR(100) NOT NULL,
  city VARCHAR(120) NOT NULL DEFAULT '*',
  fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_zone (region, city),
  CONSTRAINT chk_delivery_zone_fee CHECK (fee >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sellers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  store_name VARCHAR(180) NOT NULL,
  seller_type ENUM('individual', 'bookstore', 'publisher', 'distributor', 'educational_institution', 'other') NOT NULL,
  description TEXT NULL,
  business_logo VARCHAR(500) NULL,
  phone VARCHAR(30) NULL,
  region VARCHAR(100) NOT NULL,
  city VARCHAR(120) NOT NULL,
  business_address VARCHAR(255) NOT NULL,
  verification_status ENUM('pending', 'under_review', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
  verified_at DATETIME NULL,
  verified_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sellers_user (user_id),
  KEY idx_sellers_status (verification_status, created_at),
  CONSTRAINT fk_sellers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sellers_verified_by FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seller_applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id BIGINT UNSIGNED NOT NULL,
  application_data JSON NOT NULL,
  status ENUM('pending', 'under_review', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
  admin_notes TEXT NULL,
  rejection_reason TEXT NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_seller_applications_status (status, submitted_at),
  KEY idx_seller_applications_seller (seller_id, submitted_at),
  CONSTRAINT fk_seller_applications_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_seller_applications_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE books
  ADD COLUMN seller_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN author_text VARCHAR(255) NULL AFTER title,
  ADD COLUMN edition VARCHAR(100) NULL AFTER publication_date,
  ADD COLUMN condition_label ENUM('new', 'like_new', 'very_good', 'good', 'acceptable') NULL AFTER edition,
  ADD COLUMN supporting_document VARCHAR(500) NULL AFTER cover_image,
  ADD COLUMN submitted_at DATETIME NULL AFTER updated_at,
  ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER submitted_at,
  ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
  ADD COLUMN review_reason TEXT NULL AFTER reviewed_at,
  ADD KEY idx_books_seller_status (seller_id, status, created_at),
  ADD KEY idx_books_review_queue (status, submitted_at),
  ADD CONSTRAINT fk_books_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_books_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS book_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  book_id BIGINT UNSIGNED NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_book_images_book (book_id, sort_order),
  CONSTRAINT fk_book_images_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS book_approval_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  book_id BIGINT UNSIGNED NOT NULL,
  seller_id BIGINT UNSIGNED NULL,
  administrator_id BIGINT UNSIGNED NULL,
  previous_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  action VARCHAR(40) NOT NULL,
  reason TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_book_approval_history_book (book_id, created_at),
  KEY idx_book_approval_history_seller (seller_id, created_at),
  CONSTRAINT fk_book_approval_history_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE RESTRICT,
  CONSTRAINT fk_book_approval_history_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE SET NULL,
  CONSTRAINT fk_book_approval_history_admin FOREIGN KEY (administrator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE order_items
  ADD COLUMN seller_id BIGINT UNSIGNED NULL AFTER book_id,
  ADD COLUMN commission_rate DECIMAL(5,2) NULL AFTER line_total,
  ADD COLUMN commission_amount DECIMAL(10,2) NULL AFTER commission_rate,
  ADD COLUMN seller_net_amount DECIMAL(10,2) NULL AFTER commission_amount,
  ADD KEY idx_order_items_seller (seller_id, order_id),
  ADD CONSTRAINT fk_order_items_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS seller_order_fulfillments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  seller_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  carrier VARCHAR(120) NULL,
  tracking_number VARCHAR(120) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seller_order_fulfillment (order_id, seller_id),
  KEY idx_seller_fulfillments_seller_status (seller_id, status, updated_at),
  CONSTRAINT fk_seller_fulfillments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT,
  CONSTRAINT fk_seller_fulfillments_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seller_commissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  gross_amount DECIMAL(10,2) NOT NULL,
  commission_rate DECIMAL(5,2) NOT NULL,
  commission_amount DECIMAL(10,2) NOT NULL,
  seller_net_amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending', 'available', 'paid', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seller_commissions_order_item (order_item_id),
  KEY idx_seller_commissions_seller_status (seller_id, status, created_at),
  CONSTRAINT fk_seller_commissions_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_seller_commissions_order_item FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE RESTRICT,
  CONSTRAINT chk_seller_commissions_amounts CHECK (gross_amount >= 0 AND commission_rate >= 0 AND commission_amount >= 0 AND seller_net_amount >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seller_payouts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(60) NOT NULL,
  transaction_reference VARCHAR(150) NULL,
  status ENUM('requested', 'approved', 'rejected', 'processing', 'completed') NOT NULL DEFAULT 'requested',
  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  processed_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_seller_payouts_seller_status (seller_id, status, requested_at),
  CONSTRAINT fk_seller_payouts_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_seller_payouts_processed_by FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_seller_payouts_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seller_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  notification_type VARCHAR(60) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_seller_notifications (seller_id, is_read, created_at),
  CONSTRAINT fk_seller_notifications_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS marketplace_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL,
  setting_value VARCHAR(500) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_marketplace_settings_key (setting_key)
) ENGINE=InnoDB;

INSERT IGNORE INTO marketplace_settings (setting_key, setting_value) VALUES ('default_commission_rate', '10.00');

INSERT IGNORE INTO categories (name, slug, description) VALUES
  ('Fiction', 'fiction', 'Stories across literary and popular fiction.'),
  ('Business', 'business', 'Entrepreneurship, leadership, and finance.'),
  ('Children', 'children', 'Books for young readers.'),
  ('Education', 'education', 'Learning resources and academic books.'),
  ('History', 'history', 'Ghanaian, African, and world history.'),
  ('Personal Development', 'personal-development', 'Practical guides for personal growth.');

INSERT IGNORE INTO authors (name) VALUES
  ('Ama Ata Aidoo'),
  ('Ayi Kwei Armah'),
  ('Chinua Achebe'),
  ('Kwame Nkrumah'),
  ('Efua Sutherland'),
  ('Kofi Awoonor');

INSERT IGNORE INTO publishers (name, website) VALUES
  ('Heinemann', NULL),
  ('African Writers Series', NULL),
  ('Ghana University Press', NULL),
  ('Anansesem Press', NULL);

INSERT IGNORE INTO books
  (category_id, publisher_id, isbn, title, slug, description, publication_date, page_count, price, discount_price, stock_quantity, status)
VALUES
  (1, 1, '9780435909918', 'The Dilemma of a Ghost', 'the-dilemma-of-a-ghost', 'A celebrated play exploring identity, family, and cultural expectations.', '1965-01-01', 80, 55.00, NULL, 18, 'active'),
  (1, 2, '9780435905287', 'The Beautyful Ones Are Not Yet Born', 'the-beautyful-ones-are-not-yet-born', 'A landmark Ghanaian novel about conscience and social change.', '1968-01-01', 208, 78.00, 70.00, 14, 'active'),
  (1, 2, '9780435905256', 'Things Fall Apart', 'things-fall-apart', 'A classic novel of community, change, and colonial encounter.', '1958-01-01', 209, 72.00, NULL, 24, 'active'),
  (5, 3, '9789988550001', 'Africa Must Unite', 'africa-must-unite', 'Kwame Nkrumah presents his case for African unity and cooperation.', '1963-01-01', 208, 85.00, NULL, 9, 'active'),
  (1, 4, '9789988550002', 'The Marriage of Anansewa', 'the-marriage-of-anansewa', 'A stage play rooted in Ghanaian storytelling and performance.', '1975-01-01', 96, 60.00, NULL, 11, 'active'),
  (1, 2, '9780435905447', 'This Earth, My Brother', 'this-earth-my-brother', 'A novel exploring society, identity, and life in post-independence Ghana.', '1971-01-01', 224, 74.00, NULL, 7, 'active'),
  (2, 3, '9789988550003', 'Growing a Business in Ghana', 'growing-a-business-in-ghana', 'A practical introduction to planning and operating a small enterprise.', '2022-05-01', 180, 95.00, NULL, 16, 'active'),
  (3, 4, '9789988550004', 'Ananse and the Wisdom Pot', 'ananse-and-the-wisdom-pot', 'A retelling of a traditional Ananse story for young readers.', '2021-08-15', 48, 42.00, NULL, 20, 'active'),
  (4, 3, '9789988550005', 'African History: An Introduction', 'african-history-an-introduction', 'An accessible overview of major periods and themes in African history.', '2020-03-10', 260, 110.00, 99.00, 8, 'active'),
  (6, 3, '9789988550006', 'Building Better Habits', 'building-better-habits', 'A concise guide to setting goals and building lasting routines.', '2023-01-20', 156, 68.00, NULL, 13, 'active');

INSERT IGNORE INTO book_authors (book_id, author_id, author_order) VALUES
  (1, 1, 1),
  (2, 2, 1),
  (3, 3, 1),
  (4, 4, 1),
  (5, 5, 1),
  (6, 6, 1);

INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
  ('currency', 'GHS'),
  ('currency_symbol', 'GH₵'),
  ('tax_rate_percent', '0'),
  ('default_delivery_fee', '0'),
  ('store_name', 'Online Bookstore'),
  ('store_email', ''),
  ('store_phone', '');

INSERT IGNORE INTO delivery_zones (region, city, fee, is_active) VALUES
  ('Greater Accra', '*', 0.00, 1),
  ('Ashanti', '*', 0.00, 1);
