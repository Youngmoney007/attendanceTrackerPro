-- =============================================================
-- shared_qr_migration.sql
-- Adds the shared office QR code system.
-- Run this in phpMyAdmin after database.sql
-- =============================================================

USE attendance_tracker;

-- ----------------------------------------------------------------
-- TABLE: office_qr_codes
-- Stores the single shared QR code for the office entrance.
-- The admin can regenerate it at any time (old one expires).
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS office_qr_codes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  qr_token    VARCHAR(64) UNIQUE NOT NULL,          -- embedded in the printed QR
  label       VARCHAR(100) DEFAULT 'Main Entrance', -- e.g. "Floor 2", "Side Door"
  is_active   TINYINT(1) DEFAULT 1,
  created_by  INT DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- TABLE: checkin_sessions
-- Tracks each phone session that scanned the shared QR.
-- Prevents the same phone submitting twice in quick succession.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS checkin_sessions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  session_token VARCHAR(64) NOT NULL,               -- random token given to the phone
  qr_token      VARCHAR(64) NOT NULL,               -- which office QR was scanned
  ip_address    VARCHAR(45) DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME NOT NULL,                  -- session valid for 30 min
  used          TINYINT(1) DEFAULT 0                -- 1 once attendance submitted
) ENGINE=InnoDB;

-- Index for fast lookups
CREATE INDEX IF NOT EXISTS idx_checkin_sess ON checkin_sessions (session_token, expires_at);
CREATE INDEX IF NOT EXISTS idx_office_qr    ON office_qr_codes  (qr_token, is_active);

-- Insert a default office QR code (token generated on first admin visit)
-- The admin/office_qr.php page handles generation automatically.
