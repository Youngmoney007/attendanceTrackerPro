<?php
// =============================================================
// admin/leave_management.php  –  Approve / Reject Leave
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();
$cu = currentUser();
$db = getDB();

$success = '';
$error   = '';

// ---- Handle approve / reject ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaveId    = (int)($_POST['leave_id'] ?? 0);
    $action     = $_POST['action'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if ($leaveId && in_array($action, ['approve', 'reject'])) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $stmt = $db->prepare("
          UPDATE leave_requests
          SET status=?, reviewed_by=?, reviewed_at=NOW(), admin_notes=?
          WHERE id=?
        ");
        $stmt->execute([$newStatus, $cu['id'], $adminNotes, $leaveId]);

        // Fetch leave for notification
        $lr = $db->prepare("SELECT lr.*, e.id AS emp_id, e.full_name FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.id=?");
        $lr->execute([$leaveId]);
        $lr = $lr->fetch();
        if ($lr) {
            addNotification($lr['emp_id'],
                "Your {$lr['leave_type']} leave request has been {$newStatus}.",
                APP_URL . '/employee/leave.php');
        }

        // If approved, mark attendance as on_leave for those dates
        if ($newStatus === 'approved' && $lr) {
            $cur = strtotime($lr['start_date']);
            $end = strtotime($lr['end_date']);
            while ($cur <= $end) {
                $dow = (int) date('N', $cur);
                if ($dow < 6) { // Mon-Fri only
                    $wDate = date('Y-m-d', $cur);
                    $stmtA = $db->prepare("
                      INSERT INTO attendance (employee_id, work_date, status)
                      VALUES (?, ?, 'on_leave')
                      ON DUPLICATE KEY UPDATE status='on_leave'
                    ");
                    $stmtA->execute([$lr['emp_id'], $wDate]);
                }
                $cur = strtotime('+1 day', $cur);
            }
        }

        $success = "Leave request #{$leaveId} has been {$newStatus}.";
    }
}

// ---- Filters ---------------------------------------------
$statusFilter = $_GET['status'] ?? 'pending';

$stmt = $db->prepare("
  SELECT lr.*, e.full_name, e.emp_code, e.department
  FROM leave_requests lr
  JOIN employees e ON e.id = lr.employee_id
  WHERE (? = 'all' OR lr.status = ?)
  ORDER BY lr.created_at DESC
  LIMIT 100
");
$stmt->execute([$statusFilter, $statusFilter]);
$leaves = $stmt->fetchAll();

// Counts
$counts = $db->query("
  SELECT status, COUNT(*) AS cnt FROM leave_requests GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle  = 'Leave Management';
$activePage = 'leave_mgmt';
require_once __DIR__ . '/../includes/nav.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<!-- Quick count cards -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.25rem">
  <?php foreach(['pending'=>'amber','approved'=>'green','rejected'=>'red','all'=>'blue'] as $s => $c): ?>
  <a href="?status=<?= $s ?>" style="text-decoration:none">
    <div class="stat-card" style="--card-accent:var(--<?= $c ?>);cursor:pointer;<?= $statusFilter===$s?'border-color:var(--'.$c.')':'' ?>">
      <div class="stat-label-sm"><?= ucfirst($s) ?></div>
      <div class="stat-value"><?= $s === 'all' ? array_sum($counts) : ($counts[$s] ?? 0) ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">Leave Requests – <?= ucfirst($statusFilter) ?></div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Employee</th><th>Type</th><th>Start</th><th>End</th>
        <th>Days</th><th>Reason</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if ($leaves): foreach ($leaves as $l): ?>
        <tr>
          <td>
            <div class="emp-cell">
              <div class="avatar-initials"><?= strtoupper(substr($l['full_name'],0,1)) ?></div>
              <div>
                <div class="text-sm font-bold"><?= e($l['full_name']) ?></div>
                <div class="text-xs text-dim"><?= e($l['emp_code']) ?> · <?= e($l['department']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge badge-leave"><?= ucfirst(e($l['leave_type'])) ?></span></td>
          <td><?= date('d M Y', strtotime($l['start_date'])) ?></td>
          <td><?= date('d M Y', strtotime($l['end_date'])) ?></td>
          <td><?= $l['total_days'] ?></td>
          <td class="text-sm text-dim" style="max-width:200px;white-space:normal"><?= e(substr($l['reason']??'',0,80)) ?></td>
          <td><?= leaveStatusBadge($l['status']) ?></td>
          <td>
            <?php if ($l['status'] === 'pending'): ?>
              <div style="display:flex;gap:.4rem">
                <button onclick="openLeaveModal(<?= $l['id'] ?>,'approve')" class="btn btn-success btn-sm">Approve</button>
                <button onclick="openLeaveModal(<?= $l['id'] ?>,'reject')"  class="btn btn-danger btn-sm">Reject</button>
              </div>
            <?php else: ?>
              <span class="text-xs text-dim"><?= $l['admin_notes'] ? e(substr($l['admin_notes'],0,40)) : '–' ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="8"><div class="empty-state"><p>No leave requests found.</p></div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Approve/Reject modal -->
<div class="modal-overlay" id="leaveModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modalTitle">Review Leave</div>
      <button class="modal-close" onclick="closeModal('leaveModal')">✕</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="leave_id" id="modalLeaveId"/>
        <input type="hidden" name="action"   id="modalAction"/>
        <div class="form-group-plain">
          <label>Admin Notes (optional)</label>
          <textarea name="admin_notes" rows="3" placeholder="Reason for decision…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('leaveModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
function openLeaveModal(id, action) {
  document.getElementById('modalLeaveId').value  = id;
  document.getElementById('modalAction').value   = action;
  document.getElementById('modalTitle').textContent = action === 'approve' ? 'Approve Leave Request' : 'Reject Leave Request';
  document.getElementById('modalSubmitBtn').textContent = action === 'approve' ? 'Approve' : 'Reject';
  document.getElementById('modalSubmitBtn').className = 'btn ' + (action === 'approve' ? 'btn-success' : 'btn-danger');
  openModal('leaveModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
