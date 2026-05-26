<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
  <title>AttendTrack – Check In / Out</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>

<?php
// =============================================================
// checkin.php
// Mobile-optimised page employees land on after scanning the
// shared office QR code.
//
// Flow:
//   1. Employee scans QR → phone opens checkin.php?t=<qr_token>
//   2. Page validates the office QR token
//   3. Shows a search box / employee list to pick themselves
//   4. They optionally enter their email to confirm identity
//   5. POST → records check-in or check-out
//   6. Success screen shown
// =============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

$db = getDB();

// ---- 1. Validate office QR token --------------------------
$qrToken = trim($_GET['t'] ?? '');

if (!$qrToken) {
    $pageError = 'Missing QR code. Please scan the QR code at the entrance again.';
} else {
    $stmtQR = $db->prepare("SELECT * FROM office_qr_codes WHERE qr_token=? AND is_active=1 LIMIT 1");
    $stmtQR->execute([$qrToken]);
    $officeQR = $stmtQR->fetch();

    if (!$officeQR) {
        $pageError = 'This QR code is no longer valid. Please ask your admin to print the updated QR code.';
    }
}

// ---- 2. Handle attendance submission ----------------------
$submitSuccess = null;   // will hold result array on success
$submitError   = '';

if (!isset($pageError) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $empId     = (int)($_POST['employee_id'] ?? 0);
    $action    = $_POST['action']    ?? 'checkin';   // checkin | checkout
    $emailConf = strtolower(trim($_POST['email_confirm'] ?? ''));

    if (!$empId) {
        $submitError = 'Please select your name from the list.';
    } elseif (!in_array($action, ['checkin', 'checkout'])) {
        $submitError = 'Invalid action.';
    } else {
        // Fetch employee
        $stmtEmp = $db->prepare("SELECT * FROM employees WHERE id=? AND status='active' LIMIT 1");
        $stmtEmp->execute([$empId]);
        $emp = $stmtEmp->fetch();

        if (!$emp) {
            $submitError = 'Employee not found. Please try again.';
        } elseif ($emailConf && strtolower($emp['email']) !== $emailConf) {
            // Email confirmation provided but doesn't match
            $submitError = 'Email does not match our records for ' . htmlspecialchars($emp['full_name'], ENT_QUOTES) . '. Please check and try again.';
        } else {
            $today = date('Y-m-d');
            $now   = date('Y-m-d H:i:s');

            // Fetch today's record
            $stmtAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id=? AND work_date=? LIMIT 1");
            $stmtAtt->execute([$empId, $today]);
            $att = $stmtAtt->fetch();

            if ($action === 'checkin') {
                if ($att && $att['check_in']) {
                    $submitSuccess = [
                        'type'     => 'already',
                        'action'   => 'checkin',
                        'employee' => $emp,
                        'time'     => date('H:i', strtotime($att['check_in'])),
                        'status'   => $att['status'],
                    ];
                } else {
                    $checkInTime = date('H:i', strtotime($now));
                    $status      = ($checkInTime > LATE_THRESHOLD) ? 'late' : 'present';

                    if ($att) {
                        $db->prepare("UPDATE attendance SET check_in=?,status=? WHERE id=?")
                           ->execute([$now, $status, $att['id']]);
                    } else {
                        $db->prepare("INSERT INTO attendance (employee_id,work_date,check_in,status) VALUES (?,?,?,?)")
                           ->execute([$empId, $today, $now, $status]);
                    }

                    // Log the scan
                    $db->prepare("INSERT INTO qr_scans (employee_id,token,scan_type,scan_result,ip_address) VALUES (?,?,?,?,?)")
                       ->execute([$empId, $qrToken, 'checkin', 'success', $_SERVER['REMOTE_ADDR'] ?? '']);

                    $submitSuccess = [
                        'type'     => 'success',
                        'action'   => 'checkin',
                        'employee' => $emp,
                        'time'     => date('H:i'),
                        'status'   => $status,
                    ];
                }
            } elseif ($action === 'checkout') {
                if (!$att || !$att['check_in']) {
                    $submitError = 'You have not checked in today. Please check in first.';
                } elseif ($att['check_out']) {
                    $submitSuccess = [
                        'type'     => 'already',
                        'action'   => 'checkout',
                        'employee' => $emp,
                        'time'     => date('H:i', strtotime($att['check_out'])),
                        'hours'    => formatHours($att['total_hours']),
                    ];
                } else {
                    $hours  = calcHours($att['check_in'], $now);
                    $status = deriveStatus($att['check_in'], $hours);

                    $db->prepare("UPDATE attendance SET check_out=?,total_hours=?,status=? WHERE id=?")
                       ->execute([$now, $hours, $status, $att['id']]);

                    $db->prepare("INSERT INTO qr_scans (employee_id,token,scan_type,scan_result,ip_address) VALUES (?,?,?,?,?)")
                       ->execute([$empId, $qrToken, 'checkout', 'success', $_SERVER['REMOTE_ADDR'] ?? '']);

                    $submitSuccess = [
                        'type'     => 'success',
                        'action'   => 'checkout',
                        'employee' => $emp,
                        'time'     => date('H:i'),
                        'hours'    => formatHours($hours),
                        'status'   => $status,
                    ];
                }
            }
        }
    }
}

