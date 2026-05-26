<?php
// =============================================================
// api/attendance.php  –  Check-in / Check-out endpoint
// Accepts POST, redirects back to employee dashboard
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$cu = currentUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/employee/dashboard.php');
}

$action = trim($_POST['action'] ?? '');
$today  = date('Y-m-d');
$now    = date('Y-m-d H:i:s');

// Fetch existing record for today
$stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id=? AND work_date=? LIMIT 1");
$stmt->execute([$cu['id'], $today]);
$existing = $stmt->fetch();

if ($action === 'checkin') {
    if ($existing && $existing['check_in']) {
        // Already checked in
        redirect(APP_URL . '/employee/dashboard.php');
    }

    $checkInTime = date('H:i', strtotime($now));
    $status      = ($checkInTime > LATE_THRESHOLD) ? 'late' : 'present';

    if ($existing) {
        // Record exists without check-in (edge case)
        $db->prepare("UPDATE attendance SET check_in=?, status=? WHERE id=?")
           ->execute([$now, $status, $existing['id']]);
    } else {
        $db->prepare("INSERT INTO attendance (employee_id, work_date, check_in, status) VALUES (?,?,?,?)")
           ->execute([$cu['id'], $today, $now, $status]);
    }
}

if ($action === 'checkout') {
    if (!$existing || !$existing['check_in']) {
        // Can't check out without checking in
        redirect(APP_URL . '/employee/dashboard.php');
    }
    if ($existing['check_out']) {
        // Already checked out
        redirect(APP_URL . '/employee/dashboard.php');
    }

    $hours  = calcHours($existing['check_in'], $now);
    $status = deriveStatus($existing['check_in'], $hours);

    $db->prepare("UPDATE attendance SET check_out=?, total_hours=?, status=? WHERE id=?")
       ->execute([$now, $hours, $status, $existing['id']]);
}

redirect(APP_URL . '/employee/dashboard.php');
