<?php
// =============================================================
// includes/helpers.php
// Reusable utility functions across the application
// =============================================================

// -----------------------------------------------------------
// Sanitize output to prevent XSS
// -----------------------------------------------------------
function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// -----------------------------------------------------------
// Email validation - accepts Gmail or company domain emails
// -----------------------------------------------------------
function isValidEmail(string $email): bool {
    $normalized = strtolower(trim($email));
    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return str_ends_with($normalized, '@gmail.com') 
        || str_ends_with($normalized, COMPANY_EMAIL_DOMAIN);
}

// Keep backward compatibility
function isGmailEmail(string $email): bool {
    return isValidEmail($email);
}

// -----------------------------------------------------------
// Redirect helper
// -----------------------------------------------------------
function redirect(string $url): never {
    header("Location: $url");
    exit;
}

// -----------------------------------------------------------
// Calculate working hours between two datetime strings
// Returns float (hours) or 0 if invalid
// -----------------------------------------------------------
function calcHours(string $checkIn, string $checkOut): float {
    $in  = strtotime($checkIn);
    $out = strtotime($checkOut);
    if (!$in || !$out || $out <= $in) return 0.0;
    return round(($out - $in) / 3600, 2);
}

// -----------------------------------------------------------
// Determine attendance status from check-in time and hours
// -----------------------------------------------------------
function deriveStatus(string $checkIn, float $hours): string {
    $checkInTime = date('H:i', strtotime($checkIn));

    if ($hours < 0.1)                   return 'absent';
    if ($hours < HALF_DAY_HOURS)        return 'half_day';
    if ($checkInTime > LATE_THRESHOLD)  return 'late';
    return 'present';
}

// -----------------------------------------------------------
// Format decimal hours to "Xh Ym" string
// -----------------------------------------------------------
function formatHours(float $hours): string {
    $h = (int) floor($hours);
    $m = (int) round(($hours - $h) * 60);
    return "{$h}h {$m}m";
}

// -----------------------------------------------------------
// Return badge HTML for attendance status
// -----------------------------------------------------------
function statusBadge(string $status): string {
    $map = [
        'present'  => 'badge-present',
        'absent'   => 'badge-absent',
        'late'     => 'badge-late',
        'half_day' => 'badge-halfday',
        'on_leave' => 'badge-leave',
    ];
    $cls   = $map[$status] ?? 'badge-default';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"badge $cls\">$label</span>";
}

// -----------------------------------------------------------
// Generate a random OTP code
// -----------------------------------------------------------
function generateOTP(int $length = OTP_LENGTH): string {
    return str_pad((string)random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
}

// -----------------------------------------------------------
// Send OTP via SMTP email
// -----------------------------------------------------------
function sendOTPEmail(string $email, string $otp, string $fullName = ''): bool {
    $message = "Hello {$fullName},\n\n";
    $message .= "Your code is {$otp}\n\n";
    $message .= "This code expires in " . OTP_EXPIRY_MINS . " minutes.\n\n";
    $message .= "If you did not request this, please ignore this email.\n\n";
    $message .= "Best regards,\nAttendTrack Pro Team";

    $subject = 'Your Attendance Tracker Login Code';
    return sendEmail($email, $subject, $message);
}

// -----------------------------------------------------------
// Send a plain-text email using configured SMTP or mail()
// -----------------------------------------------------------
function sendEmail(string $to, string $subject, string $body): bool {
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    if (SMTP_HOST && SMTP_PORT && SMTP_USERNAME && SMTP_PASSWORD) {
        return sendEmailViaSmtp($to, $subject, $body, $headers);
    }

    return mail($to, $subject, $body, $headers);
}

// -----------------------------------------------------------
// Send email via SMTP (supports TLS/STARTTLS)
// -----------------------------------------------------------
function sendEmailViaSmtp(string $to, string $subject, string $body, string $headers = ''): bool {
    $host     = SMTP_HOST;
    $port     = SMTP_PORT;
    $username = SMTP_USERNAME;
    $password = SMTP_PASSWORD;
    $from     = SMTP_FROM_EMAIL;
    $name     = SMTP_FROM_NAME;

    $remote = ($port === 465 ? 'ssl://' : '') . $host . ':' . $port;
    $timeout = 30;
    $socket = stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $response = trim(fgets($socket, 515));
    if (strpos($response, '220') !== 0) {
        fclose($socket);
        return false;
    }

    $hostname = gethostname() ?: 'localhost';
    fwrite($socket, "EHLO {$hostname}\r\n");
    $ehlo = trim(fgets($socket, 515));
    if (strpos($ehlo, '250') !== 0) {
        fclose($socket);
        return false;
    }

    if ($port !== 465) {
        fwrite($socket, "STARTTLS\r\n");
        $tlsResponse = trim(fgets($socket, 515));
        if (strpos($tlsResponse, '220') !== 0) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        fwrite($socket, "EHLO {$hostname}\r\n");
        trim(fgets($socket, 515));
    }

    fwrite($socket, "AUTH LOGIN\r\n");
    trim(fgets($socket, 515));
    fwrite($socket, base64_encode($username) . "\r\n");
    trim(fgets($socket, 515));
    fwrite($socket, base64_encode($password) . "\r\n");
    $authResponse = trim(fgets($socket, 515));
    if (strpos($authResponse, '235') !== 0) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "MAIL FROM:<{$from}>\r\n");
    trim(fgets($socket, 515));
    fwrite($socket, "RCPT TO:<{$to}>\r\n");
    $rcpt = trim(fgets($socket, 515));
    if (strpos($rcpt, '250') !== 0 && strpos($rcpt, '251') !== 0) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "DATA\r\n");
    trim(fgets($socket, 515));

    $message = "From: {$name} <{$from}>\r\n";
    $message .= "To: {$to}\r\n";
    $message .= "Subject: {$subject}\r\n";
    $message .= "{$headers}\r\n";
    $message .= "\r\n{$body}\r\n.\r\n";

    fwrite($socket, $message);
    $dataResponse = trim(fgets($socket, 515));
    if (strpos($dataResponse, '250') !== 0) {
        fclose($socket);
        return false;
    }

    fwrite($socket, "QUIT\r\n");
    trim(fgets($socket, 515));
    fclose($socket);
    return true;
}

