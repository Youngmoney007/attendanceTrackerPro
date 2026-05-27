<?php
// =============================================================
// setup_passwords.php
// One-time sample password reset script for local setup only.
// Run once, then delete this file for security.
// =============================================================

require_once __DIR__ . '/includes/config.php';

try {
    $db = getDB();

    $accounts = [
        ['email' => 'benjimoore1000@gmail.com',         'password' => 'Admin@1234'],
        ['email' => 'opokubekoenadia@gmail.com',        'password' => 'Pass@1234'],
        ['email' => 'kitos0246@gmail.com',              'password' => 'Pass@1234'],
        ['email' => 'odaiclaudia2005@gmail.com',        'password' => 'Pass@1234'],
        ['email' => 'antwiyawgyimah19@gmail.com',       'password' => 'Pass@1234'],
        ['email' => 'owusukwabenarichond9@gmail.com',   'password' => 'Pass@1234'],
    ];

    echo "<h1>Sample Password Reset</h1>\n";
    echo "<p>Updating sample account password hashes...</p>\n";

    $update = $db->prepare("UPDATE employees SET password_hash = ? WHERE email = ?");

    foreach ($accounts as $account) {
        $hash = password_hash($account['password'], PASSWORD_BCRYPT);
        $update->execute([$hash, $account['email']]);

        if ($update->rowCount()) {
            echo "<p>Updated password for {$account['email']}</p>\n";
        } else {
            echo "<p style=\"color:darkorange\">No account found for {$account['email']}.</p>\n";
        }
    }

    echo "<p><strong>Done.</strong> Delete <code>setup_passwords.php</code> after use.</p>\n";
    echo "<p><a href=\"index.php\">Go to Login</a></p>\n";
} catch (Exception $e) {
    echo "<h1>Error</h1>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
}
