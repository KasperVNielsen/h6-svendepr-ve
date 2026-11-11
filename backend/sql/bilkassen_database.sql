-- ========================================================
-- BILSALG - Bilbasen Clone Database Schema
-- Backend: PHP | Authors: Kasper & Chris | © Luksusbiler
-- ========================================================
-- Encoding: utf8mb4_danish_ci
-- Engine: InnoDB
-- ========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS `bil-kassen`;
CREATE DATABASE `bil-kassen` CHARACTER SET utf8mb4 COLLATE utf8mb4_danish_ci;
USE `bil-kassen`;

-- --------------------------------------------------------
-- 1. ROLES
-- --------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL,
    `level` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=Bruger, 2=Firma, 3=Support, 4=Admin, 5=Superadmin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

INSERT INTO `roles` (`name`, `slug`, `level`) VALUES
('Bruger', 'bruger', 1),
('Firma', 'firma', 2),
('Support', 'support', 3),
('Admin', 'admin', 4),
('Superadmin', 'superadmin', 5);

-- --------------------------------------------------------
-- 2. USERS (Brugere - både private og firmamedarbejdere)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `address` VARCHAR(255) DEFAULT NULL,
    `zip_code` VARCHAR(10) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `iban` VARCHAR(34) DEFAULT NULL COMMENT 'Til udbetalinger',
    `card_token` VARCHAR(255) DEFAULT NULL COMMENT 'Token til indbetaling (fiktiv)',
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fiktiv saldo',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role_id`),
    KEY `idx_users_active` (`is_active`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 3. COMPANIES (Firmaer)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_user_id` INT UNSIGNED NOT NULL COMMENT 'Ejerens bruger-ID',
    `cvr` VARCHAR(20) NOT NULL,
    `company_name` VARCHAR(255) NOT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `address` VARCHAR(255) NOT NULL,
    `zip_code` VARCHAR(10) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fiktiv firmasaldo',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_companies_cvr` (`cvr`),
    UNIQUE KEY `uq_companies_owner` (`owner_user_id`),
    KEY `idx_companies_active` (`is_active`),
    CONSTRAINT `fk_companies_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 4. COMPANY EMPLOYEES (Firmamedarbejdere)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `company_employees`;
CREATE TABLE `company_employees` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `position` VARCHAR(100) DEFAULT 'Medarbejder',
    `can_create_listings` TINYINT(1) NOT NULL DEFAULT 1,
    `can_edit_listings` TINYINT(1) NOT NULL DEFAULT 1,
    `can_manage_transactions` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_emp_company_user` (`company_id`, `user_id`),
    KEY `idx_emp_user` (`user_id`),
    CONSTRAINT `fk_emp_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_emp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 5. BRANDS (Bilmærker)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
    `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_brands_slug` (`slug`),
    KEY `idx_brands_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 6. MODELS (Bilmodeller)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `models`;
CREATE TABLE `models` (
    `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `brand_id` SMALLINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) DEFAULT NULL COMMENT 'Varevogn, Personbil, SUV osv.',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_models_brand_slug` (`brand_id`, `slug`),
    KEY `idx_models_active` (`is_active`),
    CONSTRAINT `fk_models_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 7. EQUIPMENT (Udstyr og tilbehør)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
    `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) DEFAULT NULL COMMENT 'Komfort, Sikkerhed, Teknologi osv.',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_equipment_name` (`name`),
    KEY `idx_equipment_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 8. CAR LISTINGS (Salgsannoncer)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `car_listings`;
CREATE TABLE `car_listings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `seller_user_id` INT UNSIGNED NOT NULL,
    `seller_company_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = privatbruger',
    `brand_id` SMALLINT UNSIGNED NOT NULL,
    `model_id` MEDIUMINT UNSIGNED NOT NULL,

    -- Pris og salg
    `price` DECIMAL(12,2) NOT NULL,
    `price_type` ENUM('fast','forhandling') NOT NULL DEFAULT 'fast',
    `status` ENUM('aktiv','solgt','reserveret','inaktiv') NOT NULL DEFAULT 'aktiv',

    -- Stand
    `condition` ENUM('brugt','ny','demobil') NOT NULL DEFAULT 'brugt',

    -- Motor og ydelse
    `engine_info` VARCHAR(100) DEFAULT NULL COMMENT 'Eks: 2,0 dCi 120 L2H1',
    `horsepower` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'hk',
    `torque` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'nm',
    `cylinders` TINYINT UNSIGNED DEFAULT NULL,
    `fuel_type` ENUM('Benzin','Diesel','El','Hybrid','Plug-in hybrid','Brint') DEFAULT NULL,
    `fuel_consumption` DECIMAL(4,1) DEFAULT NULL COMMENT 'km/l (NEDC)',
    `co2_emission` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'g/km',
    `euro_norm` VARCHAR(10) DEFAULT NULL,
    `periodic_tax` DECIMAL(10,2) DEFAULT NULL COMMENT 'kr. / år',

    -- Køreegenskaber
    `acceleration` DECIMAL(4,1) DEFAULT NULL COMMENT '0-100 km/t',
    `top_speed` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'km/t',
    `gear_type` ENUM('Manuel','Automatisk','CVT','Halvautomatisk') DEFAULT NULL,
    `gear_count` TINYINT UNSIGNED DEFAULT NULL,
    `drive_wheels` ENUM('Forhjulstræk','Baghjulstræk','4x4') DEFAULT NULL,

    -- Dimensioner og vægt
    `weight` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'kg',
    `payload` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'kg',
    `towing_capacity` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'kg',
    `max_towing_brake` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'kg',
    `length` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'mm',
    `width` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'mm',
    `height` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'mm',
    `trunk_size` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'liter',
    `tank_capacity` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'liter',
    `doors` TINYINT UNSIGNED DEFAULT NULL,

    -- Sikkerhed
    `abs_brakes` TINYINT(1) DEFAULT NULL,
    `esp` TINYINT(1) DEFAULT NULL,
    `airbags` TINYINT UNSIGNED DEFAULT NULL,

    -- Info
    `model_year` YEAR DEFAULT NULL,
    `first_registration` VARCHAR(20) DEFAULT NULL COMMENT 'Eks: 3/2020',
    `mileage` INT UNSIGNED DEFAULT NULL COMMENT 'km',
    `color` VARCHAR(50) DEFAULT NULL,
    `color_type` ENUM('Metallic','Mat','Standard') DEFAULT 'Standard',
    `new_price` DECIMAL(12,2) DEFAULT NULL COMMENT 'kr.',
    `category` VARCHAR(50) DEFAULT NULL COMMENT 'Varevogn ekskl. moms osv.',
    `body_type` VARCHAR(50) DEFAULT NULL COMMENT 'Kassevogn, Stationcar osv.',

    -- Beskrivelser
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `general_info` TEXT DEFAULT NULL COMMENT 'Generelle modeloplysninger',

    -- Billeder
    `main_image` VARCHAR(255) DEFAULT NULL,

    -- Meta
    `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `sold_at` DATETIME DEFAULT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_listings_seller_user` (`seller_user_id`),
    KEY `idx_listings_seller_company` (`seller_company_id`),
    KEY `idx_listings_brand` (`brand_id`),
    KEY `idx_listings_model` (`model_id`),
    KEY `idx_listings_status` (`status`),
    KEY `idx_listings_condition` (`condition`),
    KEY `idx_listings_price` (`price`),
    KEY `idx_listings_year` (`model_year`),
    KEY `idx_listings_mileage` (`mileage`),
    KEY `idx_listings_fuel` (`fuel_type`),
    KEY `idx_listings_active` (`is_active`),
    KEY `idx_listings_featured` (`is_featured`),
    FULLTEXT KEY `ft_listings_title_desc` (`title`, `description`),
    CONSTRAINT `fk_listings_seller_user` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_listings_seller_company` FOREIGN KEY (`seller_company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_listings_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_listings_model` FOREIGN KEY (`model_id`) REFERENCES `models` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 9. CAR IMAGES (Billeder til annoncer)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `car_images`;
CREATE TABLE `car_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id` INT UNSIGNED NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) DEFAULT NULL,
    `file_size` INT UNSIGNED DEFAULT NULL,
    `mime_type` VARCHAR(50) DEFAULT NULL,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `is_main` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_images_listing` (`listing_id`),
    KEY `idx_images_main` (`is_main`),
    CONSTRAINT `fk_images_listing` FOREIGN KEY (`listing_id`) REFERENCES `car_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 10. LISTING EQUIPMENT (Mange-til-mange: annonce <-> udstyr)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `listing_equipment`;
CREATE TABLE `listing_equipment` (
    `listing_id` INT UNSIGNED NOT NULL,
    `equipment_id` SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`listing_id`, `equipment_id`),
    CONSTRAINT `fk_le_listing` FOREIGN KEY (`listing_id`) REFERENCES `car_listings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_le_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 11. TRANSACTIONS (Ind- og udbetalinger)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `company_id` INT UNSIGNED DEFAULT NULL,
    `type` ENUM('indbetaling','udbetaling','køb','salg','leasing','refundering') NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `method` ENUM('kort','iban','intern','kontant') NOT NULL DEFAULT 'kort',
    `card_last4` VARCHAR(4) DEFAULT NULL,
    `iban_number` VARCHAR(34) DEFAULT NULL,
    `reference` VARCHAR(255) DEFAULT NULL COMMENT 'Transaktionsreference',
    `status` ENUM('venter','gennemført','afvist','annulleret') NOT NULL DEFAULT 'venter',
    `description` VARCHAR(255) DEFAULT NULL,
    `processed_by` INT UNSIGNED DEFAULT NULL COMMENT 'Support/Admin der godkendte',
    `processed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_trans_user` (`user_id`),
    KEY `idx_trans_company` (`company_id`),
    KEY `idx_trans_type` (`type`),
    KEY `idx_trans_status` (`status`),
    KEY `idx_trans_created` (`created_at`),
    CONSTRAINT `fk_trans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_trans_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_trans_processed` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 12. SALES HISTORY (Salgshistorik)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sales_history`;
CREATE TABLE `sales_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `listing_id` INT UNSIGNED NOT NULL,
    `seller_user_id` INT UNSIGNED NOT NULL,
    `seller_company_id` INT UNSIGNED DEFAULT NULL,
    `buyer_user_id` INT UNSIGNED NOT NULL,
    `buyer_company_id` INT UNSIGNED DEFAULT NULL,
    `sale_price` DECIMAL(12,2) NOT NULL,
    `sale_date` DATETIME NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sales_listing` (`listing_id`),
    KEY `idx_sales_seller` (`seller_user_id`),
    KEY `idx_sales_buyer` (`buyer_user_id`),
    KEY `idx_sales_date` (`sale_date`),
    CONSTRAINT `fk_sales_listing` FOREIGN KEY (`listing_id`) REFERENCES `car_listings` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sales_seller` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sales_buyer` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 13. TICKETS (Support-sager)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'Bruger der oprettede sagen',
    `assigned_to` INT UNSIGNED DEFAULT NULL COMMENT 'Support-medarbejder',
    `subject` VARCHAR(255) NOT NULL,
    `category` ENUM('teknisk','betaling','konto','annonce','andet') NOT NULL DEFAULT 'andet',
    `priority` ENUM('lav','normal','høj','kritisk') NOT NULL DEFAULT 'normal',
    `status` ENUM('åben','under_behandling','venter_bruger','løst','lukket') NOT NULL DEFAULT 'åben',
    `resolved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tickets_user` (`user_id`),
    KEY `idx_tickets_assigned` (`assigned_to`),
    KEY `idx_tickets_status` (`status`),
    KEY `idx_tickets_priority` (`priority`),
    CONSTRAINT `fk_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 14. TICKET MESSAGES (Beskeder i support-sager)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ticket_messages`;
CREATE TABLE `ticket_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id` INT UNSIGNED NOT NULL,
    `sender_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `is_internal` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Intern note kun for support',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tmsg_ticket` (`ticket_id`),
    KEY `idx_tmsg_sender` (`sender_id`),
    CONSTRAINT `fk_tmsg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tmsg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 17. FAVORITES (Gemte annoncer)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `listing_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fav_user_listing` (`user_id`, `listing_id`),
    CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fav_listing` FOREIGN KEY (`listing_id`) REFERENCES `car_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

-- --------------------------------------------------------
-- 19. AUDIT LOG (Admin/Superadmin handlinger)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'Admin der handlede',
    `action` VARCHAR(100) NOT NULL COMMENT 'f.eks. opret_support',
    `target_type` VARCHAR(50) NOT NULL COMMENT 'user, company, listing osv.',
    `target_id` INT UNSIGNED NOT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user` (`user_id`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_target` (`target_type`, `target_id`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;


DROP TABLE IF EXISTS `password_resets`;
-- --------------------------------------------------------
-- INDSTILLINGER / KONFIGURATION
-- --------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key_name` VARCHAR(100) NOT NULL,
    `value` TEXT DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

CREATE TABLE auctions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL UNIQUE,
    start_price DECIMAL(10,2) NOT NULL,
    reserve_price DECIMAL(10,2) NULL,
    current_bid DECIMAL(10,2) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('planlagt','aktiv','slut','solgt','ikke_solgt') NOT NULL DEFAULT 'planlagt',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES car_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;


CREATE TABLE bids (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auction_id INT UNSIGNED NOT NULL,
    bidder_user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
    FOREIGN KEY (bidder_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;

INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('site_name', 'Bilsalg', 'Navn på hjemmesiden'),
('site_currency', 'DKK', 'Standard valuta'),
('max_images_per_listing', '15', 'Maks antal billeder pr. annonce'),
('listing_fee', '0.00', 'Gebyr for at oprette annonce'),
('transaction_fee_percent', '2.5', 'Transaktionsgebyr i procent'),
('min_withdrawal', '100.00', 'Minimum udbetalingsbeløb'),
('support_email', 'support@bilsalg.dk', 'Support email adresse');

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- EKSEMPEL DATA (Valgfrit - til test)
-- ========================================================

-- Eksempel-brugere
INSERT INTO `users` (`role_id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `balance`, `is_verified`) VALUES
(5, 'superadmin@bilsalg.dk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super', 'Admin', '12345678', 0.00, 1),
(4, 'admin@bilsalg.dk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Bruger', '87654321', 0.00, 1),
(3, 'support@bilsalg.dk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Support', 'Medarbejder', '11223344', 0.00, 1),
(1, 'bruger@bilsalg.dk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test', 'Bruger', '55667788', 50000.00, 1);

-- Eksempel-bilmærker
INSERT INTO `brands` (`name`, `slug`) VALUES
('Abarth', 'abarth'),
('Acura', 'acura'),
('Aiways', 'aiways'),
('Aixam', 'aixam'),
('Alfa Romeo', 'alfa-romeo'),
('Alpine', 'alpine'),
('Aptera', 'aptera'),
('Arcfox', 'arcfox'),
('Ariel', 'ariel'),
('Aspark', 'aspark'),
('Aston Martin', 'aston-martin'),
('Audi', 'audi'),
('Aurus', 'aurus'),
('BAIC', 'baic'),
('Bentley', 'bentley'),
('BMW', 'bmw'),
('Bollinger', 'bollinger'),
('Borgward', 'borgward'),
('Brabus', 'brabus'),
('Bugatti', 'bugatti'),
('Buick', 'buick'),
('BYD', 'byd'),
('Cadillac', 'cadillac'),
('Canoo', 'canoo'),
('Caterham', 'caterham'),
('Changan', 'changan'),
('Chery', 'chery'),
('Chevrolet', 'chevrolet'),
('Chrysler', 'chrysler'),
('Citroën', 'citro-n'),
('CUPRA', 'cupra'),
('Dacia', 'dacia'),
('Daewoo', 'daewoo'),
('Daihatsu', 'daihatsu'),
('Datsun', 'datsun'),
('DeLorean', 'delorean'),
('Dodge', 'dodge'),
('Dongfeng', 'dongfeng'),
('DS Automobiles', 'ds-automobiles'),
('Ebro', 'ebro'),
('Exeed', 'exeed'),
('Ferrari', 'ferrari'),
('Fiat', 'fiat'),
('Fisker', 'fisker'),
('Ford', 'ford'),
('Forthing', 'forthing'),
('Foxtron', 'foxtron'),
('GAZ', 'gaz'),
('Geely', 'geely'),
('Genesis', 'genesis'),
('GMC', 'gmc'),
('Great Wall', 'great-wall'),
('Haval', 'haval'),
('Hindustan', 'hindustan'),
('Hispano Suiza', 'hispano-suiza'),
('Honda', 'honda'),
('Hongqi', 'hongqi'),
('Hummer', 'hummer'),
('Hyundai', 'hyundai'),
('Ineos', 'ineos'),
('Infiniti', 'infiniti'),
('Isuzu', 'isuzu'),
('JAC', 'jac'),
('Jaguar', 'jaguar'),
('Jeep', 'jeep'),
('Jetour', 'jetour'),
('JETTA', 'jetta'),
('Karma', 'karma'),
('Kia', 'kia'),
('Koenigsegg', 'koenigsegg'),
('Lada', 'lada'),
('Lamborghini', 'lamborghini'),
('Lancia', 'lancia'),
('Land Rover', 'land-rover'),
('Leapmotor', 'leapmotor'),
('Lexus', 'lexus'),
('Li Auto', 'li-auto'),
('Ligier', 'ligier'),
('Lincoln', 'lincoln'),
('Lucid', 'lucid'),
('Lynk & Co', 'lynk-co'),
('Maserati', 'maserati'),
('Maxus', 'maxus'),
('Maybach', 'maybach'),
('Mazda', 'mazda'),
('McLaren', 'mclaren'),
('Mercedes-Benz', 'mercedes-benz'),
('Mercury', 'mercury'),
('MG', 'mg'),
('Micro', 'micro'),
('Mini', 'mini'),
('Mitsubishi', 'mitsubishi'),
('Morgan', 'morgan'),
('NIO', 'nio'),
('Nissan', 'nissan'),
('Nysa', 'nysa'),
('Omoda', 'omoda'),
('Opel', 'opel'),
('ORA', 'ora'),
('Pagani', 'pagani'),
('Peugeot', 'peugeot'),
('Polestar', 'polestar'),
('Pontiac', 'pontiac'),
('Porsche', 'porsche'),
('RAM', 'ram'),
('Renault', 'renault'),
('Rimac', 'rimac'),
('Rivian', 'rivian'),
('Rolls-Royce', 'rolls-royce'),
('Rover', 'rover'),
('Ruf', 'ruf'),
('Saab', 'saab'),
('Seat', 'seat'),
('Seres', 'seres'),
('Skoda', 'skoda'),
('Skywell', 'skywell'),
('Smart', 'smart'),
('SsangYong', 'ssangyong'),
('Subaru', 'subaru'),
('Suzuki', 'suzuki'),
('Škoda', 'koda'),
('Tata', 'tata'),
('Tesla', 'tesla'),
('Toyota', 'toyota'),
('UAZ', 'uaz'),
('Vauxhall', 'vauxhall'),
('VinFast', 'vinfast'),
('Volkswagen', 'volkswagen'),
('Volvo', 'volvo'),
('Wiesmann', 'wiesmann'),
('Xpeng', 'xpeng'),
('Yamaha', 'yamaha'),
('Zeekr', 'zeekr'),
('Zenvo', 'zenvo');

-- Eksempel-modeller
INSERT INTO `models` (`brand_id`, `name`, `slug`, `category`) VALUES
(7, 'Master', 'master', 'Varevogn'),
(1, 'Transporter', 'transporter', 'Varevogn'),
(2, 'Transit', 'transit', 'Varevogn'),
(3, 'Proace', 'proace', 'Varevogn'),
(4, 'Sprinter', 'sprinter', 'Varevogn'),
(10, 'V60', 'v60', 'Stationcar');

-- Eksempel-udstyr
INSERT INTO `equipment` (`name`, `category`) VALUES
('Aircondition', 'Komfort'),
('Fartpilot', 'Komfort'),
('Navigation', 'Teknologi'),
('Bluetooth', 'Teknologi'),
('El-ruder', 'Komfort'),
('El-spejle', 'Komfort'),
('Sædevarme', 'Komfort'),
('Parkeringsensor', 'Sikkerhed'),
('Bakkamera', 'Sikkerhed'),
('LED-lygter', 'Sikkerhed'),
('Træk', 'Tilbehør'),
('Dobbelt bagdøre', 'Tilbehør'),
('Skydedør', 'Tilbehør');

