<?php
// =============================================================
// admin/attendance_overview.php
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();
$db = getDB();

$filterDate  = $_GET['date']  ?? date('Y-m-d');
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterDept  = $_GET['dept']  ?? '';
$viewMode    = $_GET['view']  ?? 'daily';  // daily | monthly

// Departments list
$depts = $db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// ---- Daily view -------------------------------------------
if ($viewMode === 'daily') {
    $sql = "
      SELECT a.*, e.full_name, e.emp_code, e.department, e.position
      FROM attendance a
      JOIN employees e ON e.id = a.employee_id
      WHERE a.work_date = :date
        AND (:dept = '' OR e.department = :dept2)
      ORDER BY e.department, e.full_name
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':date' => $filterDate, ':dept' => $filterDept, ':dept2' => $filterDept]);
    $records = $stmt->fetchAll();

    // Employees not yet checked in
    $stmtNA = $db->prepare("
      SELECT e.full_name, e.emp_code, e.department, e.position
      FROM employees e
      WHERE e.role='employee' AND e.status='active'
        AND (:dept='' OR e.department=:dept2)
        AND e.id NOT IN (SELECT employee_id FROM attendance WHERE work_date=:date)
      ORDER BY e.department, e.full_name
    ");
    $stmtNA->execute([':dept' => $filterDept, ':dept2' => $filterDept, ':date' => $filterDate]);
    $notRecorded = $stmtNA->fetchAll();
}

