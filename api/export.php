<?php
// =============================================================
// api/export.php  –  CSV Export Endpoint
// type: attendance_personal | attendance_admin | performance | leave
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$cu = currentUser();
$db = getDB();

$type  = $_GET['type']  ?? '';
$month = $_GET['month'] ?? date('Y-m');

// Output CSV header helper
function csvHeader(string $filename): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
}

// Write a row
function csvRow(array $cells): void {
    echo implode(',', array_map('csvEscape', $cells)) . "\r\n";
}

// ============================================================
switch ($type) {

    // ---- Personal attendance CSV -------------------------
    case 'attendance_personal':
        csvHeader("my_attendance_{$month}.csv");
        csvRow(['Date','Day','Check In','Check Out','Hours','Status','Notes']);
        $stmt = $db->prepare("
          SELECT * FROM attendance
          WHERE employee_id=? AND DATE_FORMAT(work_date,'%Y-%m')=?
          ORDER BY work_date
        ");
        $stmt->execute([$cu['id'], $month]);
        foreach ($stmt->fetchAll() as $r) {
            csvRow([
                $r['work_date'],
                date('D', strtotime($r['work_date'])),
                $r['check_in']  ? date('H:i', strtotime($r['check_in']))  : '',
                $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '',
                $r['total_hours'],
                $r['status'],
                $r['notes'] ?? '',
            ]);
        }
        break;

    // ---- Admin attendance CSV (monthly) ------------------
    case 'attendance_admin':
        requireAdmin();
        $dept = $_GET['dept'] ?? '';
        csvHeader("attendance_report_{$month}.csv");
        csvRow(['Emp Code','Full Name','Department','Date','Day','Check In','Check Out','Hours','Status']);

        $sql = "
          SELECT a.*, e.full_name, e.emp_code, e.department
          FROM attendance a
          JOIN employees e ON e.id=a.employee_id
          WHERE DATE_FORMAT(a.work_date,'%Y-%m')=:m
            AND (:dept='' OR e.department=:dept2)
          ORDER BY e.department, e.full_name, a.work_date
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':m' => $month, ':dept' => $dept, ':dept2' => $dept]);
        foreach ($stmt->fetchAll() as $r) {
            csvRow([
                $r['emp_code'],
                $r['full_name'],
                $r['department'],
                $r['work_date'],
                date('D', strtotime($r['work_date'])),
                $r['check_in']  ? date('H:i', strtotime($r['check_in']))  : '',
                $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '',
                $r['total_hours'],
                $r['status'],
            ]);
        }
        break;

    // ---- Performance CSV ---------------------------------
    case 'performance':
        requireAdmin();
        [$fy, $fm] = explode('-', $month);
        csvHeader("performance_report_{$month}.csv");
        csvRow(['Emp Code','Full Name','Department','KPI','Category','Score','Max Score','Comments']);

        $stmt = $db->prepare("
          SELECT e.emp_code, e.full_name, e.department,
                 k.kpi_name, k.category, k.max_score,
                 ps.score, ps.comments
          FROM performance_scores ps
          JOIN employees e ON e.id=ps.employee_id
          JOIN performance_kpis k ON k.id=ps.kpi_id
          WHERE ps.period_month=? AND ps.period_year=?
          ORDER BY e.department, e.full_name, k.category
        ");
        $stmt->execute([(int)$fm, (int)$fy]);
        foreach ($stmt->fetchAll() as $r) {
            csvRow([
                $r['emp_code'],
                $r['full_name'],
                $r['department'],
                $r['kpi_name'],
                $r['category'],
                $r['score'],
                $r['max_score'],
                $r['comments'] ?? '',
            ]);
        }
        break;

    // ---- Leave CSV ---------------------------------------
    case 'leave':
        requireAdmin();
        csvHeader("leave_report_{$month}.csv");
        csvRow(['Emp Code','Full Name','Department','Leave Type','Start','End','Days','Reason','Status','Admin Notes']);

        $stmt = $db->prepare("
          SELECT lr.*, e.emp_code, e.full_name, e.department
          FROM leave_requests lr
          JOIN employees e ON e.id=lr.employee_id
          WHERE DATE_FORMAT(lr.start_date,'%Y-%m')=?
          ORDER BY lr.start_date, e.full_name
        ");
        $stmt->execute([$month]);
        foreach ($stmt->fetchAll() as $r) {
            csvRow([
                $r['emp_code'],
                $r['full_name'],
                $r['department'],
                $r['leave_type'],
                $r['start_date'],
                $r['end_date'],
                $r['total_days'],
                $r['reason'] ?? '',
                $r['status'],
                $r['admin_notes'] ?? '',
            ]);
        }
        break;

    default:
        header('HTTP/1.1 400 Bad Request');
        echo 'Invalid export type.';
}

exit;
