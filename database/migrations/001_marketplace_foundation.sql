-- Marketplace foundation migration.
-- Additive only: preserves existing users, books, orders, payments and logs.
-- Run once against an existing bookstore database after backing it up.

SET NAMES utf8mb4;

ALTER TABLE users
  MODIFY role ENUM('customer', 'admin', 'seller') NOT NULL DEFAULT 'customer';

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
  MODIFY status ENUM('draft', 'pending_review', 'under_review', 'approved', 'rejected', 'revision_requested', 'suspended', 'active', 'archived') NOT NULL DEFAULT 'draft',
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

INSERT INTO marketplace_settings (setting_key, setting_value)
VALUES ('default_commission_rate', '10.00')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