// ---- 3. Load employees for the picker ---------------------
$employees = [];
if (!isset($pageError)) {
    $employees = $db->query("
        SELECT id, full_name, emp_code, department
        FROM employees
        WHERE status='active' AND role='employee'
        ORDER BY full_name
    ")->fetchAll();
}
?>
  <style>
    /* ==========================================================
       MOBILE CHECK-IN PAGE
       Clean, large-touch, works perfectly on any phone screen
       ========================================================== */
    :root {
      --accent:  #f0ff4b;
      --bg:      #0d0d0d;
      --bg2:     #161616;
      --surface: #1e1e1e;
      --border:  #2a2a2a;
      --text:    #f0f0f0;
      --dim:     #777;
      --green:   #22c55e;
      --red:     #ef4444;
      --amber:   #f59e0b;
      --blue:    #3b82f6;
      --r:       14px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      -webkit-font-smoothing: antialiased;
    }

    /* ---- Header ------------------------------------------- */
    .header {
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      gap: .75rem;
    }
    .header-logo {
      width: 38px; height: 38px;
      background: var(--accent);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .header-logo svg { width: 22px; height: 22px; }
    .header-title { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 800; }
    .header-sub   { font-size: .75rem; color: var(--dim); }
    .header-time  {
      margin-left: auto;
      font-family: 'Syne', sans-serif;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--accent);
    }

    /* ---- Main container ----------------------------------- */
    .container { flex: 1; padding: 1.5rem 1.25rem; max-width: 480px; margin: 0 auto; width: 100%; }

    /* ---- Error full-page ---------------------------------- */
    .error-page {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 2rem 1.5rem; text-align: center;
    }
    .error-icon { font-size: 3.5rem; margin-bottom: 1rem; }
    .error-title { font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800; margin-bottom: .75rem; }
    .error-msg { color: var(--dim); font-size: .95rem; line-height: 1.6; }

    /* ---- Date/time strip ---------------------------------- */
    .date-strip {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .date-strip-date { font-size: .88rem; color: var(--dim); }
    .date-strip-time { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 800; }

    /* ---- Mode toggle (Check In / Out) --------------------- */
    .mode-toggle {
      display: grid;
      grid-template-columns: 1fr 1fr;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 4px;
      gap: 4px;
      margin-bottom: 1.5rem;
    }
    .mode-btn {
      padding: .85rem;
      border-radius: 10px;
      border: none;
      background: transparent;
      color: var(--dim);
      font-size: .95rem;
      font-weight: 600;
      font-family: 'Syne', sans-serif;
      cursor: pointer;
      transition: all .2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .4rem;
    }
    .mode-btn.active { background: var(--accent); color: #0a0a0a; }

    /* ---- Search + employee picker ------------------------- */
    .section-label {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: var(--dim);
      margin-bottom: .6rem;
    }

    .search-wrap {
      position: relative;
      margin-bottom: 1rem;
    }
    .search-wrap svg {
      position: absolute;
      left: .9rem; top: 50%;
      transform: translateY(-50%);
      color: var(--dim);
      pointer-events: none;
      width: 18px; height: 18px;
    }
    #empSearch {
      width: 100%;
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: var(--r);
      padding: .85rem .9rem .85rem 2.6rem;
      color: var(--text);
      font-size: 1rem;
      font-family: 'DM Sans', sans-serif;
      -webkit-appearance: none;
    }
    #empSearch:focus { outline: none; border-color: var(--accent); }
    #empSearch::placeholder { color: var(--dim); }

    /* Employee list */
    .emp-list {
      display: flex;
      flex-direction: column;
      gap: .5rem;
      max-height: 340px;
      overflow-y: auto;
      margin-bottom: 1.5rem;
      /* Custom scrollbar */
      scrollbar-width: thin;
      scrollbar-color: var(--border) transparent;
    }
    .emp-list::-webkit-scrollbar { width: 4px; }
    .emp-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

    .emp-item {
      display: flex;
      align-items: center;
      gap: .85rem;
      padding: .85rem 1rem;
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: var(--r);
      cursor: pointer;
      transition: border-color .15s, background .15s;
      -webkit-tap-highlight-color: transparent;
    }
    .emp-item:hover, .emp-item:active { border-color: var(--accent); background: rgba(240,255,75,.04); }
    .emp-item.selected {
      border-color: var(--accent);
      background: rgba(240,255,75,.08);
    }
    .emp-avatar {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: var(--accent);
      color: #0a0a0a;
      font-family: 'Syne', sans-serif;
      font-size: .95rem;
      font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .emp-item.selected .emp-avatar { background: var(--accent); }
    .emp-name { font-weight: 600; font-size: .95rem; line-height: 1.2; }
    .emp-meta { font-size: .78rem; color: var(--dim); margin-top: 2px; }
    .emp-tick {
      margin-left: auto;
      width: 22px; height: 22px;
      border-radius: 50%;
      background: var(--accent);
      color: #0a0a0a;
      font-size: .8rem;
      font-weight: 800;
      display: none;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .emp-item.selected .emp-tick { display: flex; }

    .emp-empty {
      text-align: center;
      padding: 2rem;
      color: var(--dim);
      font-size: .9rem;
    }

    /* ---- Email confirm (optional) ------------------------- */
    .email-section {
      margin-bottom: 1.5rem;
      display: none;
    }
    .email-section.visible { display: block; }
    .email-label {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: var(--dim);
      margin-bottom: .5rem;
      display: flex;
      align-items: center;
      gap: .4rem;
    }
    .email-optional {
      background: rgba(255,255,255,.05);
      border-radius: 4px;
      padding: 1px 6px;
      font-size: .68rem;
      text-transform: none;
      letter-spacing: 0;
      color: var(--dim);
    }
    #emailConfirm {
      width: 100%;
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: var(--r);
      padding: .85rem .9rem;
      color: var(--text);
      font-size: 1rem;
      font-family: 'DM Sans', sans-serif;
      -webkit-appearance: none;
    }
    #emailConfirm:focus { outline: none; border-color: var(--accent); }

    /* ---- Submit button ------------------------------------- */
    .submit-btn {
      width: 100%;
      padding: 1.1rem;
      border-radius: var(--r);
      border: none;
      font-size: 1.05rem;
      font-weight: 800;
      font-family: 'Syne', sans-serif;
      cursor: pointer;
      transition: all .15s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      margin-bottom: 1rem;
    }
    .submit-btn.checkin-btn  { background: var(--green); color: #fff; }
    .submit-btn.checkout-btn { background: var(--blue);  color: #fff; }
    .submit-btn:disabled { opacity: .35; cursor: not-allowed; transform: none !important; }
    .submit-btn:not(:disabled):hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,.3); }
    .submit-btn:not(:disabled):active { transform: translateY(0); }

    /* ---- Error alert --------------------------------------- */
    .alert {
      background: rgba(239,68,68,.1);
      border: 1px solid rgba(239,68,68,.3);
      color: #fca5a5;
      border-radius: var(--r);
      padding: .85rem 1rem;
      font-size: .9rem;
      margin-bottom: 1rem;
    }

    /* ---- Success screen ------------------------------------ */
    .success-screen {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.5rem;
      text-align: center;
      animation: fadeUp .4s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: none; }
    }
    .success-ring {
      width: 110px; height: 110px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 3rem;
      margin-bottom: 1.5rem;
      position: relative;
    }
    .success-ring.ok     { background: rgba(34,197,94,.15);  color: var(--green); border: 3px solid var(--green);  }
    .success-ring.warn   { background: rgba(245,158,11,.15); color: var(--amber); border: 3px solid var(--amber); }
    .success-ring.late   { background: rgba(245,158,11,.15); color: var(--amber); border: 3px solid var(--amber); }
    .success-ring.out    { background: rgba(59,130,246,.15); color: var(--blue);  border: 3px solid var(--blue);  }

    .success-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.8rem;
      font-weight: 800;
      margin-bottom: .5rem;
    }
    .success-name {
      font-size: 1.1rem;
      color: var(--accent);
      font-weight: 600;
      margin-bottom: .35rem;
    }
    .success-sub  { font-size: .9rem; color: var(--dim); margin-bottom: 2rem; line-height: 1.6; }

    .success-details {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 1.25rem 1.5rem;
      width: 100%;
      max-width: 320px;
      margin-bottom: 2rem;
    }
    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .5rem 0;
      font-size: .9rem;
      border-bottom: 1px solid var(--border);
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { color: var(--dim); }
    .detail-val   { font-weight: 600; }

    .btn-again {
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: var(--r);
      padding: .85rem 2rem;
      color: var(--text);
      font-size: .95rem;
      font-weight: 600;
      font-family: 'Syne', sans-serif;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }
    .btn-again:hover { border-color: var(--accent); color: var(--accent); }

    /* ---- Already-done info card --------------------------- */
    .info-card {
      background: rgba(59,130,246,.08);
      border: 1px solid rgba(59,130,246,.25);
      border-radius: var(--r);
      padding: 1rem;
      margin-bottom: 1.25rem;
      font-size: .9rem;
      color: #93c5fd;
    }

    /* ---- Footer note -------------------------------------- */
    .footer-note {
      text-align: center;
      font-size: .75rem;
      color: var(--dim);
      padding: 1.25rem;
      border-top: 1px solid var(--border);
      margin-top: auto;
    }
  </style>
</head>
<body>

<!-- Header -->
<div class="header">
  <div class="header-logo">
    <svg viewBox="0 0 22 22" fill="none">
      <circle cx="11" cy="11" r="9" stroke="#0a0a0a" stroke-width="2.2"/>
      <path d="M11 7v4l2.5 1.5" stroke="#0a0a0a" stroke-width="2.2" stroke-linecap="round"/>
    </svg>
  </div>
  <div>
    <div class="header-title"><?= APP_NAME ?></div>
    <div class="header-sub">Attendance Check-In</div>
  </div>
  <div class="header-time" id="headerClock"></div>
</div>

<?php if (isset($pageError)): ?>
<!-- ===== INVALID QR ERROR ===== -->
<div class="error-page">
  <div class="error-icon">⚠️</div>
  <div class="error-title">Invalid QR Code</div>
  <div class="error-msg"><?= e($pageError) ?></div>
</div>

<?php elseif ($submitSuccess): ?>
<!-- ===== SUCCESS SCREEN ===== -->
<?php
  $s        = $submitSuccess;
  $isAlready = $s['type'] === 'already';
  $isCheckin = $s['action'] === 'checkin';
  $initials  = strtoupper(substr($s['employee']['full_name'],0,1)
             . substr(strrchr($s['employee']['full_name'],' '),1,1));

  // Ring class
  if ($isAlready)         $ringClass = 'warn';
  elseif ($isCheckin && ($s['status']??'') === 'late') $ringClass = 'late';
  elseif (!$isCheckin)    $ringClass = 'out';
  else                    $ringClass = 'ok';

  $icons = ['ok'=>'✓', 'warn'=>'ℹ', 'late'=>'⏰', 'out'=>'✓'];
  $icon  = $icons[$ringClass];
?>
<div class="success-screen">
  <div class="success-ring <?= $ringClass ?>"><?= $icon ?></div>

  <div class="success-name"><?= e($s['employee']['full_name']) ?></div>

  <?php if ($isAlready && $isCheckin): ?>
    <div class="success-title">Already Checked In</div>
    <div class="success-sub">You checked in at <?= e($s['time']) ?> today.<br>No duplicate record created.</div>
  <?php elseif ($isAlready && !$isCheckin): ?>
    <div class="success-title">Already Checked Out</div>
    <div class="success-sub">You checked out at <?= e($s['time']) ?> today.<br>Total: <?= e($s['hours']) ?></div>
  <?php elseif ($isCheckin && ($s['status']??'') === 'late'): ?>
    <div class="success-title">Checked In (Late)</div>
    <div class="success-sub">You're past the <?= LATE_THRESHOLD ?> threshold.<br>Have a productive day!</div>
  <?php elseif ($isCheckin): ?>
    <div class="success-title">Checked In! ✓</div>
    <div class="success-sub">Good morning! Your attendance<br>has been recorded.</div>
  <?php else: ?>
    <div class="success-title">Checked Out! ✓</div>
    <div class="success-sub">Great work today! See you tomorrow.</div>
  <?php endif; ?>

  <div class="success-details">
    <div class="detail-row">
      <span class="detail-label">Employee</span>
      <span class="detail-val"><?= e($s['employee']['emp_code']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label"><?= $isCheckin ? 'Check-In Time' : 'Check-Out Time' ?></span>
      <span class="detail-val" style="color:var(--accent)"><?= e($s['time']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Date</span>
      <span class="detail-val"><?= date('D, d M Y') ?></span>
    </div>
    <?php if (!$isCheckin && isset($s['hours'])): ?>
    <div class="detail-row">
      <span class="detail-label">Total Hours</span>
      <span class="detail-val" style="color:var(--green)"><?= e($s['hours']) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <a href="checkin.php?t=<?= urlencode($qrToken) ?>" class="btn-again">← Back (for next person)</a>
</div>

<?php else: ?>
<!-- ===== CHECK-IN FORM ===== -->
<div class="container">

  <!-- Date/time strip -->
  <div class="date-strip">
    <div class="date-strip-date"><?= date('l, d F Y') ?></div>
    <div class="date-strip-time" id="stripClock">--:--</div>
  </div>

  <?php if ($submitError): ?>
    <div class="alert"><?= e($submitError) ?></div>
  <?php endif; ?>

  <form method="POST" id="checkinForm" action="checkin.php?t=<?= urlencode($qrToken) ?>">
    <input type="hidden" name="employee_id" id="selectedEmpId" value=""/>
    <input type="hidden" name="action"      id="actionField"   value="checkin"/>

    <!-- Mode toggle -->
    <div class="mode-toggle">
      <button type="button" class="mode-btn active" id="btnIn"  onclick="setAction('checkin')">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 3l14 9-14 9V3z"/></svg>
        Check In
      </button>
      <button type="button" class="mode-btn" id="btnOut" onclick="setAction('checkout')">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><rect x="4" y="4" width="16" height="16" rx="2" stroke-width="2.5"/></svg>
        Check Out
      </button>
    </div>

    <!-- Employee search -->
    <div class="section-label">Select your name</div>
    <div class="search-wrap">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
      </svg>
      <input type="text" id="empSearch" placeholder="Search your name…"
             autocomplete="off" autocorrect="off" autocapitalize="words"
             oninput="filterEmployees(this.value)"/>
    </div>

    <!-- Employee list -->
    <div class="emp-list" id="empList">
      <?php foreach ($employees as $emp):
        $init = strtoupper(substr($emp['full_name'],0,1));
      ?>
      <div class="emp-item"
           data-id="<?= $emp['id'] ?>"
           data-name="<?= e(strtolower($emp['full_name'])) ?>"
           onclick="selectEmployee(this, <?= $emp['id'] ?>)">
        <div class="emp-avatar"><?= e($init) ?></div>
        <div>
          <div class="emp-name"><?= e($emp['full_name']) ?></div>
          <div class="emp-meta"><?= e($emp['emp_code']) ?><?= $emp['department'] ? ' · ' . e($emp['department']) : '' ?></div>
        </div>
        <div class="emp-tick">✓</div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Email confirmation (optional security layer) -->
    <div class="email-section" id="emailSection">
      <div class="email-label">
        Confirm your email
        <span class="email-optional">optional</span>
      </div>
      <input type="email" id="emailConfirm" name="email_confirm"
             placeholder="your@email.com"
             autocomplete="email"
             inputmode="email"/>
      <div style="font-size:.75rem;color:var(--dim);margin-top:.4rem">
        Leave blank to skip — or enter your work email to confirm identity.
      </div>
    </div>

    <!-- Submit -->
    <button type="submit" class="submit-btn checkin-btn" id="submitBtn" disabled>
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 3l14 9-14 9V3z"/>
      </svg>
      <span id="submitLabel">Select your name above</span>
    </button>
  </form>

  <div style="font-size:.78rem;color:var(--dim);text-align:center;margin-top:-.25rem">
    <?= date('D, d F Y') ?> · <?= APP_NAME ?>
  </div>

</div><!-- /container -->
<?php endif; ?>

<!-- Footer -->
<div class="footer-note">
  <?= APP_NAME ?> · Attendance System<br>
  <?php if (!isset($pageError) && !$submitSuccess): ?>
    Scanned at <?= e($officeQR['label'] ?? 'Main Entrance') ?>
  <?php endif; ?>
</div>

<script>
// Live clock
(function() {
  const update = () => {
    const t = new Date().toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    const el1 = document.getElementById('headerClock');
    const el2 = document.getElementById('stripClock');
    if (el1) el1.textContent = t;
    if (el2) el2.textContent = t.slice(0,5); // HH:MM only
  };
  update(); setInterval(update, 1000);
})();

// ---- Action mode toggle ----------------------------------
function setAction(mode) {
  document.getElementById('actionField').value = mode;
  document.getElementById('btnIn').classList.toggle('active',  mode === 'checkin');
  document.getElementById('btnOut').classList.toggle('active', mode === 'checkout');

  const btn   = document.getElementById('submitBtn');
  const label = document.getElementById('submitLabel');
  const empId = document.getElementById('selectedEmpId').value;

  btn.className = 'submit-btn ' + (mode === 'checkin' ? 'checkin-btn' : 'checkout-btn');

  if (empId) {
    const name = document.querySelector('.emp-item.selected .emp-name')?.textContent || 'selected';
    label.textContent = mode === 'checkin'
      ? '▶  Check In as ' + name
      : '■  Check Out as ' + name;
    btn.disabled = false;
  } else {
    label.textContent = 'Select your name above';
    btn.disabled = true;
  }
}

// ---- Employee selection ----------------------------------
let selectedId = null;

function selectEmployee(el, empId) {
  // Deselect previous
  document.querySelectorAll('.emp-item.selected').forEach(e => e.classList.remove('selected'));

  el.classList.add('selected');
  selectedId = empId;
  document.getElementById('selectedEmpId').value = empId;

  const name   = el.querySelector('.emp-name').textContent;
  const mode   = document.getElementById('actionField').value;
  const btn    = document.getElementById('submitBtn');
  const label  = document.getElementById('submitLabel');

  btn.disabled = false;
  label.textContent = mode === 'checkin'
    ? '▶  Check In as ' + name
    : '■  Check Out as ' + name;

  // Show email section
  document.getElementById('emailSection').classList.add('visible');

  // Scroll button into view on mobile
  btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ---- Employee search filter ------------------------------
function filterEmployees(query) {
  const q     = query.toLowerCase().trim();
  const items = document.querySelectorAll('.emp-item');
  let   shown = 0;

  items.forEach(item => {
    const name = item.dataset.name || '';
    const show = !q || name.includes(q);
    item.style.display = show ? 'flex' : 'none';
    if (show) shown++;
  });

  // Empty state
  let empty = document.getElementById('empEmpty');
  if (!shown && q) {
    if (!empty) {
      empty = document.createElement('div');
      empty.id = 'empEmpty';
      empty.className = 'emp-empty';
      empty.textContent = 'No employee found for "' + query + '"';
      document.getElementById('empList').appendChild(empty);
    } else {
      empty.textContent = 'No employee found for "' + query + '"';
      empty.style.display = 'block';
    }
  } else if (empty) {
    empty.style.display = 'none';
  }
}

// ---- Prevent double-submit --------------------------------
document.getElementById('checkinForm')?.addEventListener('submit', function(e) {
  const btn = document.getElementById('submitBtn');
  if (!document.getElementById('selectedEmpId').value) {
    e.preventDefault();
    return;
  }
  btn.disabled = true;
  btn.textContent = 'Recording…';
});
</script>
</body>
</html>
