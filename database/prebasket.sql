-- =====================================================================
--  PreBasket - Smart Supermarket Pre-Order and Quick Collection System
--  MySQL / MariaDB database  (import this file in phpMyAdmin)
-- ---------------------------------------------------------------------
--  Demo logins
--     Admin     : username admin           password admin123
--     Customer  : demo@prebasket.test      password demo123
-- =====================================================================

CREATE DATABASE IF NOT EXISTS prebasket
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prebasket;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. customers -----------------------------------------------------------
CREATE TABLE users (
  user_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100) NOT NULL,
  email          VARCHAR(150) NOT NULL,
  phone          VARCHAR(15)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- 2. shop owner / staff --------------------------------------------------
CREATE TABLE admins (
  admin_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(50)  NOT NULL,
  full_name      VARCHAR(100) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (admin_id),
  UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB;

-- 3. categories ----------------------------------------------------------
CREATE TABLE categories (
  category_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_name  VARCHAR(80)  NOT NULL,
  icon           VARCHAR(30)  NOT NULL DEFAULT 'grocery',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB;

-- 4. products (stock is stored here = inventory) -------------------------
--    Products are NEVER deleted. "Remove" only sets status = 'inactive',
--    so old orders keep pointing to a valid product row.
CREATE TABLE products (
  product_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_name    VARCHAR(150) NOT NULL,
  category_id     INT UNSIGNED NOT NULL,
  price           DECIMAL(10,2) NOT NULL,
  old_price       DECIMAL(10,2) NULL DEFAULT NULL,
  unit_label      VARCHAR(40)  NOT NULL DEFAULT '',
  stock_quantity  INT UNSIGNED NOT NULL DEFAULT 0,
  description     TEXT NULL,
  image           VARCHAR(255) NOT NULL DEFAULT 'placeholder.svg',
  rating          DECIMAL(2,1) NOT NULL DEFAULT 4.0,
  is_featured     TINYINT(1)   NOT NULL DEFAULT 0,
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id),
  KEY idx_products_category (category_id),
  KEY idx_products_status (status),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id)
    REFERENCES categories (category_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_products_price CHECK (price > 0),
  CONSTRAINT chk_products_rating CHECK (rating >= 0 AND rating <= 5)
) ENGINE=InnoDB;

-- 5. cart (one row per customer + product) -------------------------------
CREATE TABLE cart (
  cart_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  quantity    INT UNSIGNED NOT NULL DEFAULT 1,
  added_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (cart_id),
  UNIQUE KEY uq_cart_user_product (user_id, product_id),
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_product FOREIGN KEY (product_id)
    REFERENCES products (product_id) ON DELETE CASCADE,
  CONSTRAINT chk_cart_qty CHECK (quantity > 0)
) ENGINE=InnoDB;

-- 6. orders --------------------------------------------------------------
CREATE TABLE orders (
  order_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_code      VARCHAR(20)  NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  total_amount    DECIMAL(10,2) NOT NULL,
  payment_method  ENUM('Online Payment','Cash at Store') NOT NULL,
  payment_status  ENUM('Pending','Paid','Refunded','Cancelled') NOT NULL DEFAULT 'Pending',
  order_status    ENUM('Pending','Accepted','Preparing','Ready for Collection',
                       'Collected','Completed','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (order_id),
  UNIQUE KEY uq_order_code (order_code),
  KEY idx_orders_user (user_id),
  KEY idx_orders_status (order_status),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT chk_orders_total CHECK (total_amount >= 0)
) ENGINE=InnoDB;

-- 7. order_items (price and name are COPIED here at the time of purchase) -
CREATE TABLE order_items (
  order_item_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id              INT UNSIGNED NOT NULL,
  product_id            INT UNSIGNED NOT NULL,
  product_name_snapshot VARCHAR(150) NOT NULL,
  price_at_purchase     DECIMAL(10,2) NOT NULL,
  quantity              INT UNSIGNED NOT NULL,
  subtotal              DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (order_item_id),
  KEY idx_items_order (order_id),
  CONSTRAINT fk_items_order FOREIGN KEY (order_id)
    REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id)
    REFERENCES products (product_id) ON DELETE RESTRICT,
  CONSTRAINT chk_items_qty CHECK (quantity > 0),
  CONSTRAINT chk_items_price CHECK (price_at_purchase > 0)
) ENGINE=InnoDB;

-- 8. payments (demo only - no real payment gateway) ----------------------
CREATE TABLE payments (
  payment_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id         INT UNSIGNED NOT NULL,
  amount           DECIMAL(10,2) NOT NULL,
  method           ENUM('Online Payment','Cash at Store') NOT NULL,
  status           ENUM('Pending','Paid','Refunded','Cancelled') NOT NULL DEFAULT 'Pending',
  transaction_ref  VARCHAR(40) NULL DEFAULT NULL,
  paid_at          DATETIME NULL DEFAULT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  UNIQUE KEY uq_payments_order (order_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id)
    REFERENCES orders (order_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. feedback (one per order) --------------------------------------------
CREATE TABLE feedback (
  feedback_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id     INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  rating       TINYINT UNSIGNED NOT NULL,
  comments     VARCHAR(500) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (feedback_id),
  UNIQUE KEY uq_feedback_order (order_id),
  CONSTRAINT fk_feedback_order FOREIGN KEY (order_id)
    REFERENCES orders (order_id) ON DELETE CASCADE,
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT chk_feedback_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;


-- =====================  SAMPLE DATA  =====================

INSERT INTO admins (admin_id, username, full_name, password_hash) VALUES
  (1, 'admin', 'Shop Owner', '$2y$12$kqtBxywk3ssMY1iDC9cpqO5v03tLLhxREkejgo0jZjsYyw2vREEf.');

INSERT INTO users (user_id, name, email, phone, password_hash) VALUES
  (1, 'Demo Customer', 'demo@prebasket.test', '9876543210', '$2y$12$16kCZcEggMS5lRcpiUlIieseBRgPWr7VvO5xTXXXgoePPlUD6meuS'),
  (2, 'Riya Sharma', 'riya@prebasket.test', '9123456780', '$2y$12$16kCZcEggMS5lRcpiUlIieseBRgPWr7VvO5xTXXXgoePPlUD6meuS');

INSERT INTO categories (category_id, category_name, icon) VALUES
  (1, 'Grocery', 'grocery'),
  (2, 'Dairy', 'dairy'),
  (3, 'Snacks', 'snacks'),
  (4, 'Beverages', 'beverages'),
  (5, 'Personal Care', 'personal'),
  (6, 'Household', 'household'),
  (7, 'Fruits and Vegetables', 'fruits');

INSERT INTO products (product_id, product_name, category_id, price, old_price, unit_label, stock_quantity, description, image, rating, is_featured, status) VALUES
  (1, 'Basmati Rice', 1, 120, 135, '1 kg', 40, 'Long grain aged basmati rice. Fluffy and fragrant when cooked.', 'basmati-rice.svg', 4.5, 1, 'active'),
  (2, 'Whole Wheat Atta', 1, 245, NULL, '5 kg', 25, 'Stone ground whole wheat flour for soft rotis.', 'whole-wheat-atta.svg', 4.4, 0, 'active'),
  (3, 'Toor Dal', 1, 95, 105, '500 g', 30, 'Clean, polished split pigeon peas.', 'toor-dal.svg', 4.2, 0, 'active'),
  (4, 'Sunflower Oil', 1, 145, 160, '1 L', 18, 'Light refined sunflower cooking oil.', 'sunflower-oil.svg', 4.3, 0, 'active'),
  (5, 'White Sugar', 1, 48, NULL, '1 kg', 60, 'Fine grain white sugar.', 'white-sugar.svg', 4.1, 0, 'active'),
  (6, 'Iodised Salt', 1, 22, NULL, '1 kg', 80, 'Free flowing iodised table salt.', 'iodised-salt.svg', 4.4, 0, 'active'),
  (7, 'Instant Noodles Family Pack', 1, 60, NULL, '4 x 70 g', 0, 'Discontinued item. Kept in the database only so that old orders stay correct.', 'instant-noodles-family-pack.svg', 4.0, 0, 'inactive'),
  (8, 'Toned Milk', 2, 28, NULL, '500 ml', 50, 'Fresh pasteurised toned milk.', 'toned-milk.svg', 4.6, 1, 'active'),
  (9, 'Fresh Curd', 2, 35, NULL, '400 g', 30, 'Thick and creamy set curd.', 'fresh-curd.svg', 4.3, 0, 'active'),
  (10, 'Fresh Paneer', 2, 90, 100, '200 g', 3, 'Soft paneer made from fresh milk.', 'fresh-paneer.svg', 4.5, 1, 'active'),
  (11, 'Salted Butter', 2, 58, NULL, '100 g', 24, 'Rich creamy salted table butter.', 'salted-butter.svg', 4.7, 0, 'active'),
  (12, 'Cheese Slices', 2, 130, NULL, '200 g', 0, 'Ten processed cheese slices.', 'cheese-slices.svg', 4.2, 0, 'active'),
  (13, 'Masala Potato Chips', 3, 25, 30, '58 g', 70, 'Crunchy potato chips with a spicy masala kick.', 'masala-potato-chips.svg', 4.5, 1, 'active'),
  (14, 'Salted Wafers', 3, 45, NULL, '140 g', 45, 'Thin crispy wafers, lightly salted.', 'salted-wafers.svg', 4.4, 1, 'active'),
  (15, 'Nacho Chips Tomato', 3, 30, NULL, '60 g', 38, 'Corn nachos with a tangy tomato flavour.', 'nacho-chips-tomato.svg', 4.1, 0, 'active'),
  (16, 'Choco Cream Biscuits', 3, 35, 40, '120 g', 55, 'Crisp biscuits filled with chocolate cream.', 'choco-cream-biscuits.svg', 4.3, 0, 'active'),
  (17, 'Roasted Peanuts', 3, 60, NULL, '200 g', 28, 'Lightly salted roasted peanuts.', 'roasted-peanuts.svg', 4.2, 0, 'active'),
  (18, 'Butter Cookies', 3, 50, 55, '150 g', 33, 'Melt in the mouth butter cookies.', 'butter-cookies.svg', 4.6, 0, 'active'),
  (19, 'Orange Juice', 4, 99, 110, '1 L', 22, 'Ready to drink orange fruit juice.', 'orange-juice.svg', 4.2, 1, 'active'),
  (20, 'Cola Drink', 4, 40, NULL, '750 ml', 4, 'Chilled fizzy cola drink.', 'cola-drink.svg', 4.0, 0, 'active'),
  (21, 'Green Tea', 4, 150, 175, '25 bags', 15, 'Refreshing green tea bags.', 'green-tea.svg', 4.4, 0, 'active'),
  (22, 'Instant Coffee', 4, 165, NULL, '50 g', 20, 'Strong and aromatic instant coffee granules.', 'instant-coffee.svg', 4.6, 1, 'active'),
  (23, 'Lemon Soda', 4, 20, NULL, '600 ml', 44, 'Sparkling lemon flavoured soda.', 'lemon-soda.svg', 3.9, 0, 'active'),
  (24, 'Herbal Shampoo', 5, 175, 199, '180 ml', 19, 'Gentle herbal shampoo for daily use.', 'herbal-shampoo.svg', 4.3, 0, 'active'),
  (25, 'Fluoride Toothpaste', 5, 89, NULL, '150 g', 35, 'Cavity protection with fresh mint.', 'fluoride-toothpaste.svg', 4.5, 0, 'active'),
  (26, 'Bathing Soap', 5, 38, NULL, '100 g', 65, 'Moisturising rose bathing soap.', 'bathing-soap.svg', 4.2, 0, 'active'),
  (27, 'Liquid Hand Wash', 5, 79, 90, '250 ml', 26, 'Germ protection liquid hand wash.', 'liquid-hand-wash.svg', 4.1, 0, 'active'),
  (28, 'Dishwash Liquid', 6, 110, NULL, '500 ml', 21, 'Lemon dishwash liquid that cuts grease.', 'dishwash-liquid.svg', 4.3, 0, 'active'),
  (29, 'Floor Cleaner', 6, 185, 205, '1 L', 17, 'Disinfectant floor cleaner with a fresh smell.', 'floor-cleaner.svg', 4.2, 0, 'active'),
  (30, 'Laundry Detergent Powder', 6, 220, NULL, '1 kg', 14, 'Tough stain removal detergent powder.', 'laundry-detergent-powder.svg', 4.4, 0, 'active'),
  (31, 'Kitchen Tissue Roll', 6, 75, NULL, '2 rolls', 32, 'Absorbent two ply kitchen tissue rolls.', 'kitchen-tissue-roll.svg', 4.0, 0, 'active'),
  (32, 'Bananas', 7, 55, NULL, '1 dozen', 40, 'Ripe and sweet yellow bananas.', 'bananas.svg', 4.4, 1, 'active'),
  (33, 'Red Apples', 7, 180, 199, '1 kg', 26, 'Crisp and juicy red apples.', 'red-apples.svg', 4.5, 1, 'active'),
  (34, 'Tomatoes', 7, 30, NULL, '500 g', 45, 'Fresh farm tomatoes.', 'tomatoes.svg', 4.0, 0, 'active'),
  (35, 'Potatoes', 7, 40, NULL, '1 kg', 70, 'Fresh potatoes for everyday cooking.', 'potatoes.svg', 4.2, 0, 'active'),
  (36, 'Onions', 7, 38, NULL, '1 kg', 66, 'Red onions, firm and fresh.', 'onions.svg', 4.1, 0, 'active'),
  (37, 'Fresh Spinach', 7, 25, NULL, '250 g', 12, 'Tender green spinach leaves.', 'fresh-spinach.svg', 4.3, 0, 'active');

INSERT INTO orders (order_id, order_code, user_id, total_amount, payment_method, payment_status, order_status, created_at, updated_at) VALUES
  (1, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '%Y%m%d'), '0001'), 1, 145, 'Online Payment', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '10:20:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '10:20:00')),
  (2, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '%Y%m%d'), '0002'), 2, 237, 'Cash at Store', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '17:45:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '17:45:00')),
  (3, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '%Y%m%d'), '0001'), 1, 295, 'Cash at Store', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:05:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:05:00')),
  (4, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '%Y%m%d'), '0002'), 2, 265, 'Online Payment', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '12:30:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '12:30:00')),
  (5, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '%Y%m%d'), '0003'), 1, 169, 'Online Payment', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '18:10:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '18:10:00')),
  (6, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '%Y%m%d'), '0001'), 2, 378, 'Cash at Store', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '11:15:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '11:15:00')),
  (7, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '0001'), 1, 200, 'Online Payment', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:50:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:50:00')),
  (8, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '0002'), 2, 295, 'Cash at Store', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '13:40:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '13:40:00')),
  (9, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '0003'), 1, 208, 'Online Payment', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '16:20:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '16:20:00')),
  (10, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '0004'), 2, 150, 'Cash at Store', 'Cancelled', 'Cancelled', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '19:05:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '19:05:00')),
  (11, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '%Y%m%d'), '0001'), 1, 390, 'Cash at Store', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:00:00')),
  (12, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '%Y%m%d'), '0002'), 2, 370, 'Online Payment', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '15:25:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '15:25:00')),
  (13, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '%Y%m%d'), '0001'), 1, 115, 'Online Payment', 'Paid', 'Collected', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00')),
  (14, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '%Y%m%d'), '0002'), 2, 184, 'Cash at Store', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:10:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:10:00')),
  (15, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '%Y%m%d'), '0003'), 1, 139, 'Online Payment', 'Paid', 'Completed', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '17:55:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '17:55:00')),
  (16, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '%Y%m%d'), '0001'), 1, 139, 'Cash at Store', 'Pending', 'Pending', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '08:15:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '08:15:00')),
  (17, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '%Y%m%d'), '0002'), 2, 150, 'Online Payment', 'Paid', 'Accepted', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '08:40:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '08:40:00')),
  (18, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '%Y%m%d'), '0003'), 1, 310, 'Cash at Store', 'Pending', 'Preparing', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '09:05:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '09:05:00')),
  (19, CONCAT('PB', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '%Y%m%d'), '0004'), 2, 118, 'Online Payment', 'Paid', 'Ready for Collection', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '09:20:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '09:20:00'));

