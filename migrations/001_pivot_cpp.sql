-- HCLOU-LICENSE — Migration: pivot Lua → C++ mod menu (ngày 2026-05-25)
-- Run sau khi đã chạy database.sql gốc. Hoặc fresh install: database.sql đã chứa columns mới.

ALTER TABLE `scripts`
  ADD COLUMN `modname` VARCHAR(120) NOT NULL DEFAULT '' AFTER `version`,
  ADD COLUMN `mod_status` ENUM('on','off') NOT NULL DEFAULT 'on' AFTER `modname`,
  ADD COLUMN `credit` TEXT DEFAULT NULL AFTER `mod_status`,
  MODIFY COLUMN `body` LONGTEXT DEFAULT NULL;
