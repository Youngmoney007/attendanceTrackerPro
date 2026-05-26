<?php
// =============================================================
// admin/reports.php  –  Generate & Export Reports
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();
$db = getDB();

$filterMonth = $_GET['month'] ?? date('Y-m');
[$fy, $fm]   = explode('-', $filterMonth);
$monthLabel  = date('F Y', mktime(0,0,0,(int)$fm,1,(int)$fy));

// ---- Summary stats for report preview --------------------
$attSummary = $db->prepare("
  SELECT
    COUNT(DISTINCT a.employee_id) AS employees_recorded,
    COUNT(*) AS total_records,
    SUM(CASE WHEN a.status='present'  THEN 1 ELSE 0 END) AS present,
    SUM(CASE WHEN a.status='late'     THEN 1 ELSE 0 END) AS late,
    SUM(CASE WHEN a.status='absent'   THEN 1 ELSE 0 END) AS absent,
    SUM(CASE WHEN a.status='on_leave' THEN 1 ELSE 0 END) AS on_leave,
    ROUND(AVG(a.total_hours),2) AS avg_hours,
    ROUND(SUM(a.total_hours),1) AS total_hours
  FROM attendance a
  WHERE DATE_FORMAT(a.work_date,'%Y-%m')=?
");
$attSummary->execute([$filterMonth]);
$att = $attSummary->fetch();

$leaveCount = $db->prepare("
  SELECT
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status='pending'  THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected
  FROM leave_requests
  WHERE DATE_FORMAT(start_date,'%Y-%m')=?
");
$leaveCount->execute([$filterMonth]);
$leaveStat = $leaveCount->fetch();

// Dept breakdown
$deptBreak = $db->prepare("
  SELECT e.department,
    COUNT(*) AS records,
    ROUND(SUM(a.total_hours),1) AS hours,
    ROUND(AVG(CASE WHEN a.status IN ('present','late') THEN 1.0 ELSE 0 END)*100,1) AS att_rate
  FROM attendance a
  JOIN employees e ON e.id=a.employee_id
  WHERE DATE_FORMAT(a.work_date,'%Y-%m')=?
  GROUP BY e.department ORDER BY att_rate DESC
");
$deptBreak->execute([$filterMonth]);
$deptData = $deptBreak->fetchAll();

// Top performers
$topPerf = $db->prepare("
  SELECT e.full_name, e.emp_code, e.department,
    ROUND(AVG(ps.score),1) AS avg_score
  FROM performance_scores ps
  JOIN employees e ON e.id=ps.employee_id
  WHERE ps.period_month=? AND ps.period_year=?
  GROUP BY e.id ORDER BY avg_score DESC LIMIT 10
");
$topPerf->execute([(int)$fm, (int)$fy]);
$perfData = $topPerf->fetchAll();

$pageTitle  = 'Reports';
$activePage = 'reports';
require_once __DIR__ . '/../includes/nav.php';
?>

<!-- Report header / controls -->
<div class="form-card" style="margin-bottom:1.25rem">
  <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <form method="GET" style="display:flex;gap:1rem;align-items:flex-end">
      <div class="form-group-plain" style="margin:0">
        <label>Report Period</label>
        <input type="month" name="month" value="<?= e($filterMonth) ?>"/>
      </div>
      <button type="submit" class="btn btn-primary">Generate</button>
    </form>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <a href="<?= APP_URL ?>/api/export.php?type=attendance_admin&view=monthly&month=<?= urlencode($filterMonth) ?>"
         class="btn btn-ghost">⬇ Attendance CSV</a>
      <a href="<?= APP_URL ?>/api/export.php?type=performance&month=<?= urlencode($filterMonth) ?>"
         class="btn btn-ghost">⬇ Performance CSV</a>
      <a href="<?= APP_URL ?>/api/export.php?type=leave&month=<?= urlencode($filterMonth) ?>"
         class="btn btn-ghost">⬇ Leave CSV</a>
      <button onclick="window.print()" class="btn btn-ghost">🖨 Print</button>
    </div>
  </div>
</div>

<!-- Report title -->
<div style="text-align:center;padding:2rem 0 1rem">
  <h1 style="font-family:var(--ff-display);font-size:1.8rem;font-weight:800">Monthly Report – <?= $monthLabel ?></h1>
  <p class="text-dim">Generated on <?= date('d F Y, H:i') ?> by <?= e($_SESSION['full_name'] ?? 'Admin') ?></p>
</div>

<!-- Attendance summary cards -->
<div class="stats-grid" style="margin-bottom:1.75rem">
  <div class="stat-card" style="--card-accent:var(--green)">
    <div class="stat-label-sm">Present Days</div>
    <div class="stat-value"><?= $att['present'] ?? 0 ?></div>
    <div class="stat-sub">records</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--amber)">
    <div class="stat-label-sm">Late Days</div>
    <div class="stat-value"><?= $att['late'] ?? 0 ?></div>
    <div class="stat-sub">records</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--red)">
    <div class="stat-label-sm">Absent Days</div>
    <div class="stat-value"><?= $att['absent'] ?? 0 ?></div>
    <div class="stat-sub">records</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--blue)">
    <div class="stat-label-sm">On Leave</div>
    <div class="stat-value"><?= $att['on_leave'] ?? 0 ?></div>
    <div class="stat-sub">records</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--accent)">
    <div class="stat-label-sm">Total Hours</div>
    <div class="stat-value"><?= $att['total_hours'] ?? 0 ?><span style="font-size:.9rem">h</span></div>
    <div class="stat-sub">workforce</div>
  </div>
  <div class="stat-card" style="--card-accent:var(--purple)">
    <div class="stat-label-sm">Avg Daily Hours</div>
    <div class="stat-value"><?= $att['avg_hours'] ?? 0 ?><span style="font-size:.9rem">h</span></div>
    <div class="stat-sub">per employee</div>
  </div>
