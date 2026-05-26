<?php
// logout.php – destroys session and redirects to login
require_once __DIR__ . '/includes/auth.php';
logoutUser();
header('Location: ' . APP_URL . '/index.php?msg=You+have+been+logged+out');
exit;
