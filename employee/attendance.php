<?php
// =============================================================
// employee/attendance.php  –  Employee Attendance History
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$cu = currentUser();
$db = getDB();

// ---- Filters ----------------------------------------------
$filterMonth = $_GET['month'] ?? date('Y-m');
[$filterYear, $filterMonthNum] = explode('-', $filterMonth);

// ---- Fetch records ----------------------------------------
$stmt = $db->prepare("
  SELECT * FROM attendance
  WHERE employee_id = ?
    AND DATE_FORMAT(work_date, '%Y-%m') = ?
  ORDER BY work_date DESC
");
$stmt->execute([$cu['id'], $filterMonth]);
$records = $stmt->fetchAll();

// ---- Monthly summary --------------------------------------
$stmtS = $db->prepare("
  SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status='present'  THEN 1 ELSE 0 END) AS present,
    SUM(CASE WHEN status='late'     THEN 1 ELSE 0 END) AS late,
    SUM(CASE WHEN status='absent'   THEN 1 ELSE 0 END) AS absent,
    SUM(CASE WHEN status='half_day' THEN 1 ELSE 0 END) AS half_day,
    ROUND(SUM(total_hours),2) AS total_hours,
    ROUND(AVG(total_hours),2) AS avg_hours
  FROM attendance WHERE employee_id = ? AND DATE_FORMAT(work_date,'%Y-%m')=?
");
$stmtS->execute([$cu['id'], $filterMonth]);
$summary = $stmtS->fetch();

$pageTitle  = 'My Attendance';
$activePage = 'attendance';
require_once __DIR__ . '/../includes/nav.php';
?>

<!-- Filter -->
<div class="form-card" style="margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group-plain" style="margin:0">
      <label>Month</label>
      <input type="month" name="month" value="<?= e($filterMonth) ?>" max="<?= date('Y-m') ?>"/>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
  </form>
</div>

<!-- Summary stats -->
<div class="stats-grid" style="--cols:5;grid-template-columns:repeat(auto-fill,minmax(160px,1fr))">
  <div class="stat-card" style="--card-accent:var(--green)">
    <div class="stat-label-sm">Present</div>
    <div class="stat-value"><?= $summary['present'] ?? 0 ?></div>
    <div class="stat-sub">days</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--amber)">
    <div class="stat-label-sm">Late</div>
    <div class="stat-value"><?= $summary['late'] ?? 0 ?></div>
    <div class="stat-sub">days</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--red)">
    <div class="stat-label-sm">Absent</div>
    <div class="stat-value"><?= $summary['absent'] ?? 0 ?></div>
    <div class="stat-sub">days</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--accent)">
    <div class="stat-label-sm">Total Hours</div>
    <div class="stat-value"><?= $summary['total_hours'] ?? 0 ?><span style="font-size:1rem">h</span></div>
    <div class="stat-sub">this month</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--blue)">
    <div class="stat-label-sm">Daily Avg</div>
    <div class="stat-value"><?= $summary['avg_hours'] ?? 0 ?><span style="font-size:1rem">h</span></div>
    <div class="stat-sub">per day</div>
  </div>
</div>

<!-- Attendance table -->
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">Attendance – <?= date('F Y', mktime(0,0,0,$filterMonthNum,1,$filterYear)) ?></div>
    <a href="<?= APP_URL ?>/api/export.php?type=attendance_personal&month=<?= urlencode($filterMonth) ?>"
       class="btn btn-ghost btn-sm">⬇ Export CSV</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Date</th><th>Day</th><th>Check In</th><th>Check Out</th>
        <th>Hours</th><th>Status</th><th>Notes</th>
      </tr></thead>
      <tbody>
      <?php if ($records): foreach ($records as $r): ?>
        <tr>
          <td><?= date('d M Y', strtotime($r['work_date'])) ?></td>
          <td class="text-dim"><?= date('D', strtotime($r['work_date'])) ?></td>
          <td><?= $r['check_in']  ? date('H:i', strtotime($r['check_in']))  : '–' ?></td>
          <td><?= $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '–' ?></td>
          <td><?= $r['total_hours'] > 0 ? formatHours($r['total_hours']) : '–' ?></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td class="text-sm text-dim"><?= e($r['notes'] ?? '') ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p>No attendance records for this month.</p>
          </div>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