INSERT INTO order_items (order_id, product_id, product_name_snapshot, price_at_purchase, quantity, subtotal) VALUES
  (1, 13, 'Masala Potato Chips', 22, 2, 44),
  (1, 8, 'Toned Milk', 28, 2, 56),
  (1, 14, 'Salted Wafers', 45, 1, 45),
  (2, 1, 'Basmati Rice', 120, 1, 120),
  (2, 3, 'Toor Dal', 95, 1, 95),
  (2, 6, 'Iodised Salt', 22, 1, 22),
  (3, 32, 'Bananas', 55, 1, 55),
  (3, 33, 'Red Apples', 180, 1, 180),
  (3, 34, 'Tomatoes', 30, 2, 60),
  (4, 22, 'Instant Coffee', 165, 1, 165),
  (4, 18, 'Butter Cookies', 50, 2, 100),
  (5, 19, 'Orange Juice', 99, 1, 99),
  (5, 16, 'Choco Cream Biscuits', 35, 2, 70),
  (6, 24, 'Herbal Shampoo', 175, 1, 175),
  (6, 26, 'Bathing Soap', 38, 3, 114),
  (6, 25, 'Fluoride Toothpaste', 89, 1, 89),
  (7, 7, 'Instant Noodles Family Pack', 60, 2, 120),
  (7, 20, 'Cola Drink', 40, 2, 80),
  (8, 28, 'Dishwash Liquid', 110, 1, 110),
  (8, 29, 'Floor Cleaner', 185, 1, 185),
  (9, 10, 'Fresh Paneer', 90, 1, 90),
  (9, 36, 'Onions', 38, 1, 38),
  (9, 35, 'Potatoes', 40, 2, 80),
  (10, 21, 'Green Tea', 150, 1, 150),
  (11, 2, 'Whole Wheat Atta', 245, 1, 245),
  (11, 4, 'Sunflower Oil', 145, 1, 145),
  (12, 30, 'Laundry Detergent Powder', 220, 1, 220),
  (12, 31, 'Kitchen Tissue Roll', 75, 2, 150),
  (13, 13, 'Masala Potato Chips', 25, 3, 75),
  (13, 23, 'Lemon Soda', 20, 2, 40),
  (14, 9, 'Fresh Curd', 35, 2, 70),
  (14, 8, 'Toned Milk', 28, 2, 56),
  (14, 11, 'Salted Butter', 58, 1, 58),
  (15, 27, 'Liquid Hand Wash', 79, 1, 79),
  (15, 17, 'Roasted Peanuts', 60, 1, 60),
  (16, 32, 'Bananas', 55, 1, 55),
  (16, 8, 'Toned Milk', 28, 3, 84),
  (17, 15, 'Nacho Chips Tomato', 30, 2, 60),
  (17, 14, 'Salted Wafers', 45, 2, 90),
  (18, 1, 'Basmati Rice', 120, 2, 240),
  (18, 5, 'White Sugar', 48, 1, 48),
  (18, 6, 'Iodised Salt', 22, 1, 22),
  (19, 37, 'Fresh Spinach', 25, 2, 50),
  (19, 34, 'Tomatoes', 30, 1, 30),
  (19, 36, 'Onions', 38, 1, 38);

