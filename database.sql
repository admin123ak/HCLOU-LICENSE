-- HCLOU-LICENSE — Schema
-- Tách biệt khỏi HCLOU shop. License + script delivery cho Lua/GameGuardian.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- =============================================
-- TABLE: games — game/package được hỗ trợ
-- =============================================
CREATE TABLE IF NOT EXISTS `games` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `package_id` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(60) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_games_pkg` (`package_id`),
  UNIQUE KEY `uniq_games_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: scripts — Mod config per game (modname/mod_status/credit cho C++ client)
-- =============================================
CREATE TABLE IF NOT EXISTS `scripts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `game_id` INT(11) NOT NULL,
  `version` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
  `modname` VARCHAR(120) NOT NULL DEFAULT '',
  `mod_status` ENUM('on','off') NOT NULL DEFAULT 'on',
  `credit` TEXT DEFAULT NULL,
  `body` LONGTEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scripts_game` (`game_id`, `is_active`),
  CONSTRAINT `fk_scripts_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: license_keys — pool key sell qua HCLOU shop
-- =============================================
CREATE TABLE IF NOT EXISTS `license_keys` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `key_code` VARCHAR(40) NOT NULL,                        -- HCLOU-XXXXXXXXXXXX
  `game_id` INT(11) NOT NULL,
  `duration_hours` INT(11) NOT NULL DEFAULT 720,          -- 720h = 30 ngày
  `max_devices` INT(11) NOT NULL DEFAULT 1,
  `devices` TEXT DEFAULT NULL,                            -- comma-separated serials đã bind
  `activated_at` DATETIME DEFAULT NULL,                   -- lần activate đầu tiên
  `expired_at` DATETIME DEFAULT NULL,                     -- NULL = chưa active
  `status` ENUM('unused','active','expired','banned') NOT NULL DEFAULT 'unused',
  `batch_id` VARCHAR(40) DEFAULT NULL,                    -- group key gen cùng batch (export CSV)
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_key_code` (`key_code`),
  KEY `idx_keys_game_status` (`game_id`, `status`),
  KEY `idx_keys_batch` (`batch_id`),
  CONSTRAINT `fk_keys_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: connect_logs — audit mọi request /connect
-- =============================================
CREATE TABLE IF NOT EXISTS `connect_logs` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `key_id` INT(11) DEFAULT NULL,
  `device_serial` VARCHAR(80) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `action` VARCHAR(40) NOT NULL,                          -- 'activate', 'verify_ok', 'rejected', 'rate_limited'
  `reason` VARCHAR(120) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_key` (`key_id`, `created_at`),
  KEY `idx_logs_ip_created` (`ip`, `created_at`),
  KEY `idx_logs_action` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: banned_devices — blacklist device cố tình crack
-- =============================================
CREATE TABLE IF NOT EXISTS `banned_devices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `device_serial` VARCHAR(80) NOT NULL,
  `reason` VARCHAR(200) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_banned_serial` (`device_serial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: settings — kv config (HMAC rotation, maintenance, etc.)
-- =============================================
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(60) NOT NULL,
  `v` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT INTO `settings` (`k`, `v`) VALUES
  ('maintenance', '0'),
  ('maintenance_message', 'Server bảo trì, vui lòng quay lại sau.'),
  ('hmac_rotated_at', NOW())
ON DUPLICATE KEY UPDATE `v` = `v`;

COMMIT;
