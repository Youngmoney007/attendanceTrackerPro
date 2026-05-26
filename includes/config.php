<?php
// =============================================================
// includes/config.php
// Central configuration file - edit DB credentials here
// =============================================================

// ---- Database Settings ----------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Change for production
define('DB_PASS', '');              // Change for production
define('DB_NAME', 'attendance_tracker');
define('DB_CHARSET', 'utf8mb4');

// ---- Application Settings ------------------------------------
define('APP_NAME',    'AttendTrack Pro');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/attendance_tracker');

// ---- Session Settings ----------------------------------------
define('SESSION_LIFETIME', 3600);  // 1 hour in seconds
define('SESSION_NAME',     'att_session');

// ---- Work Schedule (for late / half-day calculation) ---------
define('WORK_START',      '08:00');  // Expected check-in
define('LATE_THRESHOLD',   '09:00'); // After this = late
define('HALF_DAY_HOURS',   4.0);     // < this = half day
define('FULL_DAY_HOURS',   8.0);     // Target hours

// ---- Timezone ------------------------------------------------
date_default_timezone_set('Africa/Accra');  // Change to your timezone

// =============================================================
// Database connection via PDO
// =============================================================
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST
             . ";dbname=" . DB_NAME
             . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log and show a friendly error
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed. Check config.php.'
            ]));
        }
    }

    return $pdo;
}
