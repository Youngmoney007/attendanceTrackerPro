<?php
// =============================================================
// SETUP SCRIPT - Create Admin Account
// Visit this file once in your browser, then DELETE IT
// =============================================================

require_once __DIR__ . '/includes/config.php';

// Admin credentials to create
$adminEmail = 'benjimoore1000@gmail.com';
$adminPassword = 'Admin@1234';  // CHANGE THIS to your desired password
$adminName = 'Admin User';

// Validation
if (empty($adminPassword) || strlen($adminPassword) < 6) {
    die('Error: Password must be at least 6 characters.');
}

try {
    $db = getDB();
    
    // Check if admin already exists
    $check = $db->prepare("SELECT id FROM employees WHERE email = ? LIMIT 1");
    $check->execute([$adminEmail]);
    if ($check->fetch()) {
        die("❌ Admin account already exists for {$adminEmail}. Delete the account first or use a different email.");
    }
    
    // Hash password
    $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
    
    // Insert admin user
    $stmt = $db->prepare("
        INSERT INTO employees (emp_code, full_name, email, password_hash, role, is_super_admin, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        'ADMIN001',
        $adminName,
        $adminEmail,
        $passwordHash,
        'admin',
        1,
        'active'
    ]);
    
    echo "<h2>✅ Admin Account Created Successfully!</h2>";
    echo "<p><strong>Email:</strong> {$adminEmail}</p>";
    echo "<p><strong>Password:</strong> {$adminPassword}</p>";
    echo "<p><strong>⚠️  IMPORTANT:</strong> Delete this file (setup-admin.php) after you log in!</p>";
    echo "<p><a href='index.php'>Go to Login</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage());
}
?>
