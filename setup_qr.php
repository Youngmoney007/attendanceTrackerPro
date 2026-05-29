<?php
// =============================================================
// setup_qr.php
// Run this once to set up all QR-related database tables/columns.
// Safe to run multiple times — uses IF NOT EXISTS / IF NOT EXISTS.
// Access: http://localhost/attendance_tracker/setup_qr.php
// =============================================================

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>AttendTrack Pro – QR System Setup</h1><pre>\n";

try {
    $db = getDB();
    $results = [];

    // ---- 1. Add qr_token column to employees if missing ----
    $cols = $db->query("SHOW COLUMNS FROM employees LIKE 'qr_token'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE employees ADD COLUMN qr_token VARCHAR(64) UNIQUE DEFAULT NULL COMMENT 'Unique token encoded in employee QR code'");
        $results[] = "✅ Added 'qr_token' column to employees table.";
    } else {
        $results[] = "✓ 'qr_token' column already exists in employees table.";
    }

    // ---- 2. Generate tokens for employees that don't have one ----
    $stmt = $db->query("SELECT id, emp_code FROM employees WHERE qr_token IS NULL OR qr_token = ''");
    $noToken = $stmt->fetchAll();
    if ($noToken) {
        foreach ($noToken as $emp) {
            $token = bin2hex(random_bytes(32)); // 64-char hex
            $db->prepare("UPDATE employees SET qr_token = ? WHERE id = ?")->execute([$token, $emp['id']]);
        }
        $results[] = "✅ Generated QR tokens for " . count($noToken) . " employee(s): " . implode(', ', array_column($noToken, 'emp_code'));
    } else {
        $results[] = "✓ All employees already have QR tokens.";
    }

    // ---- 3. Create qr_scans table if missing ----
    $db->exec("
        CREATE TABLE IF NOT EXISTS qr_scans (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            employee_id   INT DEFAULT NULL,
            token         VARCHAR(64) NOT NULL,
            scan_type     ENUM('checkin','checkout') NOT NULL,
            scan_result   ENUM('success','already_done','no_checkin','invalid') NOT NULL,
            scanned_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address    VARCHAR(45) DEFAULT NULL,
            user_agent    VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    $results[] = "✅ qr_scans table ready.";

    // ---- 4. Create office_qr_codes table if missing ----
    $db->exec("
        CREATE TABLE IF NOT EXISTS office_qr_codes (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            qr_token    VARCHAR(64) UNIQUE NOT NULL,
            label       VARCHAR(100) DEFAULT 'Main Entrance',
            is_active   TINYINT(1) DEFAULT 1,
            created_by  INT DEFAULT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    $results[] = "✅ office_qr_codes table ready.";

    // ---- 5. Create checkin_sessions table if missing ----
    $db->exec("
        CREATE TABLE IF NOT EXISTS checkin_sessions (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            session_token VARCHAR(64) NOT NULL,
            qr_token      VARCHAR(64) NOT NULL,
            ip_address    VARCHAR(45) DEFAULT NULL,
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at    DATETIME NOT NULL,
            used          TINYINT(1) DEFAULT 0
        ) ENGINE=InnoDB
    ");
    $results[] = "✅ checkin_sessions table ready.";

    // ---- 6. Create indexes (safe – won't error if they exist) ----
    try {
        $db->exec("CREATE INDEX idx_qr_token ON employees (qr_token)");
        $results[] = "✅ Created index idx_qr_token on employees.";
    } catch (Exception $e) {
        $results[] = "✓ Index idx_qr_token already exists.";
    }

    try {
        $db->exec("CREATE INDEX idx_qr_scans_emp ON qr_scans (employee_id, scanned_at)");
        $results[] = "✅ Created index idx_qr_scans_emp on qr_scans.";
    } catch (Exception $e) {
        $results[] = "✓ Index idx_qr_scans_emp already exists.";
    }

    // ---- 7. Verify: show employee tokens ----
    $results[] = "\n--- Employee QR Tokens ---";
    $emps = $db->query("SELECT emp_code, full_name, SUBSTRING(qr_token, 1, 16) AS token_preview FROM employees ORDER BY id")->fetchAll();
    foreach ($emps as $emp) {
        $results[] = "  {$emp['emp_code']} ({$emp['full_name']}): {$emp['token_preview']}…";
    }

    // ---- 8. Check if office QR exists ----
    $officeQR = $db->query("SELECT qr_token, label FROM office_qr_codes WHERE is_active = 1 LIMIT 1")->fetch();
    if ($officeQR) {
        $results[] = "\n--- Active Office QR ---";
        $results[] = "  Label: {$officeQR['label']}";
        $results[] = "  Check-in URL: " . APP_URL . "/checkin.php?t=" . $officeQR['qr_token'];
    } else {
        $results[] = "\n⚠️  No active office QR code yet. Visit admin/office_qr.php to generate one.";
    }

    // ---- Done ----
    $results[] = "\n========================================";
    $results[] = "🎉 QR System setup complete!";
    $results[] = "";
    $results[] = "Next steps:";
    $results[] = "  1. Kiosk (camera scan): " . APP_URL . "/kiosk.php";
    $results[] = "  2. Admin QR management: " . APP_URL . "/admin/office_qr.php";
    $results[] = "  3. Employee QR card:    Log in as employee → My QR Code";
    $results[] = "";
    $results[] = "To test the kiosk:";
    $results[] = "  - Open kiosk.php in a browser with a webcam";
    $results[] = "  - Show an employee's QR code to the camera";
    $results[] = "  - Or use the 'Manual Token Entry' box at the bottom-right";
    $results[] = "    and paste a full 64-char token from the list above";

    echo implode("\n", $results);

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Make sure your database is running and config.php has correct credentials.\n";
}

echo "\n</pre>";
?>
