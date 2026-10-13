-- Run this ONCE in phpMyAdmin (database: attendance) -> SQL tab.
-- Adds email sign-up, admin approval, password reset and login-attempt limits.

ALTER TABLE `users`
  ADD COLUMN `email` VARCHAR(190) NULL AFTER `username`,
  ADD UNIQUE KEY `email` (`email`);

-- Employees who register themselves. Admin approves or rejects each one.
CREATE TABLE `registrations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `token_hash` CHAR(64) DEFAULT NULL,
  `status` ENUM('unverified','pending','approved','rejected') NOT NULL DEFAULT 'unverified',
  `employee_id` INT(11) DEFAULT NULL,
  `reject_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `reviewed_by` INT(11) DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `token_hash` (`token_hash`),
  KEY `email` (`email`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One-time links for "Forgot password" (only a hash of the link code is stored).
CREATE TABLE `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Failed logins, sign-ups and reset requests (used to slow down guessing and spam).
CREATE TABLE `auth_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `kind` VARCHAR(20) NOT NULL,
  `subject` VARCHAR(190) NOT NULL,
  `attempted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `lookup` (`kind`,`subject`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Give the admin an email so "Forgot password" works for the admin too
-- (change the address, then remove the two dashes at the start of the next line):
-- UPDATE `users` SET `email` = 'your-admin-email@gmail.com' WHERE `username` = 'admin';
