-- Migration: add payment columns to reservas table
-- Run once in phpMyAdmin or via MySQL CLI

ALTER TABLE `reservas`
    ADD COLUMN `monto` DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `personas`,
    ADD COLUMN `stripe_session_id` VARCHAR(200) NULL AFTER `monto`,
    ADD COLUMN `pagado` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stripe_session_id`;
