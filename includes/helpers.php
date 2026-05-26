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
    } catch (Exception $e) {
        // Non-fatal – log silently
    }
}
