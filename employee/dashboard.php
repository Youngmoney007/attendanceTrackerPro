<?php
// =============================================================
// employee/dashboard.php  –  Employee Home
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$cu = currentUser();
$db = getDB();

// ---- Today's attendance record ----------------------------
$today = date('Y-m-d');
$stmt  = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND work_date = ? LIMIT 1");
$stmt->execute([$cu['id'], $today]);
$todayAtt = $stmt->fetch();

// ---- Monthly summary for this employee -------------------
$month = date('Y-m');
$stmtM = $db->prepare("
  SELECT
    COUNT(*) AS total_days,
    SUM(CASE WHEN status='present'  THEN 1 ELSE 0 END) AS present,
    SUM(CASE WHEN status='late'     THEN 1 ELSE 0 END) AS late,
    SUM(CASE WHEN status='absent'   THEN 1 ELSE 0 END) AS absent,
    ROUND(SUM(total_hours),1)                          AS total_hours
  FROM attendance
  WHERE employee_id = ?
    AND DATE_FORMAT(work_date,'%Y-%m') = ?
");
$stmtM->execute([$cu['id'], $month]);
$monthly = $stmtM->fetch();

// ---- Pending leave requests --------------------------------
$stmtL = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status='pending'");
$stmtL->execute([$cu['id']]);
$pendingLeaves = (int) $stmtL->fetchColumn();

// ---- Last 5 attendance records ----------------------------
$stmtR = $db->prepare("
  SELECT * FROM attendance WHERE employee_id = ?
  ORDER BY work_date DESC LIMIT 5
");
$stmtR->execute([$cu['id']]);
$recentAtt = $stmtR->fetchAll();

// ---- Latest KPI scores ------------------------------------
$stmtK = $db->prepare("
  SELECT k.kpi_name, ps.score, k.max_score, k.category
  FROM performance_scores ps
  JOIN performance_kpis k ON k.id = ps.kpi_id
  WHERE ps.employee_id = ?
    AND ps.period_month = MONTH(CURDATE())
    AND ps.period_year  = YEAR(CURDATE())
  ORDER BY k.category
");
$stmtK->execute([$cu['id']]);
$kpis = $stmtK->fetchAll();

// ---- Weekly attendance for chart (last 7 days) -----------
$stmtW = $db->prepare("
  SELECT work_date, total_hours, status
  FROM attendance
  WHERE employee_id = ?
    AND work_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  ORDER BY work_date ASC
");
$stmtW->execute([$cu['id']]);
$weekData = $stmtW->fetchAll();

$pageTitle  = 'My Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/nav.php';
?>

<!-- ====== Check-in Panel ====== -->
<div class="checkin-panel">
  <div class="checkin-info">
    <div class="checkin-time" id="clock-display">--:--:--</div>
    <div class="checkin-date"><?= date('l, d F Y') ?></div>
    <?php if ($todayAtt): ?>
      <div class="mt-1">
        <?php if ($todayAtt['check_in']): ?>
          <span class="text-sm text-dim">In: <strong class="text-accent"><?= date('H:i', strtotime($todayAtt['check_in'])) ?></strong></span>
        <?php endif; ?>
        <?php if ($todayAtt['check_out']): ?>
          &nbsp;&nbsp;<span class="text-sm text-dim">Out: <strong class="text-accent"><?= date('H:i', strtotime($todayAtt['check_out'])) ?></strong></span>
          &nbsp;&nbsp;<span class="text-sm text-dim">Hours: <strong class="text-green"><?= formatHours($todayAtt['total_hours']) ?></strong></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="checkin-actions">
    <form method="POST" action="<?= APP_URL ?>/api/attendance.php">
      <input type="hidden" name="action" value="checkin"/>
      <button type="submit" class="btn-checkin"
        <?= ($todayAtt && $todayAtt['check_in']) ? 'disabled' : '' ?>>
        ▶ Check In
      </button>
    </form>
    <form method="POST" action="<?= APP_URL ?>/api/attendance.php">
      <input type="hidden" name="action" value="checkout"/>
      <button type="submit" class="btn-checkout"
        <?= (!$todayAtt || !$todayAtt['check_in'] || $todayAtt['check_out']) ? 'disabled' : '' ?>>
        ■ Check Out
      </button>
    </form>
    <!-- QR quick-access shortcut -->
    <a href="<?= APP_URL ?>/employee/my_qr.php"
       style="display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.2rem;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius);color:var(--text);font-weight:600;font-size:.88rem;text-decoration:none;transition:border-color .2s"
       onmouseover="this.style.borderColor='var(--accent)'"
       onmouseout="this.style.borderColor='var(--border)'">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8H3m2 8H2m10-2V8"/></svg>
      My QR Code
    </a>
  </div>
</div>

<!-- ====== Stats ====== -->
<div class="stats-grid">
  <div class="stat-card" style="--card-accent:var(--green)">
    <div class="stat-icon-wrap"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
    <div class="stat-label-sm">Present (Month)</div>
    <div class="stat-value"><?= $monthly['present'] ?? 0 ?></div>
    <div class="stat-sub">out of <?= $monthly['total_days'] ?? 0 ?> recorded days</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--amber)">
    <div class="stat-icon-wrap"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
    <div class="stat-label-sm">Late Arrivals</div>
    <div class="stat-value"><?= $monthly['late'] ?? 0 ?></div>
    <div class="stat-sub">this month</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--accent)">
    <div class="stat-icon-wrap"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
    <div class="stat-label-sm">Total Hours</div>
    <div class="stat-value"><?= $monthly['total_hours'] ?? 0 ?><span style="font-size:1rem">h</span></div>
    <div class="stat-sub">this month</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--blue)">
    <div class="stat-icon-wrap"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div>
    <div class="stat-label-sm">Pending Leave</div>
    <div class="stat-value"><?= $pendingLeaves ?></div>
    <div class="stat-sub"><a href="<?= APP_URL ?>/employee/leave.php">view requests →</a></div>
  </div>
</div>

<!-- ====== Charts row ====== -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.75rem" class="charts-row">
  <div class="chart-card">
    <div class="chart-title">Weekly Hours</div>
    <div class="chart-container"><canvas id="weekHoursChart"></canvas></div>
  </div>
  <div class="chart-card">
    <div class="chart-title">KPI Scores – <?= date('F Y') ?></div>
    <div class="chart-container"><canvas id="kpiRadarChart"></canvas></div>
  </div>
</div>

<!-- ====== KPI Cards ====== -->
<?php if ($kpis): ?>
<div class="kpi-grid">
  <?php foreach ($kpis as $k): ?>
  <div class="kpi-card">
    <div class="kpi-header">
      <div>
        <div class="kpi-name"><?= e($k['kpi_name']) ?></div>
        <div class="text-xs text-dim"><?= e($k['category']) ?></div>
      </div>
      <div class="kpi-score"><?= $k['score'] ?></div>
    </div>
    <div class="kpi-bar">
      <div class="kpi-fill" style="width:<?= ($k['score'] / $k['max_score']) * 100 ?>%"></div>
    </div>
    <div class="kpi-meta">Score: <?= $k['score'] ?> / <?= $k['max_score'] ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ====== Recent Attendance ====== -->
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">Recent Attendance</div>
    <a href="<?= APP_URL ?>/employee/attendance.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th>
      </tr></thead>
      <tbody>
      <?php if ($recentAtt): foreach ($recentAtt as $r): ?>
        <tr>
          <td><?= date('D, d M Y', strtotime($r['work_date'])) ?></td>
          <td><?= $r['check_in'] ? date('H:i', strtotime($r['check_in'])) : '–' ?></td>
          <td><?= $r['check_out'] ? date('H:i', strtotime($r['check_out'])) : '–' ?></td>
          <td><?= $r['total_hours'] > 0 ? formatHours($r['total_hours']) : '–' ?></td>
          <td><?= statusBadge($r['status']) ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="5"><div class="empty-state"><p>No attendance records yet.</p></div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Chart JS -->
<script>
// Weekly hours bar chart
(function() {
  const rawWeek = <?= json_encode($weekData) ?>;
  const labels = [], data = [];
  // fill last 7 days
  for (let i = 6; i >= 0; i--) {
    const d = new Date(); d.setDate(d.getDate() - i);
    const ymd = d.toISOString().split('T')[0];
    const found = rawWeek.find(r => r.work_date === ymd);
    labels.push(d.toLocaleDateString('en-GB',{weekday:'short'}));
    data.push(found ? parseFloat(found.total_hours) : 0);
  }
  new Chart(document.getElementById('weekHoursChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Hours Worked',
        data,
        backgroundColor: data.map(v => v >= 8 ? 'rgba(240,255,75,.7)' : v > 0 ? 'rgba(240,255,75,.35)' : 'rgba(255,255,255,.05)'),
        borderColor: 'transparent',
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, max: 12, grid: { color: '#2e2e2e' } },
        x: { grid: { display: false } }
      }
    }
  });
})();

// KPI Radar chart
(function() {
  const kpis = <?= json_encode($kpis) ?>;
  if (!kpis.length) return;
  new Chart(document.getElementById('kpiRadarChart'), {
    type: 'radar',
    data: {
      labels: kpis.map(k => k.kpi_name),
      datasets: [{
        label: 'My Score',
        data: kpis.map(k => k.score),
        backgroundColor: 'rgba(240,255,75,.15)',
        borderColor: '#f0ff4b',
        pointBackgroundColor: '#f0ff4b',
        borderWidth: 2,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { r: { beginAtZero: true, max: 100, grid: { color: '#2e2e2e' }, ticks: { stepSize: 25, color: '#555' } } }
    }
  });
})();

// Live clock
(function() {
  const el = document.getElementById('clock-display');
  const update = () => { el.textContent = new Date().toLocaleTimeString('en-GB'); };
  update(); setInterval(update, 1000);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
