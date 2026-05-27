-- =============================================================
-- ATTENDANCE & PERFORMANCE TRACKER - Database Setup Script
-- Run this in phpMyAdmin or MySQL CLI before starting the app
-- =============================================================

CREATE DATABASE IF NOT EXISTS attendance_tracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE attendance_tracker;

-- ----------------------------------------------------------------
-- TABLE: employees
-- Stores all employee and admin accounts
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  emp_code      VARCHAR(20) UNIQUE NOT NULL,        -- e.g. EMP001
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,              -- bcrypt hash
  role          ENUM('admin','employee') DEFAULT 'employee',
  is_super_admin TINYINT(1) DEFAULT 0,
  department    VARCHAR(100) DEFAULT NULL,
  position      VARCHAR(100) DEFAULT NULL,
  phone         VARCHAR(20) DEFAULT NULL,
  hire_date     DATE DEFAULT NULL,
  status        ENUM('active','inactive') DEFAULT 'active',
  avatar        VARCHAR(255) DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- TABLE: attendance
-- Records daily check-in / check-out times
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  employee_id   INT NOT NULL,
  work_date     DATE NOT NULL,
  check_in      DATETIME DEFAULT NULL,
  check_out     DATETIME DEFAULT NULL,
  total_hours   DECIMAL(5,2) DEFAULT 0.00,          -- computed on check-out
  status        ENUM('present','absent','late','half_day','on_leave') DEFAULT 'present',
  notes         TEXT DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_emp_date (employee_id, work_date),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- TABLE: leave_requests
