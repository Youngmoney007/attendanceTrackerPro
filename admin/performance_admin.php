<?php
// =============================================================
// admin/performance_admin.php  –  KPI Management & Scoring
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();
$cu = currentUser();
$db = getDB();

$success = '';
$error   = '';

// ---- Add / Edit KPI ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_kpi') {
        $name     = trim($_POST['kpi_name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $maxScore = (int)($_POST['max_score'] ?? 100);
        $cat      = trim($_POST['category'] ?? 'General');

        if (!$name) {
            $error = 'KPI name is required.';
        } else {
            $db->prepare("INSERT INTO performance_kpis (kpi_name, description, max_score, category) VALUES (?,?,?,?)")
               ->execute([$name, $desc, $maxScore, $cat]);
            $success = 'KPI added successfully.';
        }
    }

    if ($action === 'score') {
        $empId   = (int)($_POST['employee_id'] ?? 0);
        $kpiId   = (int)($_POST['kpi_id'] ?? 0);
        $score   = (float)($_POST['score'] ?? 0);
        $comments= trim($_POST['comments'] ?? '');
        $pMonth  = (int)($_POST['period_month'] ?? date('n'));
        $pYear   = (int)($_POST['period_year']  ?? date('Y'));

        if ($empId && $kpiId) {
            $db->prepare("
              INSERT INTO performance_scores (employee_id, kpi_id, score, period_month, period_year, comments, rated_by)
              VALUES (?,?,?,?,?,?,?)
              ON DUPLICATE KEY UPDATE score=VALUES(score), comments=VALUES(comments), rated_by=VALUES(rated_by)
            ")->execute([$empId, $kpiId, $score, $pMonth, $pYear, $comments, $cu['id']]);

            addNotification($empId,
                "Your KPI score has been updated for " . date('F Y', mktime(0,0,0,$pMonth,1,$pYear)) . ".",
                APP_URL . '/employee/performance.php');

            $success = 'Score saved.';
        }
    }

    if ($action === 'toggle_kpi') {
        $id = (int)($_POST['kpi_id'] ?? 0);
        $db->prepare("UPDATE performance_kpis SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
        $success = 'KPI status toggled.';
    }
}

$filterMonth = $_GET['month'] ?? date('Y-m');
[$fy, $fm]   = explode('-', $filterMonth);

// Active KPIs
$kpis = $db->query("SELECT * FROM performance_kpis ORDER BY category, kpi_name")->fetchAll();

// Employees
$employees = $db->query("SELECT id, full_name, emp_code, department FROM employees WHERE role='employee' AND status='active' ORDER BY full_name")->fetchAll();

// Scores grid: employee x kpi for this month
$scoresRaw = $db->prepare("
  SELECT employee_id, kpi_id, score, comments
  FROM performance_scores
  WHERE period_month=? AND period_year=?
");
$scoresRaw->execute([(int)$fm, (int)$fy]);
$scores = [];
foreach ($scoresRaw->fetchAll() as $s) {
    $scores[$s['employee_id']][$s['kpi_id']] = $s;
}

// Avg scores per employee (this month)
$avgPerEmp = $db->prepare("
  SELECT employee_id, ROUND(AVG(score),1) AS avg_score
  FROM performance_scores WHERE period_month=? AND period_year=?
  GROUP BY employee_id
");
$avgPerEmp->execute([(int)$fm, (int)$fy]);
$avgMap = [];
foreach ($avgPerEmp->fetchAll() as $a) {
    $avgMap[$a['employee_id']] = $a['avg_score'];
}

$pageTitle  = 'KPI Management';
$activePage = 'perf_admin';
require_once __DIR__ . '/../includes/nav.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem">

<!-- ---- Add KPI panel ---- -->
<div>
  <div class="form-card">
    <div class="form-card-title">Add New KPI</div>
    <form method="POST">
      <input type="hidden" name="action" value="add_kpi"/>
      <div class="form-group-plain">
        <label>KPI Name *</label>
        <input type="text" name="kpi_name" placeholder="e.g. Task Completion Rate" required/>
      </div>
      <div class="form-group-plain">
        <label>Category</label>
        <input type="text" name="category" value="General" placeholder="General, Productivity…"/>
      </div>
      <div class="form-group-plain">
        <label>Max Score</label>
        <input type="number" name="max_score" value="100" min="1" max="1000"/>
      </div>
      <div class="form-group-plain">
        <label>Description</label>
        <textarea name="description" rows="2" placeholder="Brief description…"></textarea>
      </div>
      <button type="submit" class="btn btn-primary w-full">Add KPI</button>
    </form>
  </div>

  <!-- KPI list -->
  <div class="table-card">
    <div class="table-card-header"><div class="table-card-title">All KPIs</div></div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Category</th><th>Max</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($kpis as $k): ?>
          <tr style="<?= !$k['is_active'] ? 'opacity:.4' : '' ?>">
            <td class="text-sm"><?= e($k['kpi_name']) ?></td>
            <td class="text-xs text-dim"><?= e($k['category']) ?></td>
            <td><?= $k['max_score'] ?></td>
            <td>
              <form method="POST" style="margin:0">
                <input type="hidden" name="action"  value="toggle_kpi"/>
                <input type="hidden" name="kpi_id"  value="<?= $k['id'] ?>"/>
                <button type="submit" class="btn btn-ghost btn-sm">
                  <?= $k['is_active'] ? 'Disable' : 'Enable' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ---- Score entry panel ---- -->
<div>
  <div class="form-card" style="margin-bottom:1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
      <div class="form-card-title" style="margin:0;border:none;padding:0">Score Employees</div>
      <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end">
        <div class="form-group-plain" style="margin:0">
          <label>Period</label>
          <input type="month" name="month" value="<?= e($filterMonth) ?>"/>
        </div>
        <button type="submit" class="btn btn-primary">Go</button>
      </form>
    </div>
  </div>

  <div class="table-card">
    <div class="table-card-header">
      <div class="table-card-title">Scores – <?= date('F Y', mktime(0,0,0,(int)$fm,1,(int)$fy)) ?></div>
      <a href="<?= APP_URL ?>/api/export.php?type=performance&month=<?= urlencode($filterMonth) ?>" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
    </div>
    <div class="table-wrap">
      <?php $activeKpis = array_filter($kpis, fn($k) => $k['is_active']); ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Employee</th>
            <?php foreach ($activeKpis as $k): ?>
              <th title="<?= e($k['description']??'') ?>"><?= e($k['kpi_name']) ?></th>
            <?php endforeach; ?>
            <th>Avg</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $emp): ?>
          <tr>
            <td>
              <div class="emp-cell">
                <div class="avatar-initials"><?= strtoupper(substr($emp['full_name'],0,1)) ?></div>
                <div>
                  <div class="text-sm font-bold"><?= e($emp['full_name']) ?></div>
                  <div class="text-xs text-dim"><?= e($emp['department']) ?></div>
                </div>
              </div>
            </td>
            <?php foreach ($activeKpis as $k): ?>
              <td>
                <?php $s = $scores[$emp['id']][$k['id']] ?? null; ?>
                <?php if ($s): ?>
                  <span class="text-accent font-bold"><?= $s['score'] ?></span>
                <?php else: ?>
                  <span class="text-dim">–</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <td>
              <?php if (isset($avgMap[$emp['id']])): ?>
                <span class="badge badge-present"><?= $avgMap[$emp['id']] ?></span>
              <?php else: ?>
                <span class="text-dim">–</span>
              <?php endif; ?>
            </td>
            <td>
              <button onclick="openScoreModal(<?= $emp['id'] ?>, '<?= e(addslashes($emp['full_name'])) ?>')"
                      class="btn btn-ghost btn-sm">Score</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div><!-- /grid -->

<!-- Score entry modal -->
<div class="modal-overlay" id="scoreModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Enter Scores – <span id="scoreEmpName"></span></div>
      <button class="modal-close" onclick="closeModal('scoreModal')">✕</button>
    </div>
    <form method="POST" id="scoreForm">
      <input type="hidden" name="action" value="score"/>
      <input type="hidden" name="employee_id" id="scoreEmpId"/>
      <input type="hidden" name="period_month" value="<?= (int)$fm ?>"/>
      <input type="hidden" name="period_year"  value="<?= (int)$fy ?>"/>
      <div class="modal-body">
        <div id="kpiScoreFields">
          <?php foreach ($activeKpis as $k): ?>
          <div class="form-group-plain" data-kpi-id="<?= $k['id'] ?>">
            <label><?= e($k['kpi_name']) ?> (0–<?= $k['max_score'] ?>)</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem">
              <input type="number" name="score" min="0" max="<?= $k['max_score'] ?>" placeholder="Score"
                     id="kpiScore_<?= $k['id'] ?>" data-kpi="<?= $k['id'] ?>" data-max="<?= $k['max_score'] ?>"/>
              <input type="text" name="comments" placeholder="Comment (optional)"
                     id="kpiComment_<?= $k['id'] ?>" data-kpi="<?= $k['id'] ?>"/>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- We submit one KPI at a time via JS loop -->
        <input type="hidden" name="kpi_id" id="currentKpiId"/>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('scoreModal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitAllScores()">Save All Scores</button>
      </div>
    </form>
  </div>
</div>

<script>
const activeKpis = <?= json_encode(array_values($activeKpis)) ?>;
const scores     = <?= json_encode($scores) ?>;
const filterMonth = '<?= $fm ?>';
const filterYear  = '<?= $fy ?>';

function openScoreModal(empId, empName) {
  document.getElementById('scoreEmpId').value   = empId;
  document.getElementById('scoreEmpName').textContent = empName;

  // Pre-fill existing scores
  activeKpis.forEach(k => {
    const inp = document.getElementById('kpiScore_' + k.id);
    const cmt = document.getElementById('kpiComment_' + k.id);
    const existing = scores[empId] && scores[empId][k.id];
    if (inp) inp.value = existing ? existing.score    : '';
    if (cmt) cmt.value = existing ? existing.comments : '';
  });

  openModal('scoreModal');
}

function submitAllScores() {
  const empId = document.getElementById('scoreEmpId').value;
  const promises = activeKpis.map(k => {
    const score   = document.getElementById('kpiScore_' + k.id).value;
    const comment = document.getElementById('kpiComment_' + k.id).value;
    if (score === '') return Promise.resolve();
    const fd = new FormData();
    fd.append('action', 'score');
    fd.append('employee_id', empId);
    fd.append('kpi_id', k.id);
    fd.append('score', score);
    fd.append('comments', comment);
    fd.append('period_month', filterMonth);
    fd.append('period_year', filterYear);
    return fetch(window.location.href, { method:'POST', body: fd });
  });
  Promise.all(promises).then(() => { closeModal('scoreModal'); location.reload(); });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
