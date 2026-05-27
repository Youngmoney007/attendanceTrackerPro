<?php
// =============================================================
// fix_sample_data.php
// One-time database fix for sample login credentials.
// Run once in your browser, then delete this file.
// =============================================================

require_once __DIR__ . '/includes/config.php';

$accounts = [
    ['emp_code' => 'ADM001', 'full_name' => 'System Administrator', 'email' => 'benjimoore1000@gmail.com', 'password' => 'Admin@1234', 'role' => 'admin', 'is_super_admin' => 1],
    ['emp_code' => 'EMP001', 'full_name' => 'Nadia Opoku Bekoe', 'email' => 'opokubekoenadia@gmail.com', 'password' => 'Pass@1234', 'role' => 'employee', 'is_super_admin' => 0],
    ['emp_code' => 'EMP002', 'full_name' => 'Kofi Asare', 'email' => 'kitos0246@gmail.com', 'password' => 'Pass@1234', 'role' => 'employee', 'is_super_admin' => 0],
    ['emp_code' => 'EMP003', 'full_name' => 'Claudia Naa Afoley Odai', 'email' => 'odaiclaudia2005@gmail.com', 'password' => 'Pass@1234', 'role' => 'employee', 'is_super_admin' => 0],
    ['emp_code' => 'EMP004', 'full_name' => 'Nana Yaw Antwi', 'email' => 'antwiyawgyimah19@gmail.com', 'password' => 'Pass@1234', 'role' => 'employee', 'is_super_admin' => 0],
    ['emp_code' => 'EMP005', 'full_name' => 'Richmond Owusu', 'email' => 'owusukwabenarichond9@gmail.com', 'password' => 'Pass@1234', 'role' => 'employee', 'is_super_admin' => 0],
];

try {
    $db = getDB();

    // Make sure the new super-admin flag exists.
    try {
        $db->query("SELECT is_super_admin FROM employees LIMIT 1");
    } catch (Exception $inner) {
        $db->exec("ALTER TABLE employees ADD COLUMN is_super_admin TINYINT(1) DEFAULT 0 AFTER role");
    }

    $update = $db->prepare(
        "UPDATE employees SET full_name = ?, email = ?, password_hash = ?, role = ?, is_super_admin = ?, status = 'active' WHERE emp_code = ?"
    );

    echo "<h1>Sample Data Fix</h1>\n";
    echo "<p>Updating sample accounts...</p>\n";

    foreach ($accounts as $account) {
        $hash = password_hash($account['password'], PASSWORD_BCRYPT);
        $update->execute([
            $account['full_name'],
            $account['email'],
            $hash,
            $account['role'],
            $account['is_super_admin'],
            $account['emp_code'],
        ]);

        if ($update->rowCount()) {
            echo "<p>Updated {$account['emp_code']} to {$account['email']} ({$account['role']}).</p>\n";
        } else {
            echo "<p style='color:darkorange;'>No row found for {$account['emp_code']}. If this is a fresh install, import database.sql first.</p>\n";
        }
    }

    echo "<p><strong>Complete.</strong> Delete <code>fix_sample_data.php</code> after use.</p>\n";
    echo "<p><a href=\"index.php\">Go to Login</a></p>\n";
} catch (Exception $e) {
    echo "<h1>Error</h1>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
}
