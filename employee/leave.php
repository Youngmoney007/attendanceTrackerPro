<?php
// =============================================================
// employee/leave.php  –  Leave Requests
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$cu = currentUser();
$db = getDB();

$success = '';
$error   = '';

// ---- Handle new leave request ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    $type      = $_POST['leave_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate   = $_POST['end_date']   ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if (!$type || !$startDate || !$endDate) {
        $error = 'Please fill in all required fields.';
    } elseif ($startDate > $endDate) {
        $error = 'Start date cannot be after end date.';
    } else {
        $days = workingDaysBetween($startDate, $endDate);

        // Check for overlapping approved/pending requests
        $stmtChk = $db->prepare("
            SELECT COUNT(*) FROM leave_requests
            WHERE employee_id = ? AND status != 'rejected'
              AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))
        ");
        $stmtChk->execute([$cu['id'], $endDate, $startDate, $startDate, $startDate]);
        if ($stmtChk->fetchColumn() > 0) {
            $error = 'You already have a leave request overlapping these dates.';
        } else {
            $stmtI = $db->prepare("
                INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, total_days, reason)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtI->execute([$cu['id'], $type, $startDate, $endDate, $days, $reason]);

            // Notify all admins
            $admins = $db->query("SELECT id FROM employees WHERE role='admin'")->fetchAll();
            foreach ($admins as $admin) {
                addNotification($admin['id'],
                    "{$cu['full_name']} has submitted a {$type} leave request.",
                    APP_URL . '/admin/leave_management.php');
            }
            $success = 'Leave request submitted successfully!';
        }
    }
}

// ---- Fetch leave history ----------------------------------
$stmt = $db->prepare("
  SELECT lr.*, e.full_name AS reviewer_name
  FROM leave_requests lr
  LEFT JOIN employees e ON e.id = lr.reviewed_by
  WHERE lr.employee_id = ?
  ORDER BY lr.created_at DESC
");
$stmt->execute([$cu['id']]);
$leaveHistory = $stmt->fetchAll();

$pageTitle  = 'Leave Requests';
$activePage = 'leave';
require_once __DIR__ . '/../includes/nav.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<!-- Apply for leave -->
<div class="form-card">
  <div class="form-card-title">Apply for Leave</div>
  <form method="POST">
    <input type="hidden" name="action" value="apply"/>
    <div class="form-grid">
      <div class="form-group-plain">
        <label>Leave Type *</label>
        <select name="leave_type" required>
          <option value="">Select type…</option>
          <option value="annual">Annual Leave</option>
          <option value="sick">Sick Leave</option>
          <option value="emergency">Emergency Leave</option>
          <option value="unpaid">Unpaid Leave</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-group-plain">
        <label>Start Date *</label>
        <input type="date" name="start_date" min="<?= date('Y-m-d') ?>" required/>
      </div>
      <div class="form-group-plain">
        <label>End Date *</label>
        <input type="date" name="end_date" min="<?= date('Y-m-d') ?>" required/>
      </div>
    </div>
    <div class="form-group-plain">
      <label>Reason</label>
      <textarea name="reason" rows="3" placeholder="Brief explanation (optional)…"></textarea>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Submit Request</button>
    </div>
  </form>
</div>

<!-- History -->
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">My Leave History</div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Type</th><th>Start</th><th>End</th><th>Days</th>
        <th>Status</th><th>Reviewed By</th><th>Admin Notes</th>
      </tr></thead>
      <tbody>
      <?php if ($leaveHistory): foreach ($leaveHistory as $l): ?>
        <tr>
          <td><?= ucfirst(e($l['leave_type'])) ?></td>
          <td><?= date('d M Y', strtotime($l['start_date'])) ?></td>
          <td><?= date('d M Y', strtotime($l['end_date'])) ?></td>
          <td><?= $l['total_days'] ?></td>
          <td><?= leaveStatusBadge($l['status']) ?></td>
          <td class="text-dim text-sm"><?= e($l['reviewer_name'] ?? '–') ?></td>
          <td class="text-sm text-dim"><?= e($l['admin_notes'] ?? '') ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
            <p>No leave requests yet.</p>
          </div>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
