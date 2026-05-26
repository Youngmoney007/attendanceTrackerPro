<?php
// =============================================================
// admin/dashboard.php  –  Admin Home / Overview
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();
$db = getDB();

// ---- Global stats ----------------------------------------
$today = date('Y-m-d');
$month = date('Y-m');

// Total employees
$totalEmp = (int) $db->query("SELECT COUNT(*) FROM employees WHERE role='employee' AND status='active'")->fetchColumn();

// Present today
$presentToday = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE work_date='$today' AND status IN ('present','late')")->fetchColumn();

// Pending leave
$pendingLeave = (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();

// Month attendance rate
$monthStats = $db->query("
  SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS present
  FROM attendance
  WHERE DATE_FORMAT(work_date,'%Y-%m') = '$month'
")->fetch();
$attRate = $monthStats['total'] > 0
    ? round(($monthStats['present'] / $monthStats['total']) * 100, 1)
    : 0;

// Today's attendance list
$stmtToday = $db->prepare("
  SELECT a.*, e.full_name, e.department, e.emp_code
  FROM attendance a
  JOIN employees e ON e.id = a.employee_id
  WHERE a.work_date = ?
  ORDER BY a.check_in DESC
");
$stmtToday->execute([$today]);
$todayList = $stmtToday->fetchAll();

// Pending leave requests (5 most recent)
$stmtPL = $db->prepare("
  SELECT lr.*, e.full_name, e.department, e.emp_code
  FROM leave_requests lr
  JOIN employees e ON e.id = lr.employee_id
  WHERE lr.status = 'pending'
  ORDER BY lr.created_at DESC
  LIMIT 5
");
$stmtPL->execute();
$pendingLeaveList = $stmtPL->fetchAll();

// Department attendance chart (current month)
$deptAtt = $db->query("
  SELECT e.department,
    COUNT(DISTINCT a.employee_id) AS employees,
    ROUND(AVG(a.total_hours),1)   AS avg_hours,
    SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS present,
    COUNT(a.id) AS total_records
  FROM attendance a
  JOIN employees e ON e.id = a.employee_id
  WHERE DATE_FORMAT(a.work_date,'%Y-%m') = '$month'
  GROUP BY e.department
")->fetchAll();

// Top performers (this month avg score)
$topPerf = $db->query("
  SELECT e.full_name, e.emp_code, e.department,
         ROUND(AVG(ps.score),1) AS avg_score
  FROM performance_scores ps
  JOIN employees e ON e.id = ps.employee_id
  WHERE ps.period_month = MONTH(CURDATE()) AND ps.period_year = YEAR(CURDATE())
  GROUP BY e.id
  ORDER BY avg_score DESC
  LIMIT 5
")->fetchAll();

$pageTitle  = 'Admin Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/nav.php';
?>

<!-- ====== Top Stats ====== -->
<div class="stats-grid">
  <div class="stat-card" style="--card-accent:var(--accent)">
    <div class="stat-icon-wrap"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
    <div class="stat-label-sm">Total Employees</div>
    <div class="stat-value"><?= $totalEmp ?></div>
    <div class="stat-sub">active workforce</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--green)">
    <div class="stat-icon-wrap"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
    <div class="stat-label-sm">Present Today</div>
    <div class="stat-value"><?= $presentToday ?></div>
    <div class="stat-sub">out of <?= $totalEmp ?> employees</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--blue)">
    <div class="stat-icon-wrap"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
    <div class="stat-label-sm">Attendance Rate</div>
    <div class="stat-value"><?= $attRate ?><span style="font-size:1.1rem">%</span></div>
    <div class="stat-sub">this month</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--amber)">
    <div class="stat-icon-wrap"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
    <div class="stat-label-sm">Pending Leaves</div>
    <div class="stat-value"><?= $pendingLeave ?></div>
    <div class="stat-sub"><a href="<?= APP_URL ?>/admin/leave_management.php">review now →</a></div>
  </div>
  <div class="stat-card" style="--card-accent:var(--purple)">
    <div class="stat-icon-wrap"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8H3m2 8H2m10-2V8"/></svg></div>
    <div class="stat-label-sm">QR Scans Today</div>
    <div class="stat-value"><?= (int)$db->query("SELECT COUNT(*) FROM qr_scans WHERE DATE(scanned_at)=CURDATE() AND scan_result='success'")->fetchColumn() ?></div>
    <div class="stat-sub"><a href="<?= APP_URL ?>/admin/office_qr.php">manage QR →</a></div>
  </div>
</div>

<!-- Charts row -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.75rem">
  <div class="chart-card">
    <div class="chart-title">Department Attendance Rate (<?= date('F Y') ?>)</div>
    <div class="chart-container"><canvas id="deptChart"></canvas></div>
  </div>
  <div class="chart-card">
    <div class="chart-title">Today's Status</div>
    <div class="chart-container" style="display:flex;align-items:center;justify-content:center">
      <canvas id="todayDoughnut"></canvas>
    </div>
  </div>
</div>

<!-- Today's attendance and pending leaves side by side -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.75rem">

  <!-- Today's check-ins -->
  <div class="table-card" style="margin:0">
    <div class="table-card-header">
      <div class="table-card-title">Today's Check-ins</div>
      <a href="<?= APP_URL ?>/admin/attendance_overview.php" class="btn btn-ghost btn-sm">Full View</a>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Employee</th><th>In</th><th>Out</th><th>Status</th></tr></thead>
        <tbody>
        <?php if ($todayList): foreach ($todayList as $r): ?>
          <tr>
            <td>
              <div class="emp-cell">
                <div class="avatar-initials"><?= strtoupper(substr($r['full_name'],0,1)) ?></div>
                <div>
                  <div class="text-sm font-bold"><?= e($r['full_name']) ?></div>
                  <div class="text-xs text-dim"><?= e($r['department']) ?></div>
                </div>
              </div>
            </td>
            <td><?= $r['check_in'] ? date('H:i', strtotime($r['check_in'])) : '–' ?></td>
            <td><?= $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '–' ?></td>
            <td><?= statusBadge($r['status']) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4"><div class="empty-state" style="padding:1.5rem"><p>No check-ins yet today.</p></div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pending leave requests -->
  <div class="table-card" style="margin:0">
    <div class="table-card-header">
      <div class="table-card-title">Pending Leave Requests</div>
      <a href="<?= APP_URL ?>/admin/leave_management.php" class="btn btn-ghost btn-sm">Manage</a>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th></tr></thead>
        <tbody>
        <?php if ($pendingLeaveList): foreach ($pendingLeaveList as $l): ?>
          <tr>
            <td>
              <div class="emp-cell">
                <div class="avatar-initials"><?= strtoupper(substr($l['full_name'],0,1)) ?></div>
                <div class="text-sm"><?= e($l['full_name']) ?></div>
              </div>
            </td>
            <td><span class="badge badge-leave"><?= ucfirst(e($l['leave_type'])) ?></span></td>
            <td class="text-sm"><?= date('d M', strtotime($l['start_date'])) ?> – <?= date('d M', strtotime($l['end_date'])) ?></td>
            <td><?= $l['total_days'] ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4"><div class="empty-state" style="padding:1.5rem"><p>No pending requests.</p></div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Top performers -->
<?php if ($topPerf): ?>
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">Top Performers – <?= date('F Y') ?></div>
    <a href="<?= APP_URL ?>/admin/performance_admin.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>#</th><th>Employee</th><th>Department</th><th>Avg KPI Score</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($topPerf as $i => $p): ?>
        <tr>
          <td><span class="text-dim"><?= $i + 1 ?></span></td>
          <td>
            <div class="emp-cell">
              <div class="avatar-initials"><?= strtoupper(substr($p['full_name'],0,1)) ?></div>
              <div>
                <div class="text-sm font-bold"><?= e($p['full_name']) ?></div>
                <div class="text-xs text-dim"><?= e($p['emp_code']) ?></div>
              </div>
            </div>
          </td>
          <td class="text-dim text-sm"><?= e($p['department']) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:.75rem">
              <div style="flex:1;height:6px;background:var(--bg3);border-radius:3px;overflow:hidden;max-width:120px">
                <div style="height:100%;width:<?= $p['avg_score'] ?>%;background:var(--accent);border-radius:3px"></div>
              </div>
              <span class="text-accent font-bold"><?= $p['avg_score'] ?></span>
            </div>
          </td>
          <td></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
// Department bar chart
(function() {
  const dept = <?= json_encode($deptAtt) ?>;
  if (!dept.length) return;
  const rate = dept.map(d => d.total_records > 0 ? Math.round((d.present / d.total_records) * 100) : 0);
  new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
      labels: dept.map(d => d.department || 'N/A'),
      datasets: [{
        label: 'Attendance Rate %',
        data: rate,
        backgroundColor: rate.map(r => r >= 80 ? 'rgba(240,255,75,.7)' : r >= 60 ? 'rgba(245,158,11,.6)' : 'rgba(239,68,68,.5)'),
        borderRadius: 6,
        borderColor: 'transparent',
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, max: 100, grid: { color: '#2e2e2e' }, ticks: { callback: v => v + '%' } },
        x: { grid: { display: false } }
      }
    }
  });
})();

// Today doughnut
(function() {
  const present  = <?= $presentToday ?>;
  const total    = <?= $totalEmp ?>;
  const absent   = Math.max(0, total - present);
  new Chart(document.getElementById('todayDoughnut'), {
    type: 'doughnut',
    data: {
      labels: ['Present', 'Absent/Not recorded'],
      datasets: [{
        data: [present, absent],
        backgroundColor: ['rgba(240,255,75,.8)', 'rgba(255,255,255,.07)'],
        borderWidth: 0,
        hoverBorderWidth: 2,
        hoverBorderColor: ['#f0ff4b', '#555'],
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '72%',
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.raw}` } }
      }
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
