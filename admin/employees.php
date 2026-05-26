<?php
// =============================================================
// admin/employees.php  –  Employee CRUD
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();
$db = getDB();

$success = '';
$error   = '';

// ---- Handle form submissions ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id       = (int)($_POST['emp_id'] ?? 0);
        $fullName = trim($_POST['full_name']  ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $role     = $_POST['role']       ?? 'employee';
        $dept     = trim($_POST['department'] ?? '');
        $pos      = trim($_POST['position']   ?? '');
        $phone    = trim($_POST['phone']      ?? '');
        $hire     = $_POST['hire_date']  ?? '';
        $status   = $_POST['status']     ?? 'active';
        $password = trim($_POST['password']   ?? '');

        if (!$fullName || !$email) {
            $error = 'Name and email are required.';
        } else {
            if ($action === 'add') {
                if (!$password) { $error = 'Password is required for new employees.'; }
                else {
                    // Check email unique
                    $chk = $db->prepare("SELECT COUNT(*) FROM employees WHERE email=?");
                    $chk->execute([$email]);
                    if ($chk->fetchColumn() > 0) {
                        $error = 'Email already exists.';
                    } else {
                        // Auto generate emp_code
                        $lastCode = $db->query("SELECT MAX(CAST(SUBSTRING(emp_code,4) AS UNSIGNED)) FROM employees WHERE emp_code LIKE 'EMP%'")->fetchColumn();
                        $newCode  = 'EMP' . str_pad((int)$lastCode + 1, 3, '0', STR_PAD_LEFT);

                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $db->prepare("
                          INSERT INTO employees (emp_code,full_name,email,password_hash,role,department,position,phone,hire_date,status)
                          VALUES (?,?,?,?,?,?,?,?,?,?)
                        ")->execute([$newCode,$fullName,$email,$hash,$role,$dept,$pos,$phone,$hire,$status]);
                        $success = "Employee {$fullName} added with code {$newCode}.";
                    }
                }
            } elseif ($action === 'edit' && $id) {
                $params = [$fullName,$email,$role,$dept,$pos,$phone,$hire,$status,$id];
                $db->prepare("
                  UPDATE employees SET full_name=?,email=?,role=?,department=?,position=?,phone=?,hire_date=?,status=?
                  WHERE id=?
                ")->execute($params);
                if ($password) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $db->prepare("UPDATE employees SET password_hash=? WHERE id=?")->execute([$hash,$id]);
                }
                $success = "Employee updated.";
            }
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['emp_id'] ?? 0);
        $db->prepare("UPDATE employees SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
        $success = 'Employee status updated.';
    }
}

// ---- Fetch employees ----------------------------------------
$search = trim($_GET['search'] ?? '');
$stmt   = $db->prepare("
  SELECT * FROM employees
  WHERE role != 'admin'
    AND (? = '' OR full_name LIKE ? OR email LIKE ? OR emp_code LIKE ?)
  ORDER BY department, full_name
");
$like = "%$search%";
$stmt->execute([$search, $like, $like, $like]);
$employees = $stmt->fetchAll();

// Fetch one employee for edit modal
$editEmp = null;
if (!empty($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM employees WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editEmp = $s->fetch();
    if ($editEmp) {
        echo "<script>window.addEventListener('DOMContentLoaded',()=>openModal('empModal'))</script>";
    }
}

$pageTitle  = 'Employees';
$activePage = 'employees';
require_once __DIR__ . '/../includes/nav.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<!-- Search + Add -->
<div style="display:flex;gap:1rem;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end">
    <div class="form-group-plain" style="margin:0">
      <label>Search</label>
      <input type="text" name="search" value="<?= e($search) ?>" placeholder="Name, email, code…" style="width:260px"/>
    </div>
    <button type="submit" class="btn btn-ghost">Search</button>
    <?php if ($search): ?><a href="?" class="btn btn-ghost">Clear</a><?php endif; ?>
  </form>
  <button onclick="openModal('empModal')" class="btn btn-primary">+ Add Employee</button>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">All Employees (<?= count($employees) ?>)</div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Code</th><th>Employee</th><th>Department</th><th>Position</th>
        <th>Hire Date</th><th>Status</th><th>Role</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if ($employees): foreach ($employees as $emp): ?>
        <tr>
          <td class="text-dim text-sm"><?= e($emp['emp_code']) ?></td>
          <td>
            <div class="emp-cell">
              <div class="avatar-initials"><?= strtoupper(substr($emp['full_name'],0,1)) ?></div>
              <div>
                <div class="text-sm font-bold"><?= e($emp['full_name']) ?></div>
                <div class="text-xs text-dim"><?= e($emp['email']) ?></div>
              </div>
            </div>
          </td>
          <td class="text-dim text-sm"><?= e($emp['department'] ?? '–') ?></td>
          <td class="text-sm"><?= e($emp['position'] ?? '–') ?></td>
          <td class="text-sm text-dim"><?= $emp['hire_date'] ? date('d M Y', strtotime($emp['hire_date'])) : '–' ?></td>
          <td>
            <?php if ($emp['status'] === 'active'): ?>
              <span class="badge badge-present">Active</span>
            <?php else: ?>
              <span class="badge badge-absent">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($emp['role'] === 'admin'): ?>
              <span class="badge badge-admin">Admin</span>
            <?php else: ?>
              <span class="badge badge-default">Employee</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:.4rem">
              <a href="?edit=<?= $emp['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
              <form method="POST" style="margin:0">
                <input type="hidden" name="action"  value="toggle_status"/>
                <input type="hidden" name="emp_id"  value="<?= $emp['id'] ?>"/>
                <button type="submit" class="btn btn-ghost btn-sm">
                  <?= $emp['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="8"><div class="empty-state"><p>No employees found.</p></div></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Employee Modal -->
<div class="modal-overlay" id="empModal">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <div class="modal-title"><?= $editEmp ? 'Edit Employee' : 'Add New Employee' ?></div>
      <button class="modal-close" onclick="closeModal('empModal');history.replaceState(null,'',window.location.pathname)">✕</button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action"  value="<?= $editEmp ? 'edit' : 'add' ?>"/>
        <input type="hidden" name="emp_id"  value="<?= $editEmp ? $editEmp['id'] : '' ?>"/>
        <div class="form-grid">
          <div class="form-group-plain">
            <label>Full Name *</label>
            <input type="text" name="full_name" value="<?= e($editEmp['full_name'] ?? '') ?>" required/>
          </div>
          <div class="form-group-plain">
            <label>Email *</label>
            <input type="email" name="email" value="<?= e($editEmp['email'] ?? '') ?>" required/>
          </div>
          <div class="form-group-plain">
            <label>Department</label>
            <input type="text" name="department" value="<?= e($editEmp['department'] ?? '') ?>" placeholder="Engineering, HR…"/>
          </div>
          <div class="form-group-plain">
            <label>Position</label>
            <input type="text" name="position" value="<?= e($editEmp['position'] ?? '') ?>"/>
          </div>
          <div class="form-group-plain">
            <label>Phone</label>
            <input type="text" name="phone" value="<?= e($editEmp['phone'] ?? '') ?>"/>
          </div>
          <div class="form-group-plain">
            <label>Hire Date</label>
            <input type="date" name="hire_date" value="<?= e($editEmp['hire_date'] ?? '') ?>"/>
          </div>
          <div class="form-group-plain">
            <label>Role</label>
            <select name="role">
              <option value="employee" <?= ($editEmp['role']??'')==='employee'?'selected':'' ?>>Employee</option>
              <option value="admin"    <?= ($editEmp['role']??'')==='admin'   ?'selected':'' ?>>Admin</option>
            </select>
          </div>
          <div class="form-group-plain">
            <label>Status</label>
            <select name="status">
              <option value="active"   <?= ($editEmp['status']??'active')==='active'  ?'selected':'' ?>>Active</option>
              <option value="inactive" <?= ($editEmp['status']??'')==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group-plain">
          <label>Password <?= $editEmp ? '(leave blank to keep current)' : '*' ?></label>
          <input type="password" name="password" placeholder="<?= $editEmp ? 'New password…' : 'Set password' ?>"
                 <?= $editEmp ? '' : 'required' ?>/>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('empModal');history.replaceState(null,'',window.location.pathname)">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= $editEmp ? 'Save Changes' : 'Add Employee' ?></button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