// -----------------------------------------------------------
// Send a notification email to an employee by ID
// -----------------------------------------------------------
function sendNotificationEmail(int $employeeId, string $subject, string $message, string $link = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT email, full_name FROM employees WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$employeeId]);
        $user = $stmt->fetch();
        if (!$user || !$user['email']) {
            return;
        }

        $body = "Hello {$user['full_name']},\n\n{$message}\n\n";
        if ($link) {
            $body .= "View details: {$link}\n\n";
        }
        $body .= "Best regards,\nAttendTrack Pro Team";

        sendEmail($user['email'], $subject, $body);
    } catch (Exception $e) {
        // Do not interrupt application flow if email fails
    }
}

// -----------------------------------------------------------
// Get user's device fingerprint (IP + User-Agent)
// -----------------------------------------------------------
function getDeviceFingerprint(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . $userAgent);
}

// -----------------------------------------------------------
// Return badge HTML for leave status
// -----------------------------------------------------------
function leaveStatusBadge(string $status): string {
    $map = [
        'pending'  => 'badge-pending',
        'approved' => 'badge-present',
        'rejected' => 'badge-absent',
    ];
    $cls = $map[$status] ?? 'badge-default';
    return "<span class=\"badge $cls\">" . ucfirst($status) . "</span>";
}

// -----------------------------------------------------------
// Calculate number of working days between two dates
// (Mon-Fri only, simple version without holiday support)
// -----------------------------------------------------------
function workingDaysBetween(string $start, string $end): int {
    $days = 0;
    $current = strtotime($start);
    $endTs   = strtotime($end);
    while ($current <= $endTs) {
        $dow = (int) date('N', $current); // 1=Mon … 7=Sun
        if ($dow < 6) $days++;
        $current = strtotime('+1 day', $current);
    }
    return $days;
}

// -----------------------------------------------------------
// Escape a value for use in CSV output
// -----------------------------------------------------------
function csvEscape(mixed $val): string {
    $val = (string) $val;
    if (str_contains($val, ',') || str_contains($val, '"') || str_contains($val, "\n")) {
        $val = '"' . str_replace('"', '""', $val) . '"';
    }
    return $val;
}

// -----------------------------------------------------------
// Send a JSON response (for API endpoints)
// -----------------------------------------------------------
function jsonResponse(bool $success, string $message, array $data = []): never {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// -----------------------------------------------------------
// Add a notification for a specific employee
// -----------------------------------------------------------
function addNotification(int $employeeId, string $message, string $link = ''): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (employee_id, message, link) VALUES (?, ?, ?)");
        $stmt->execute([$employeeId, $message, $link]);

        // Send an email alert to the recipient as well
        sendNotificationEmail($employeeId, 'AttendTrack Pro Notification', $message, $link);
    } catch (Exception $e) {
        // Non-fatal – log silently
    }
}
