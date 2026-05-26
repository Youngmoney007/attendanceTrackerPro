-- =============================================================
-- qr_migration.sql
-- Run this AFTER the main database.sql to add QR check-in support
-- =============================================================

USE attendance_tracker;

-- ----------------------------------------------------------------
-- Add qr_token column to employees table
-- Each employee gets a permanent unique token embedded in their QR
-- ----------------------------------------------------------------
ALTER TABLE employees
  ADD COLUMN IF NOT EXISTS qr_token VARCHAR(64) UNIQUE DEFAULT NULL
    COMMENT 'Unique token encoded in employee QR code';

-- Generate a unique token for all existing employees that don't have one
UPDATE employees
SET qr_token = SHA2(CONCAT(id, emp_code, UUID(), RAND()), 256)
WHERE qr_token IS NULL;

-- ----------------------------------------------------------------
-- TABLE: qr_scans
-- Audit log of every QR scan attempt (success or fail)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS qr_scans (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  employee_id   INT DEFAULT NULL,               -- NULL if unrecognised token
  token         VARCHAR(64) NOT NULL,
  scan_type     ENUM('checkin','checkout') NOT NULL,
  scan_result   ENUM('success','already_done','no_checkin','invalid') NOT NULL,
  scanned_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address    VARCHAR(45) DEFAULT NULL,
  user_agent    VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Index for fast token lookups
-- ----------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_qr_token ON employees (qr_token);
CREATE INDEX IF NOT EXISTS idx_qr_scans_emp ON qr_scans (employee_id, scanned_at);
