<?php
// =============================================================
// api/qr_scan.php
// Public endpoint called by the kiosk scanner when a QR is read.
// Accepts POST with { token, type } or GET ?token=...&type=...
// Returns JSON so the kiosk page can react immediately.
// No login required – authentication is the QR token itself.
// =============================================================

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow same-server kiosk page

// ---- Read inputs -------------------------------------------
$input = $_POST;
if (empty($input)) {
    // Support JSON body from fetch()
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

$token    = trim($input['token']    ?? ($_GET['token']    ?? ''));
$scanType = trim($input['type']     ?? ($_GET['type']     ?? 'checkin')); // checkin | checkout
$ip       = $_SERVER['REMOTE_ADDR']     ?? '';
$ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// ---- Validate inputs ----------------------------------------
if (!$token) {
    logScan(null, '', $scanType, 'invalid', $ip, $ua);
    jsonResponse(false, 'No QR token received.', ['result' => 'invalid']);
}

if (!in_array($scanType, ['checkin', 'checkout'])) {
    $scanType = 'checkin';
}

$db = getDB();

// ---- Look up employee by token ------------------------------
$stmt = $db->prepare("
    SELECT id, full_name, emp_code, department, position, status
    FROM employees
    WHERE qr_token = ? AND status = 'active'
    LIMIT 1
");
$stmt->execute([$token]);
$employee = $stmt->fetch();

if (!$employee) {
    logScan(null, $token, $scanType, 'invalid', $ip, $ua);
    jsonResponse(false, 'Unrecognised QR code. Please contact HR.', ['result' => 'invalid']);
}

$empId = $employee['id'];
$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');

// ---- Fetch today's attendance record -----------------------
$stmtAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id=? AND work_date=? LIMIT 1");
$stmtAtt->execute([$empId, $today]);
$att = $stmtAtt->fetch();

// ---- Process check-in --------------------------------------
if ($scanType === 'checkin') {
    if ($att && $att['check_in']) {
        // Already checked in today
        logScan($empId, $token, 'checkin', 'already_done', $ip, $ua);
        jsonResponse(true, "Already checked in at " . date('H:i', strtotime($att['check_in'])) . ".", [
            'result'   => 'already_done',
            'employee' => buildEmployeeData($employee),
            'time'     => date('H:i', strtotime($att['check_in'])),
            'status'   => $att['status'],
        ]);
    }

    // Determine late status
    $checkInTime = date('H:i', strtotime($now));
    $attStatus   = ($checkInTime > LATE_THRESHOLD) ? 'late' : 'present';

    if ($att) {
        // Record existed (edge case – no check_in yet)
        $db->prepare("UPDATE attendance SET check_in=?, status=? WHERE id=?")
           ->execute([$now, $attStatus, $att['id']]);
    } else {
        $db->prepare("INSERT INTO attendance (employee_id, work_date, check_in, status) VALUES (?,?,?,?)")
           ->execute([$empId, $today, $now, $attStatus]);
    }

    logScan($empId, $token, 'checkin', 'success', $ip, $ua);

    $msg = $attStatus === 'late'
        ? "Checked in (late) at " . date('H:i') . ". Expected by " . LATE_THRESHOLD . "."
        : "Checked in at " . date('H:i') . ". Good morning, {$employee['full_name']}!";

    jsonResponse(true, $msg, [
        'result'   => 'success',
        'employee' => buildEmployeeData($employee),
        'time'     => date('H:i'),
        'status'   => $attStatus,
    ]);
}

// ---- Process check-out -------------------------------------
if ($scanType === 'checkout') {
    if (!$att || !$att['check_in']) {
        logScan($empId, $token, 'checkout', 'no_checkin', $ip, $ua);
        jsonResponse(false, "No check-in found today for {$employee['full_name']}. Please check in first.", [
            'result'   => 'no_checkin',
            'employee' => buildEmployeeData($employee),
        ]);
    }

    if ($att['check_out']) {
        // Already checked out
        logScan($empId, $token, 'checkout', 'already_done', $ip, $ua);
        jsonResponse(true, "Already checked out at " . date('H:i', strtotime($att['check_out'])) . ".", [
            'result'   => 'already_done',
            'employee' => buildEmployeeData($employee),
            'time'     => date('H:i', strtotime($att['check_out'])),
            'hours'    => $att['total_hours'],
        ]);
    }

    // Calculate hours and update
    $hours     = calcHours($att['check_in'], $now);
    $newStatus = deriveStatus($att['check_in'], $hours);

    $db->prepare("UPDATE attendance SET check_out=?, total_hours=?, status=? WHERE id=?")
       ->execute([$now, $hours, $newStatus, $att['id']]);

    logScan($empId, $token, 'checkout', 'success', $ip, $ua);

    jsonResponse(true, "Checked out at " . date('H:i') . ". Total: " . formatHours($hours) . ". See you tomorrow!", [
        'result'   => 'success',
        'employee' => buildEmployeeData($employee),
        'time'     => date('H:i'),
        'hours'    => $hours,
        'hours_fmt'=> formatHours($hours),
        'status'   => $newStatus,
    ]);
}

// ---- Helpers -----------------------------------------------
function logScan(?int $empId, string $token, string $type, string $result, string $ip, string $ua): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO qr_scans (employee_id,token,scan_type,scan_result,ip_address,user_agent) VALUES (?,?,?,?,?,?)")
           ->execute([$empId, $token, $type, $result, $ip, $ua]);
    } catch (Exception $e) { /* non-fatal */ }
}

function buildEmployeeData(array $emp): array {
    return [
        'id'         => $emp['id'],
        'full_name'  => $emp['full_name'],
        'emp_code'   => $emp['emp_code'],
        'department' => $emp['department'] ?? '',
        'position'   => $emp['position']   ?? '',
        'initials'   => strtoupper(substr($emp['full_name'],0,1)
                      . substr(strrchr($emp['full_name'],' '),1,1)),
    ];
}
