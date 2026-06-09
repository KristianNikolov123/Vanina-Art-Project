-- Vanina Art Project - MySQL schema
-- Import via phpMyAdmin (http://localhost/phpmyadmin) or:
--   mysql -u root -p < database/mysql_schema.sql

CREATE DATABASE IF NOT EXISTS vanina_art
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vanina_art;

-- ---------------------------------------------------------------------------
-- Users & authentication
-- ---------------------------------------------------------------------------

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE login_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    username VARCHAR(100) NOT NULL,
    login_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_logs_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX idx_login_logs_user_id (user_id),
    INDEX idx_login_logs_login_time (login_time)
) ENGINE=InnoDB;

CREATE TABLE admin_emails (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_admin_emails_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Feedback (home page)
-- ---------------------------------------------------------------------------

CREATE TABLE feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_feedback_created_at (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Inventory catalog (frame profiles, glass, passepartouts)
-- ---------------------------------------------------------------------------

CREATE TABLE profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_profiles_name (name)
) ENGINE=InnoDB;

CREATE TABLE glasses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_glasses_name (name)
) ENGINE=InnoDB;

CREATE TABLE passepartouts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_passepartouts_name (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Orders (profile/glass/passepartout stored as names, matching app logic)
-- ---------------------------------------------------------------------------

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number INT UNSIGNED NOT NULL,
    sub_order_number INT UNSIGNED NOT NULL DEFAULT 0,
    date VARCHAR(20) NOT NULL,
    width DECIMAL(10, 2) NULL,
    height DECIMAL(10, 2) NULL,
    profile VARCHAR(255) NULL,
    additional_profiles TEXT NULL,
    frame_count INT UNSIGNED NOT NULL DEFAULT 1,
    description TEXT NULL,
    glass VARCHAR(255) NULL,
    back VARCHAR(255) NULL,
    passepartout VARCHAR(255) NULL,
    hanging VARCHAR(255) NULL,
    price DECIMAL(10, 2) NULL,
    advance_payment DECIMAL(10, 2) NULL,
    discount DECIMAL(10, 2) NULL,
    customer_name VARCHAR(255) NULL,
    paid TINYINT(1) NOT NULL DEFAULT 0,
    collected TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_orders_order_number (order_number),
    INDEX idx_orders_customer_name (customer_name),
    INDEX idx_orders_date (date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Optional seed data (edit admin email before use)
-- ---------------------------------------------------------------------------

-- INSERT INTO admin_emails (email) VALUES ('your-admin@gmail.com');