INSERT INTO payments (order_id, amount, method, status, transaction_ref, paid_at) VALUES
  (1, 145, 'Online Payment', 'Paid', 'DEMO007919', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '10:20:00')),
  (2, 237, 'Cash at Store', 'Paid', NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '17:45:00')),
  (3, 295, 'Cash at Store', 'Paid', NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:05:00')),
  (4, 265, 'Online Payment', 'Paid', 'DEMO031676', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '12:30:00')),
  (5, 169, 'Online Payment', 'Paid', 'DEMO039595', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '18:10:00')),
  (6, 378, 'Cash at Store', 'Paid', NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '11:15:00')),
  (7, 200, 'Online Payment', 'Paid', 'DEMO055433', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:50:00')),
  (8, 295, 'Cash at Store', 'Paid', NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '13:40:00')),
  (9, 208, 'Online Payment', 'Paid', 'DEMO071271', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '16:20:00')),
  (10, 150, 'Cash at Store', 'Cancelled', NULL, NULL),
  (11, 390, 'Cash at Store', 'Paid', NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:00:00')),
  (12, 370, 'Online Payment', 'Paid', 'DEMO095028', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '15:25:00')),
  (13, 115, 'Online Payment', 'Paid', 'DEMO102947', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00')),
  (14, 184, 'Cash at Store', 'Paid', NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:10:00')),
  (15, 139, 'Online Payment', 'Paid', 'DEMO118785', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '17:55:00')),
  (16, 139, 'Cash at Store', 'Pending', NULL, NULL),
  (17, 150, 'Online Payment', 'Paid', 'DEMO134623', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '08:40:00')),
  (18, 310, 'Cash at Store', 'Pending', NULL, NULL),
  (19, 118, 'Online Payment', 'Paid', 'DEMO150461', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 0 DAY), '09:20:00'));

INSERT INTO feedback (order_id, user_id, rating, comments, created_at) VALUES
  (1, 1, 5, 'Very quick pickup, everything was packed and ready.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '10:20:00')),
  (3, 1, 4, 'Good fresh fruit. Counter had a short wait.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:05:00')),
  (6, 2, 5, 'Loved the QR code idea!', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '11:15:00')),
  (11, 1, 4, 'Smooth process, will use again.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:00:00')),
  (14, 2, 5, 'Fast and easy. No searching in the aisles.', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:10:00'));
