<?php
// =============================================================
// employee/performance.php  –  Employee KPI Performance View
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$cu = currentUser();
$db = getDB();

$filterMonth = $_GET['month'] ?? date('Y-m');
[$filterYear, $filterMonthNum] = explode('-', $filterMonth);

// Fetch KPI scores for selected period
$stmt = $db->prepare("
  SELECT k.kpi_name, k.description, k.max_score, k.category,
         ps.score, ps.comments, ps.created_at
  FROM performance_kpis k
  LEFT JOIN performance_scores ps
    ON ps.kpi_id = ps.kpi_id
    AND ps.employee_id = ?
    AND ps.period_month = ?
    AND ps.period_year  = ?
    AND ps.kpi_id = k.id
  WHERE k.is_active = 1
  ORDER BY k.category, k.kpi_name
");
$stmt->execute([$cu['id'], (int)$filterMonthNum, (int)$filterYear]);
$kpis = $stmt->fetchAll();

// All months with scores (for trend chart)
$stmtT = $db->prepare("
  SELECT ps.period_year, ps.period_month, ROUND(AVG(ps.score),1) AS avg_score
  FROM performance_scores ps
  WHERE ps.employee_id = ?
  GROUP BY ps.period_year, ps.period_month
  ORDER BY ps.period_year, ps.period_month
  LIMIT 12
");
$stmtT->execute([$cu['id']]);
$trendData = $stmtT->fetchAll();

$pageTitle  = 'My Performance';
$activePage = 'performance';
require_once __DIR__ . '/../includes/nav.php';
?>

<!-- Filter -->
<div class="form-card" style="margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:1rem;align-items:flex-end">
    <div class="form-group-plain" style="margin:0">
      <label>Period</label>
      <input type="month" name="month" value="<?= e($filterMonth) ?>"/>
    </div>
    <button type="submit" class="btn btn-primary">View</button>
  </form>
</div>

<!-- Trend chart -->
<?php if ($trendData): ?>
<div class="chart-card" style="margin-bottom:1.75rem">
  <div class="chart-title">Average KPI Trend</div>
  <div class="chart-container"><canvas id="trendChart"></canvas></div>
</div>
<?php endif; ?>

<!-- KPI cards -->
<div class="kpi-grid">
<?php if ($kpis): foreach ($kpis as $k):
  $score = $k['score'] ?? null;
  $pct   = $score !== null ? round(($score / $k['max_score']) * 100) : 0;
?>
  <div class="kpi-card">
    <div class="kpi-header">
      <div>
        <div class="kpi-name"><?= e($k['kpi_name']) ?></div>
        <div class="text-xs text-dim"><?= e($k['category']) ?></div>
      </div>
      <div class="kpi-score" style="<?= $score === null ? 'color:var(--text-dim);font-size:1rem' : '' ?>">
        <?= $score !== null ? $score : 'N/A' ?>
      </div>
    </div>
    <?php if ($k['description']): ?>
      <div class="text-xs text-dim mb-2"><?= e($k['description']) ?></div>
    <?php endif; ?>
    <div class="kpi-bar">
      <div class="kpi-fill" style="width:<?= $pct ?>%"></div>
    </div>
    <div class="kpi-meta">
      <?php if ($score !== null): ?>
        <?= $score ?>/<?= $k['max_score'] ?> (<?= $pct ?>%)
        <?php if ($k['comments']): ?> – <em><?= e($k['comments']) ?></em><?php endif; ?>
      <?php else: ?>
        Not rated yet
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; else: ?>
  <p class="text-dim">No KPIs configured.</p>
<?php endif; ?>
</div>

<script>
(function() {
  const trend = <?= json_encode($trendData) ?>;
  if (!trend.length) return;
  const labels = trend.map(r => {
    const d = new Date(r.period_year, r.period_month - 1);
    return d.toLocaleDateString('en-GB', {month:'short', year:'2-digit'});
  });
  new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Avg KPI Score',
        data: trend.map(r => r.avg_score),
        borderColor: '#f0ff4b',
        backgroundColor: 'rgba(240,255,75,.1)',
        tension: .4,
        fill: true,
        pointBackgroundColor: '#f0ff4b',
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: false, min: 0, max: 100, grid: { color: '#2e2e2e' } },
        x: { grid: { display: false } }
      }
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
