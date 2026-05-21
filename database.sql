-- ============================================================
-- QueuePro - Smart Queue & Appointment Management System
-- Database Schema v1.0
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `queuepro` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `queuepro`;

-- ============================================================
-- TABLE: roles
-- ============================================================
CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `display_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `roles` (`name`, `display_name`, `description`) VALUES
('admin',    'Administrator', 'Full system access'),
('staff',    'Staff Member',  'Manage queues and appointments'),
('customer', 'Customer',      'Book appointments and take queue tickets');

-- ============================================================
-- TABLE: branches
-- ============================================================
CREATE TABLE `branches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `address` TEXT,
  `phone` VARCHAR(30),
  `email` VARCHAR(150),
  `city` VARCHAR(100),
  `country` VARCHAR(100) DEFAULT 'Somalia',
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

INSERT INTO `branches` (`name`, `address`, `phone`, `email`, `city`) VALUES
('Main Branch – Mogadishu', 'KM4, Hodan District', '+252 61 000 0001', 'main@queuepro.so', 'Mogadishu'),
('Hargeisa Branch',         'Ahmed Gurey Road',    '+252 63 000 0002', 'hgm@queuepro.so',  'Hargeisa');

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT UNSIGNED,
  `role_id` INT UNSIGNED NOT NULL DEFAULT 3,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(200) NOT NULL UNIQUE,
  `phone` VARCHAR(30),
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255),
  `status` ENUM('active','inactive','blocked') DEFAULT 'active',
  `email_verified_at` TIMESTAMP NULL,
  `remember_token` VARCHAR(100),
  `reset_token` VARCHAR(100),
  `reset_token_expires` DATETIME,
  `last_login` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT,
  INDEX `idx_email` (`email`),
  INDEX `idx_status` (`status`),
  INDEX `idx_role` (`role_id`)
) ENGINE=InnoDB;

-- Default admin (password: Admin@123)
INSERT INTO `users` (`branch_id`, `role_id`, `full_name`, `email`, `phone`, `password_hash`, `status`, `email_verified_at`) VALUES
(1, 1, 'System Administrator', 'admin@queuepro.so', '+252 61 000 0000',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW()),
(1, 2, 'Ahmed Hassan', 'staff@queuepro.so', '+252 61 000 0001',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NOW());

-- ============================================================
-- TABLE: services
-- ============================================================
CREATE TABLE `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `prefix` VARCHAR(5) NOT NULL DEFAULT 'A',
  `avg_duration_minutes` INT DEFAULT 10,
  `max_capacity` INT DEFAULT 100,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `icon` VARCHAR(100) DEFAULT 'bi-person-badge',
  `color` VARCHAR(20) DEFAULT '#3b82f6',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  INDEX `idx_branch` (`branch_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

INSERT INTO `services` (`branch_id`, `name`, `description`, `prefix`, `avg_duration_minutes`, `icon`, `color`) VALUES
(1, 'General Consultation', 'See a general practitioner', 'G', 15, 'bi-heart-pulse', '#ef4444'),
(1, 'Passport Services',    'New passport and renewals',  'P', 20, 'bi-passport',    '#3b82f6'),
(1, 'Banking Services',     'Deposits, withdrawals, loans','B', 10, 'bi-bank',         '#10b981'),
(1, 'Hair & Beauty',        'Haircut and grooming',        'H',  30, 'bi-scissors',     '#8b5cf6');

-- ============================================================
-- TABLE: tickets
-- ============================================================
CREATE TABLE `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED,
  `served_by` INT UNSIGNED,
  `ticket_number` VARCHAR(20) NOT NULL,
  `status` ENUM('waiting','called','in_progress','completed','cancelled','no_show') DEFAULT 'waiting',
  `priority` ENUM('normal','high','vip') DEFAULT 'normal',
  `notes` TEXT,
  `called_at` DATETIME,
  `started_at` DATETIME,
  `completed_at` DATETIME,
  `estimated_wait_minutes` INT,
  `qr_code` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`)  REFERENCES `branches`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)     ON DELETE SET NULL,
  FOREIGN KEY (`served_by`)  REFERENCES `users`(`id`)     ON DELETE SET NULL,
  INDEX `idx_branch_service` (`branch_id`, `service_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_date` (`created_at`)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: appointments
-- ============================================================
CREATE TABLE `appointments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `staff_id` INT UNSIGNED,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `duration_minutes` INT DEFAULT 30,
  `status` ENUM('pending','confirmed','cancelled','completed','rescheduled','no_show') DEFAULT 'pending',
  `notes` TEXT,
  `cancel_reason` TEXT,
  `reminder_sent` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`)  REFERENCES `branches`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`staff_id`)   REFERENCES `users`(`id`)     ON DELETE SET NULL,
  INDEX `idx_date` (`appointment_date`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `data` JSON,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: activity_logs
-- ============================================================
CREATE TABLE `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_date` (`created_at`)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: settings
-- ============================================================
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL UNIQUE,
  `value` TEXT,
  `type` ENUM('string','integer','boolean','json') DEFAULT 'string',
  `description` VARCHAR(255),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
('app_name',          'QueuePro',                'string',  'Application name'),
('app_logo',          '/assets/img/logo.png',    'string',  'Logo path'),
('timezone',          'Africa/Mogadishu',         'string',  'Default timezone'),
('queue_reset_time',  '00:00',                    'string',  'Daily queue reset time'),
('max_tickets_per_user', '3',                     'integer', 'Max tickets per customer per day'),
('notify_at_position',   '3',                     'integer', 'Notify when N tickets ahead'),
('working_hours_start',  '08:00',                 'string',  'Working hours start'),
('working_hours_end',    '17:00',                 'string',  'Working hours end');