// ---- Monthly view ----------------------------------------
if ($viewMode === 'monthly') {
    $stmt = $db->prepare("
      SELECT e.full_name, e.emp_code, e.department,
        COUNT(a.id) AS total_days,
        SUM(CASE WHEN a.status='present'  THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN a.status='late'     THEN 1 ELSE 0 END) AS late,
        SUM(CASE WHEN a.status='absent'   THEN 1 ELSE 0 END) AS absent,
        SUM(CASE WHEN a.status='on_leave' THEN 1 ELSE 0 END) AS on_leave,
        ROUND(SUM(a.total_hours),1) AS total_hours
      FROM employees e
      LEFT JOIN attendance a ON a.employee_id = e.id
        AND DATE_FORMAT(a.work_date,'%Y-%m') = :month
      WHERE e.role='employee' AND e.status='active'
        AND (:dept='' OR e.department=:dept2)
      GROUP BY e.id
      ORDER BY e.department, e.full_name
    ");
    $stmt->execute([':month' => $filterMonth, ':dept' => $filterDept, ':dept2' => $filterDept]);
    $monthlyRecords = $stmt->fetchAll();
}

$pageTitle  = 'Attendance Overview';
$activePage = 'att_overview';
require_once __DIR__ . '/../includes/nav.php';
?>

<!-- Filter bar -->
<div class="form-card" style="margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group-plain" style="margin:0">
      <label>View Mode</label>
      <select name="view" onchange="this.form.submit()">
        <option value="daily"   <?= $viewMode==='daily'   ? 'selected' : '' ?>>Daily View</option>
        <option value="monthly" <?= $viewMode==='monthly' ? 'selected' : '' ?>>Monthly View</option>
      </select>
    </div>
    <?php if ($viewMode === 'daily'): ?>
    <div class="form-group-plain" style="margin:0">
      <label>Date</label>
      <input type="date" name="date" value="<?= e($filterDate) ?>" max="<?= date('Y-m-d') ?>"/>
    </div>
    <?php else: ?>
    <div class="form-group-plain" style="margin:0">
      <label>Month</label>
      <input type="month" name="month" value="<?= e($filterMonth) ?>"/>
    </div>
    <?php endif; ?>
    <div class="form-group-plain" style="margin:0">
      <label>Department</label>
      <select name="dept">
        <option value="">All Departments</option>
        <?php foreach ($depts as $d): ?>
          <option value="<?= e($d) ?>" <?= $filterDept===$d?'selected':'' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
    <a href="<?= APP_URL ?>/api/export.php?type=attendance_admin&view=<?= urlencode($viewMode) ?>&date=<?= urlencode($filterDate) ?>&month=<?= urlencode($filterMonth) ?>&dept=<?= urlencode($filterDept) ?>"
       class="btn btn-ghost">⬇ Export CSV</a>
  </form>
</div>

<?php if ($viewMode === 'daily'): ?>
<!-- ======== DAILY VIEW ======== -->
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">Attendance – <?= date('D, d F Y', strtotime($filterDate)) ?></div>
    <div style="display:flex;gap:.5rem">
      <span class="badge badge-present"><?= count($records) ?> Recorded</span>
      <span class="badge badge-absent"><?= count($notRecorded) ?> Not Recorded</span>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Employee</th><th>Department</th><th>Check In</th><th>Check Out</th>
        <th>Hours</th><th>Status</th><th>Notes</th>
      </tr></thead>
      <tbody>
      <?php if ($records): foreach ($records as $r): ?>
        <tr>
          <td>
            <div class="emp-cell">
              <div class="avatar-initials"><?= strtoupper(substr($r['full_name'],0,1)) ?></div>
              <div>
                <div class="text-sm font-bold"><?= e($r['full_name']) ?></div>
                <div class="text-xs text-dim"><?= e($r['emp_code']) ?></div>
              </div>
            </div>
          </td>
          <td class="text-dim text-sm"><?= e($r['department']) ?></td>
          <td><?= $r['check_in']  ? date('H:i', strtotime($r['check_in']))  : '–' ?></td>
          <td><?= $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '–' ?></td>
          <td><?= $r['total_hours'] > 0 ? formatHours($r['total_hours']) : '–' ?></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td class="text-xs text-dim"><?= e($r['notes']??'') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      <?php foreach ($notRecorded as $nr): ?>
        <tr style="opacity:.5">
          <td>
            <div class="emp-cell">
              <div class="avatar-initials" style="background:var(--bg3);color:var(--text-dim)"><?= strtoupper(substr($nr['full_name'],0,1)) ?></div>
              <div>
                <div class="text-sm font-bold"><?= e($nr['full_name']) ?></div>
                <div class="text-xs text-dim"><?= e($nr['emp_code']) ?></div>
              </div>
            </div>
          </td>
          <td class="text-dim text-sm"><?= e($nr['department']) ?></td>
          <td>–</td><td>–</td><td>–</td>
          <td><span class="badge badge-absent">Not Recorded</span></td>
          <td></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- ======== MONTHLY VIEW ======== -->
<?php
[$my, $mm] = explode('-', $filterMonth);
$monthLabel = date('F Y', mktime(0,0,0,(int)$mm,1,(int)$my));
?>
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">Monthly Summary – <?= $monthLabel ?></div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Employee</th><th>Department</th><th>Present</th><th>Late</th>
        <th>Absent</th><th>On Leave</th><th>Total Hours</th><th>Rate</th>
      </tr></thead>
      <tbody>
      <?php if ($monthlyRecords): foreach ($monthlyRecords as $r):
        $rate = ($r['total_days'] > 0) ? round((($r['present'] + $r['late']) / $r['total_days']) * 100) : 0;
      ?>
        <tr>
          <td>
            <div class="emp-cell">
              <div class="avatar-initials"><?= strtoupper(substr($r['full_name'],0,1)) ?></div>
              <div>
                <div class="text-sm font-bold"><?= e($r['full_name']) ?></div>
                <div class="text-xs text-dim"><?= e($r['emp_code']) ?></div>
              </div>
            </div>
          </td>
          <td class="text-dim text-sm"><?= e($r['department']) ?></td>
          <td class="text-green"><?= $r['present'] ?></td>
          <td class="text-amber"><?= $r['late'] ?></td>
          <td class="text-red"><?= $r['absent'] ?></td>
          <td class="text-dim"><?= $r['on_leave'] ?></td>
          <td class="text-accent"><?= $r['total_hours'] ?>h</td>
          <td>
            <div style="display:flex;align-items:center;gap:.5rem">
              <div style="flex:1;height:5px;background:var(--bg3);border-radius:3px;overflow:hidden;min-width:60px">
                <div style="height:100%;width:<?= $rate ?>%;background:<?= $rate>=80?'var(--green)':($rate>=60?'var(--amber)':'var(--red)') ?>;border-radius:3px"></div>
              </div>
              <span class="text-sm"><?= $rate ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="8"><div class="empty-state"><p>No records found.</p></div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