</div>

<!-- Charts -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.75rem">
  <div class="chart-card">
    <div class="chart-title">Attendance Breakdown</div>
    <div class="chart-container" style="display:flex;align-items:center;justify-content:center">
      <canvas id="attPieChart"></canvas>
    </div>
  </div>
  <div class="chart-card">
    <div class="chart-title">Department Attendance Rate</div>
    <div class="chart-container"><canvas id="deptReportChart"></canvas></div>
  </div>
</div>

<!-- Department table -->
<div class="table-card" style="margin-bottom:1.75rem">
  <div class="table-card-header"><div class="table-card-title">Department Summary</div></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Department</th><th>Records</th><th>Total Hours</th><th>Att. Rate</th></tr></thead>
      <tbody>
      <?php foreach ($deptData as $d): ?>
        <tr>
          <td><?= e($d['department'] ?? 'N/A') ?></td>
          <td><?= $d['records'] ?></td>
          <td><?= $d['hours'] ?>h</td>
          <td>
            <div style="display:flex;align-items:center;gap:.5rem">
              <div style="flex:1;height:5px;background:var(--bg3);border-radius:3px;overflow:hidden;min-width:80px">
                <div style="height:100%;width:<?= $d['att_rate'] ?>%;background:<?= $d['att_rate']>=80?'var(--green)':($d['att_rate']>=60?'var(--amber)':'var(--red)') ?>;border-radius:3px"></div>
              </div>
              <span><?= $d['att_rate'] ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Leave summary -->
<div class="table-card" style="margin-bottom:1.75rem">
  <div class="table-card-header"><div class="table-card-title">Leave Summary</div></div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;padding:1.25rem">
    <div style="text-align:center">
      <div style="font-family:var(--ff-display);font-size:2rem;font-weight:700;color:var(--green)"><?= $leaveStat['approved'] ?? 0 ?></div>
      <div class="text-dim text-sm">Approved</div>
    </div>
    <div style="text-align:center">
      <div style="font-family:var(--ff-display);font-size:2rem;font-weight:700;color:var(--amber)"><?= $leaveStat['pending'] ?? 0 ?></div>
      <div class="text-dim text-sm">Pending</div>
    </div>
    <div style="text-align:center">
      <div style="font-family:var(--ff-display);font-size:2rem;font-weight:700;color:var(--red)"><?= $leaveStat['rejected'] ?? 0 ?></div>
      <div class="text-dim text-sm">Rejected</div>
    </div>
  </div>
</div>

<!-- Top performers -->
<?php if ($perfData): ?>
<div class="table-card">
  <div class="table-card-header"><div class="table-card-title">Performance Ranking – <?= $monthLabel ?></div></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Rank</th><th>Employee</th><th>Department</th><th>Avg KPI Score</th></tr></thead>
      <tbody>
      <?php foreach ($perfData as $i => $p): ?>
        <tr>
          <td>
            <?php if ($i === 0): ?><span style="color:var(--accent);font-size:1.2rem">🥇</span>
            <?php elseif ($i === 1): ?><span style="font-size:1.2rem">🥈</span>
            <?php elseif ($i === 2): ?><span style="font-size:1.2rem">🥉</span>
            <?php else: ?><?= $i + 1 ?><?php endif; ?>
          </td>
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
              <div style="flex:1;height:6px;background:var(--bg3);border-radius:3px;overflow:hidden;max-width:150px">
                <div style="height:100%;width:<?= $p['avg_score'] ?>%;background:var(--accent);border-radius:3px"></div>
              </div>
              <span class="text-accent font-bold"><?= $p['avg_score'] ?>/100</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
// Attendance pie
new Chart(document.getElementById('attPieChart'), {
  type: 'doughnut',
  data: {
    labels: ['Present', 'Late', 'Absent', 'On Leave'],
    datasets: [{
      data: [<?= $att['present']??0 ?>, <?= $att['late']??0 ?>, <?= $att['absent']??0 ?>, <?= $att['on_leave']??0 ?>],
      backgroundColor: ['rgba(34,197,94,.75)','rgba(245,158,11,.75)','rgba(239,68,68,.75)','rgba(59,130,246,.75)'],
      borderWidth: 0,
    }]
  },
  options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{ position:'right' } } }
});

// Dept bar
const deptData = <?= json_encode($deptData) ?>;
new Chart(document.getElementById('deptReportChart'), {
  type: 'bar',
  data: {
    labels: deptData.map(d => d.department || 'N/A'),
    datasets: [{
      label: 'Att. Rate %',
      data: deptData.map(d => d.att_rate),
      backgroundColor: deptData.map(d => d.att_rate >= 80 ? 'rgba(240,255,75,.7)' : d.att_rate >= 60 ? 'rgba(245,158,11,.5)' : 'rgba(239,68,68,.5)'),
      borderRadius: 6, borderColor: 'transparent',
    }]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ display:false } },
    scales:{ y:{ max:100, grid:{color:'#2e2e2e'}, ticks:{callback:v=>v+'%'} }, x:{grid:{display:false}} }
  }
});
</script>

<style>
@media print {
  .sidebar, .topbar, .form-card, .btn, form { display: none !important; }
  .main-wrapper { margin: 0 !important; }
  .main-content { padding: 0 !important; }
  body { background: white !important; color: black !important; }
  .stat-card, .table-card, .chart-card { border: 1px solid #ccc !important; background: white !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