-- Tracks leave applications and approvals
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leave_requests (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  employee_id   INT NOT NULL,
  leave_type    ENUM('annual','sick','emergency','unpaid','other') DEFAULT 'annual',
  start_date    DATE NOT NULL,
  end_date      DATE NOT NULL,
  total_days    INT NOT NULL DEFAULT 1,
  reason        TEXT DEFAULT NULL,
  status        ENUM('pending','approved','rejected') DEFAULT 'pending',
  reviewed_by   INT DEFAULT NULL,                   -- admin employee_id
  reviewed_at   TIMESTAMP NULL DEFAULT NULL,
  admin_notes   TEXT DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- TABLE: performance_kpis
-- KPI definitions (created by admin)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS performance_kpis (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  kpi_name      VARCHAR(150) NOT NULL,
  description   TEXT DEFAULT NULL,
  max_score     INT DEFAULT 100,
  category      VARCHAR(80) DEFAULT 'General',
  is_active     TINYINT(1) DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- TABLE: performance_scores
-- Monthly KPI scores per employee
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS performance_scores (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  employee_id   INT NOT NULL,
  kpi_id        INT NOT NULL,
  score         DECIMAL(5,2) NOT NULL DEFAULT 0,
  period_month  TINYINT NOT NULL,                   -- 1-12
  period_year   YEAR NOT NULL,
  comments      TEXT DEFAULT NULL,
  rated_by      INT DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_emp_kpi_period (employee_id, kpi_id, period_month, period_year),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (kpi_id) REFERENCES performance_kpis(id) ON DELETE CASCADE,
  FOREIGN KEY (rated_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- TABLE: notifications
-- In-app alerts (leave status updates, etc.)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  employee_id   INT NOT NULL,
  message       TEXT NOT NULL,
  is_read       TINYINT(1) DEFAULT 0,
  link          VARCHAR(255) DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- TABLE: login_devices
-- Track employee login devices for OTP verification
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_devices (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  employee_id     INT NOT NULL,
  device_fingerprint VARCHAR(64) NOT NULL,  -- SHA256 hash of IP + User-Agent
  device_name     VARCHAR(255) DEFAULT NULL,
  last_login      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_emp_device (employee_id, device_fingerprint),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- TABLE: login_otp
-- One-time passwords for device verification
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_otp (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  employee_id   INT NOT NULL,
  otp_code      VARCHAR(10) NOT NULL,
  device_fingerprint VARCHAR(64) NOT NULL,
  attempts      INT DEFAULT 0,
  max_attempts  INT DEFAULT 5,
  is_verified   TINYINT(1) DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at    TIMESTAMP NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_emp_otp (employee_id, otp_code)
) ENGINE=InnoDB;

-- =============================================================
-- SAMPLE DATA
-- =============================================================

-- Admin account  (password: Admin@1234)
INSERT INTO employees (emp_code, full_name, email, password_hash, role, is_super_admin, department, position, hire_date) VALUES
('ADM001', 'System Administrator', 'benjimoore1000@gmail.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@1234 (bcrypt)
 'admin', 1, 'Management', 'HR Administrator', '2020-01-01');

-- Employees  (password for all: Pass@1234)
INSERT INTO employees (emp_code, full_name, email, password_hash, role, department, position, phone, hire_date) VALUES
('EMP001', 'Nadia Opoku Bekoe',  'opokubekoenadia@gmail.com',   '$2y$10$zGrxU2PxMffl42Ybmmw74eQ8lS.XG8bXgQy2.WEFskSccqRneopxa', 'employee', 'Engineering',  'Software Engineer',    '0244000001', '2021-03-15'),
('EMP002', 'Kofi Asare',   'kitos0246@gmail.com',     '$2y$10$zGrxU2PxMffl42Ybmmw74eQ8lS.XG8bXgQy2.WEFskSccqRneopxa', 'employee', 'Marketing',    'Marketing Specialist', '0244000002', '2021-06-01'),
('EMP003', 'Claudia Naa Afoley Odai',  'odaiclaudia2005@gmail.com',   '$2y$10$zGrxU2PxMffl42Ybmmw74eQ8lS.XG8bXgQy2.WEFskSccqRneopxa', 'employee', 'Engineering',  'Senior Developer',     '0244000003', '2020-08-20'),
('EMP004', 'Nana Yaw Antwi', 'antwiyawgyimah19@gmail.com',   '$2y$10$zGrxU2PxMffl42Ybmmw74eQ8lS.XG8bXgQy2.WEFskSccqRneopxa', 'employee', 'Sales',        'Sales Executive',      '0244000004', '2022-01-10'),
('EMP005', 'Richmond Owusu',  'owusukwabenarichond9@gmail.com',     '$2y$10$zGrxU2PxMffl42Ybmmw74eQ8lS.XG8bXgQy2.WEFskSccqRneopxa', 'employee', 'Finance',      'Accountant',           '0244000005', '2021-11-05');

-- NOTE: The employee passwords above use a placeholder hash.
-- The setup script (setup_passwords.php) will fix them to Pass@1234 on first run.

-- Sample KPIs
INSERT INTO performance_kpis (kpi_name, description, max_score, category) VALUES
('Attendance Rate',      'Percentage of days present vs scheduled',    100, 'Attendance'),
('Task Completion',      'Percentage of assigned tasks completed on time', 100, 'Productivity'),
('Quality of Work',      'Peer and manager quality rating',             100, 'Quality'),
('Teamwork',             'Collaboration and communication score',        100, 'Soft Skills'),
('Initiative',           'Proactive contributions beyond assigned work', 100, 'Soft Skills');

-- Sample attendance (last 7 days for EMP001)
INSERT INTO attendance (employee_id, work_date, check_in, check_out, total_hours, status) VALUES
(2, DATE_SUB(CURDATE(),INTERVAL 6 DAY), DATE_SUB(CURDATE(),INTERVAL 6 DAY) + INTERVAL '08:02' HOUR_MINUTE, DATE_SUB(CURDATE(),INTERVAL 6 DAY) + INTERVAL '17:05' HOUR_MINUTE, 9.05, 'present'),
(2, DATE_SUB(CURDATE(),INTERVAL 5 DAY), DATE_SUB(CURDATE(),INTERVAL 5 DAY) + INTERVAL '08:15' HOUR_MINUTE, DATE_SUB(CURDATE(),INTERVAL 5 DAY) + INTERVAL '17:00' HOUR_MINUTE, 8.75, 'present'),
(2, DATE_SUB(CURDATE(),INTERVAL 4 DAY), DATE_SUB(CURDATE(),INTERVAL 4 DAY) + INTERVAL '09:10' HOUR_MINUTE, DATE_SUB(CURDATE(),INTERVAL 4 DAY) + INTERVAL '17:00' HOUR_MINUTE, 7.83, 'late'),
(2, DATE_SUB(CURDATE(),INTERVAL 3 DAY), DATE_SUB(CURDATE(),INTERVAL 3 DAY) + INTERVAL '08:00' HOUR_MINUTE, DATE_SUB(CURDATE(),INTERVAL 3 DAY) + INTERVAL '17:00' HOUR_MINUTE, 9.00, 'present'),
(2, DATE_SUB(CURDATE(),INTERVAL 2 DAY), DATE_SUB(CURDATE(),INTERVAL 2 DAY) + INTERVAL '08:05' HOUR_MINUTE, DATE_SUB(CURDATE(),INTERVAL 2 DAY) + INTERVAL '17:10' HOUR_MINUTE, 9.08, 'present');

-- Sample leave request
INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, total_days, reason, status) VALUES
(2, 'annual', DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 9 DAY), 3, 'Family vacation', 'pending'),
(3, 'sick',   DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 2, 'Flu recovery',    'approved');

-- Sample performance scores (current month)
INSERT INTO performance_scores (employee_id, kpi_id, score, period_month, period_year, comments, rated_by) VALUES
(2, 1, 92, MONTH(CURDATE()), YEAR(CURDATE()), 'Consistent attendance', 1),
(2, 2, 85, MONTH(CURDATE()), YEAR(CURDATE()), 'Good task completion',  1),
(2, 3, 88, MONTH(CURDATE()), YEAR(CURDATE()), 'Quality code reviews',  1),
(3, 1, 96, MONTH(CURDATE()), YEAR(CURDATE()), 'Excellent attendance',  1),
(3, 2, 91, MONTH(CURDATE()), YEAR(CURDATE()), 'Consistently exceeds',  1),
(4, 1, 78, MONTH(CURDATE()), YEAR(CURDATE()), 'A few absences',        1),
(4, 2, 82, MONTH(CURDATE()), YEAR(CURDATE()), 'Mostly on track',       1);
