<?php
// =============================================================
// admin/qr_scans.php  –  QR Scan Audit Log
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();
$db = getDB();

$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterEmp  = (int)($_GET['emp_id'] ?? 0);

// ---- Fetch scan log ----------------------------------------
$stmt = $db->prepare("
  SELECT qs.*, e.full_name, e.emp_code, e.department
  FROM qr_scans qs
  LEFT JOIN employees e ON e.id = qs.employee_id
  WHERE DATE(qs.scanned_at) = :date
    AND (:emp = 0 OR qs.employee_id = :emp2)
  ORDER BY qs.scanned_at DESC
  LIMIT 200
");
$stmt->execute([':date' => $filterDate, ':emp' => $filterEmp, ':emp2' => $filterEmp]);
$scans = $stmt->fetchAll();

// Daily stats
$stmtStats = $db->prepare("
  SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN scan_result='success'  AND scan_type='checkin'  THEN 1 ELSE 0 END) AS checkins,
    SUM(CASE WHEN scan_result='success'  AND scan_type='checkout' THEN 1 ELSE 0 END) AS checkouts,
    SUM(CASE WHEN scan_result='invalid'  THEN 1 ELSE 0 END) AS invalid,
    SUM(CASE WHEN scan_result='already_done' THEN 1 ELSE 0 END) AS dupes
  FROM qr_scans
  WHERE DATE(scanned_at) = ?
");
$stmtStats->execute([$filterDate]);
$stats = $stmtStats->fetch();

// Employees list for filter
$employees = $db->query("SELECT id, full_name, emp_code FROM employees WHERE status='active' ORDER BY full_name")->fetchAll();

$pageTitle  = 'QR Scan Log';
$activePage = 'qr_scans';
require_once __DIR__ . '/../includes/nav.php';
?>

<!-- Filter bar -->
<div class="form-card" style="margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group-plain" style="margin:0">
      <label>Date</label>
      <input type="date" name="date" value="<?= e($filterDate) ?>" max="<?= date('Y-m-d') ?>"/>
    </div>
    <div class="form-group-plain" style="margin:0">
      <label>Employee</label>
      <select name="emp_id" style="min-width:180px">
        <option value="0">All Employees</option>
        <?php foreach ($employees as $emp): ?>
          <option value="<?= $emp['id'] ?>" <?= $filterEmp === (int)$emp['id'] ? 'selected' : '' ?>>
            <?= e($emp['full_name']) ?> (<?= e($emp['emp_code']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="<?= APP_URL ?>/kiosk.php" target="_blank" class="btn btn-ghost">
      📷 Open Kiosk
    </a>
  </form>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:1.25rem">
  <div class="stat-card" style="--card-accent:var(--text-dim)">
    <div class="stat-label-sm">Total Scans</div>
    <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
  </div>
  <div class="stat-card" style="--card-accent:var(--green)">
    <div class="stat-label-sm">Check-ins</div>
    <div class="stat-value"><?= $stats['checkins'] ?? 0 ?></div>
  </div>
  <div class="stat-card" style="--card-accent:var(--blue)">
    <div class="stat-label-sm">Check-outs</div>
    <div class="stat-value"><?= $stats['checkouts'] ?? 0 ?></div>
  </div>
  <div class="stat-card" style="--card-accent:var(--amber)">
    <div class="stat-label-sm">Duplicates</div>
    <div class="stat-value"><?= $stats['dupes'] ?? 0 ?></div>
  </div>
  <div class="stat-card" style="--card-accent:var(--red)">
    <div class="stat-label-sm">Invalid</div>
    <div class="stat-value"><?= $stats['invalid'] ?? 0 ?></div>
  </div>
</div>

<!-- Scan log table -->
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">
      Scan Log – <?= date('D, d F Y', strtotime($filterDate)) ?>
      <span class="text-dim text-sm" style="font-weight:400;margin-left:.5rem">(<?= count($scans) ?> records)</span>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Time</th><th>Employee</th><th>Department</th>
        <th>Type</th><th>Result</th><th>IP Address</th>
      </tr></thead>
      <tbody>
      <?php if ($scans): foreach ($scans as $s):
        $resultColors = [
          'success'    => 'badge-present',
          'already_done' => 'badge-leave',
          'no_checkin' => 'badge-halfday',
          'invalid'    => 'badge-absent',
        ];
        $cls = $resultColors[$s['scan_result']] ?? 'badge-default';
        $typeLabel = $s['scan_type'] === 'checkin' ? '▶ Check In' : '■ Check Out';
        $typeCls   = $s['scan_type'] === 'checkin'  ? 'badge-present' : 'badge-leave';
      ?>
        <tr>
          <td class="text-sm"><?= date('H:i:s', strtotime($s['scanned_at'])) ?></td>
          <td>
            <?php if ($s['full_name']): ?>
              <div class="emp-cell">
                <div class="avatar-initials" style="width:28px;height:28px;font-size:.7rem"><?= strtoupper(substr($s['full_name'],0,1)) ?></div>
                <div>
                  <div class="text-sm font-bold"><?= e($s['full_name']) ?></div>
                  <div class="text-xs text-dim"><?= e($s['emp_code'] ?? '') ?></div>
                </div>
              </div>
            <?php else: ?>
              <span class="text-dim text-sm">Unknown token</span>
            <?php endif; ?>
          </td>
          <td class="text-dim text-sm"><?= e($s['department'] ?? '–') ?></td>
          <td><span class="badge <?= $typeCls ?>"><?= $typeLabel ?></span></td>
          <td>
            <span class="badge <?= $cls ?>">
              <?= ucwords(str_replace('_', ' ', $s['scan_result'])) ?>
            </span>
          </td>
          <td class="text-xs text-dim"><?= e($s['ip_address'] ?? '–') ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8H3m2 8H2m10-2V8"/></svg>
            <p>No scan activity for this date.</p>
          </div>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
