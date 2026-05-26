<?php
// =============================================================
// admin/office_qr.php
// Admin generates the single shared office QR code here.
// They can print it, download it, or regenerate it.
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();
$cu = currentUser();
$db = getDB();

$success = '';
$error   = '';

// ---- Handle regenerate / create --------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $label  = trim($_POST['label'] ?? 'Main Entrance');

    if ($action === 'generate') {
        // Deactivate all existing codes
        $db->exec("UPDATE office_qr_codes SET is_active = 0");

        // Create new one
        $newToken = bin2hex(random_bytes(32)); // 64-char secure random token
        $db->prepare("INSERT INTO office_qr_codes (qr_token, label, is_active, created_by) VALUES (?,?,1,?)")
           ->execute([$newToken, $label, $cu['id']]);

        $success = 'New QR code generated. Old QR codes are now invalid. Please reprint the new one.';
    }
}

// ---- Fetch active QR code ---------------------------------
$stmt   = $db->query("SELECT * FROM office_qr_codes WHERE is_active=1 ORDER BY created_at DESC LIMIT 1");
$active = $stmt->fetch();

// Auto-generate one if none exists yet
if (!$active) {
    $newToken = bin2hex(random_bytes(32));
    $db->prepare("INSERT INTO office_qr_codes (qr_token, label, is_active, created_by) VALUES (?,?,1,?)")
       ->execute([$newToken, 'Main Entrance', $cu['id']]);
    $active = $db->query("SELECT * FROM office_qr_codes WHERE is_active=1 LIMIT 1")->fetch();
    $success = 'First QR code generated automatically. Print it and post it at the entrance.';
}

// ---- Build the URL the QR encodes -------------------------
// This is the full URL employees' phones will open
$checkinUrl = APP_URL . '/checkin.php?t=' . urlencode($active['qr_token']);

// ---- Past codes (audit trail) ----------------------------
$history = $db->query("
    SELECT oq.*, e.full_name AS creator
    FROM office_qr_codes oq
    LEFT JOIN employees e ON e.id = oq.created_by
    ORDER BY oq.created_at DESC
    LIMIT 10
")->fetchAll();

// ---- Today's scan count ----------------------------------
$todayScans = (int)$db->query("
    SELECT COUNT(*) FROM qr_scans
    WHERE DATE(scanned_at) = CURDATE() AND scan_result = 'success'
")->fetchColumn();

$pageTitle  = 'Office QR Code';
$activePage = 'office_qr';
require_once __DIR__ . '/../includes/nav.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

  <!-- ===== LEFT: QR Display ===== -->
  <div>
    <div class="form-card" style="text-align:center;padding:2rem">

      <div style="margin-bottom:1.25rem">
        <h2 style="font-family:var(--ff-display);font-size:1.2rem;font-weight:800;margin-bottom:.3rem">
          <?= e($active['label']) ?> — Attendance QR
        </h2>
        <p class="text-dim text-sm">Post this at your office entrance. Employees scan it with their phones.</p>
      </div>

      <!-- QR rendered via qrcode.js -->
      <div id="qrWrapper" style="
        background:#fff;
        display:inline-block;
        padding:20px;
        border-radius:16px;
        margin-bottom:1.5rem;
        box-shadow: 0 0 0 1px rgba(255,255,255,.06), 0 8px 40px rgba(0,0,0,.6);
      ">
        <canvas id="qrCanvas"></canvas>
      </div>

      <!-- URL preview -->
      <div style="background:var(--bg3);border-radius:8px;padding:.65rem 1rem;margin-bottom:1.25rem;font-size:.75rem;color:var(--text-dim);word-break:break-all;text-align:left">
        <span style="color:var(--text-xdim);display:block;margin-bottom:2px;font-size:.68rem;text-transform:uppercase;letter-spacing:.08em">Encoded URL</span>
        <span style="color:var(--accent)"><?= e($checkinUrl) ?></span>
      </div>

      <!-- Scan stats -->
      <div style="display:flex;gap:1rem;justify-content:center;margin-bottom:1.5rem">
        <div style="text-align:center">
          <div style="font-family:var(--ff-display);font-size:1.6rem;font-weight:700;color:var(--green)"><?= $todayScans ?></div>
          <div class="text-xs text-dim">Scans today</div>
        </div>
        <div style="width:1px;background:var(--border)"></div>
        <div style="text-align:center">
          <div style="font-family:var(--ff-display);font-size:1rem;font-weight:700;color:var(--text-dim)"><?= date('d M Y', strtotime($active['created_at'])) ?></div>
          <div class="text-xs text-dim">Generated</div>
        </div>
      </div>

      <!-- Action buttons -->
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
        <button onclick="downloadQR()" class="btn btn-primary">⬇ Download PNG</button>
        <button onclick="printQR()"    class="btn btn-ghost">🖨 Print Poster</button>
        <a href="<?= e($checkinUrl) ?>" target="_blank" class="btn btn-ghost">📱 Preview on Phone</a>
      </div>
    </div>

    <!-- Regenerate form -->
    <div class="form-card">
      <div class="form-card-title">Generate New QR Code</div>
      <p class="text-sm text-dim" style="margin-bottom:1rem">
        Regenerate if the code is compromised or you want a fresh one.
        <strong style="color:var(--red)">The old printed QR will stop working immediately.</strong>
      </p>
      <form method="POST" onsubmit="return confirm('This will invalidate the current printed QR code. Continue?')">
        <input type="hidden" name="action" value="generate"/>
        <div class="form-group-plain">
          <label>Location Label</label>
          <input type="text" name="label" value="<?= e($active['label']) ?>" placeholder="e.g. Main Entrance, Floor 2…"/>
        </div>
        <button type="submit" class="btn btn-danger w-full" style="margin-top:.75rem">
          ↻ Regenerate QR Code
        </button>
      </form>
    </div>
  </div><!-- /left -->

  <!-- ===== RIGHT: Instructions + History ===== -->
  <div style="display:flex;flex-direction:column;gap:1rem">

    <!-- How it works -->
    <div class="form-card">
      <div class="form-card-title">How the Shared QR System Works</div>
      <div style="display:flex;flex-direction:column;gap:1rem">

        <div style="display:flex;gap:.75rem">
          <div style="width:30px;height:30px;background:rgba(240,255,75,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700;font-size:.8rem;flex-shrink:0">1</div>
          <div>
            <div class="text-sm font-bold">Print and post the QR</div>
            <div class="text-sm text-dim">Download or print the QR code and stick it at the office entrance, reception, or time clock area.</div>
          </div>
        </div>

        <div style="display:flex;gap:.75rem">
          <div style="width:30px;height:30px;background:rgba(240,255,75,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700;font-size:.8rem;flex-shrink:0">2</div>
          <div>
            <div class="text-sm font-bold">Employee scans with phone camera</div>
            <div class="text-sm text-dim">No app needed — the phone's built-in camera reads the QR and opens a link automatically.</div>
          </div>
        </div>

        <div style="display:flex;gap:.75rem">
          <div style="width:30px;height:30px;background:rgba(240,255,75,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700;font-size:.8rem;flex-shrink:0">3</div>
          <div>
            <div class="text-sm font-bold">They tap their name</div>
            <div class="text-sm text-dim">A mobile page opens showing all employees. They search or scroll to find their name and tap it.</div>
          </div>
        </div>

        <div style="display:flex;gap:.75rem">
          <div style="width:30px;height:30px;background:rgba(240,255,75,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700;font-size:.8rem;flex-shrink:0">4</div>
          <div>
            <div class="text-sm font-bold">Optional email confirmation</div>
            <div class="text-sm text-dim">Employees can enter their work email to verify identity, preventing someone else from checking them in.</div>
          </div>
        </div>

        <div style="display:flex;gap:.75rem">
          <div style="width:30px;height:30px;background:rgba(34,197,94,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--green);font-weight:700;font-size:.9rem;flex-shrink:0">✓</div>
          <div>
            <div class="text-sm font-bold">Attendance is recorded instantly</div>
            <div class="text-sm text-dim">Check-in time, late status, and hours are calculated automatically and appear in the admin dashboard.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- QR History -->
    <div class="table-card" style="margin:0">
      <div class="table-card-header">
        <div class="table-card-title">QR Code History</div>
        <a href="<?= APP_URL ?>/admin/qr_scans.php" class="btn btn-ghost btn-sm">View Scan Log →</a>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Label</th><th>Created</th><th>By</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($history as $h): ?>
            <tr>
              <td class="text-sm"><?= e($h['label']) ?></td>
              <td class="text-xs text-dim"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
              <td class="text-xs text-dim"><?= e($h['creator'] ?? 'System') ?></td>
              <td>
                <?php if ($h['is_active']): ?>
                  <span class="badge badge-present">Active</span>
                <?php else: ?>
                  <span class="badge badge-absent">Expired</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /right -->
</div><!-- /grid -->

<!-- qrcode.js -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<script>
const CHECKIN_URL  = <?= json_encode($checkinUrl) ?>;
const OFFICE_LABEL = <?= json_encode($active['label']) ?>;
const APP_NAME     = <?= json_encode(APP_NAME) ?>;

// Generate QR on canvas (large – 300px for crisp print)
QRCode.toCanvas(
  document.getElementById('qrCanvas'),
  CHECKIN_URL,
  {
    width: 300,
    margin: 2,
    color: { dark: '#000000', light: '#ffffff' },
    errorCorrectionLevel: 'H',
  },
  err => { if (err) console.error('QR error:', err); }
);

// ---- Download as branded PNG poster ----------------------
function downloadQR() {
  const src = document.getElementById('qrCanvas');
  const out = document.createElement('canvas');
  out.width  = 400;
  out.height = 520;
  const ctx  = out.getContext('2d');

  // Background
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, 400, 520);

  // Accent top bar
  ctx.fillStyle = '#f0ff4b';
  ctx.fillRect(0, 0, 400, 8);

  // QR
  ctx.drawImage(src, 50, 40, 300, 300);

  // Divider
  ctx.fillStyle = '#e5e5e5';
  ctx.fillRect(40, 360, 320, 1);

  // App name
  ctx.fillStyle = '#0a0a0a';
  ctx.font = 'bold 22px "Helvetica Neue", Arial, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText(APP_NAME, 200, 395);

  // Instruction
  ctx.fillStyle = '#555';
  ctx.font = '15px "Helvetica Neue", Arial, sans-serif';
  ctx.fillText('Scan to Check In / Check Out', 200, 422);

  // Location label
  ctx.fillStyle = '#aaa';
  ctx.font = '12px "Helvetica Neue", Arial, sans-serif';
  ctx.fillText(OFFICE_LABEL, 200, 448);

  // Arrow hint
  ctx.fillStyle = '#f0ff4b';
  ctx.fillRect(140, 468, 120, 36);
  ctx.fillStyle = '#0a0a0a';
  ctx.font = 'bold 13px "Helvetica Neue", Arial, sans-serif';
  ctx.fillText('📷  Point camera here', 200, 491);

  // Bottom border
  ctx.fillStyle = '#0a0a0a';
  ctx.fillRect(0, 512, 400, 8);

  const link = document.createElement('a');
  link.download = 'AttendTrack_Office_QR.png';
  link.href = out.toDataURL('image/png');
  link.click();
}

// ---- Print a full poster page ----------------------------
function printQR() {
  const src = document.getElementById('qrCanvas').toDataURL('image/png');
  const w   = window.open('', '_blank', 'width=600,height=800');
  w.document.write(`<!DOCTYPE html>
<html><head>
<title>AttendTrack QR Poster – ${OFFICE_LABEL}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;600&display=swap');
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'DM Sans', Arial, sans-serif;
    background: #fff;
    display: flex; align-items: center; justify-content: center;
    min-height: 100vh; padding: 20px;
  }
  .poster {
    width: 380px;
    border: 2px solid #0a0a0a;
    border-radius: 20px;
    overflow: hidden;
    text-align: center;
    box-shadow: 0 4px 40px rgba(0,0,0,.15);
  }
  .poster-top {
    background: #f0ff4b;
    padding: 22px 20px 18px;
  }
  .poster-top h1 {
    font-family: 'Syne', sans-serif;
    font-size: 1.6rem;
    font-weight: 800;
    color: #0a0a0a;
    margin-bottom: 4px;
  }
  .poster-top p {
    font-size: .85rem;
    color: #333;
  }
  .qr-wrap {
    background: #fff;
    padding: 28px;
  }
  .qr-wrap img { width: 280px; height: 280px; display: block; margin: 0 auto; }
  .poster-body {
    background: #0a0a0a;
    color: #f0f0f0;
    padding: 20px;
  }
  .poster-body h2 {
    font-family: 'Syne', sans-serif;
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 14px;
    color: #f0ff4b;
  }
  .steps { text-align: left; list-style: none; }
  .steps li {
    display: flex; gap: 10px; align-items: flex-start;
    margin-bottom: 10px; font-size: .85rem; color: #bbb;
  }
  .steps li span {
    width: 22px; height: 22px; border-radius: 50%;
    background: rgba(240,255,75,.15); color: #f0ff4b;
    font-weight: 700; font-size: .75rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .poster-footer {
    background: #141414;
    padding: 12px;
    font-size: .72rem;
    color: #555;
    border-top: 1px solid #222;
  }
  @media print {
    body { margin: 0; padding: 0; min-height: unset; }
    .poster { box-shadow: none; border: 2px solid #0a0a0a; }
  }
</style>
</head>
<body>
<div class="poster">
  <div class="poster-top">
    <h1>${APP_NAME}</h1>
    <p>${OFFICE_LABEL} · Attendance Check-In</p>
  </div>
  <div class="qr-wrap">
    <img src="${src}" alt="Attendance QR Code"/>
  </div>
  <div class="poster-body">
    <h2>📷 How to Check In</h2>
    <ol class="steps">
      <li><span>1</span><div>Open your phone camera and point it at the QR code above</div></li>
      <li><span>2</span><div>Tap the link that pops up on your screen</div></li>
      <li><span>3</span><div>Find your name in the list and tap it</div></li>
      <li><span>4</span><div>Tap <strong style="color:#f0ff4b">Check In</strong> or <strong style="color:#93c5fd">Check Out</strong></div></li>
    </ol>
  </div>
  <div class="poster-footer">${APP_NAME} · Attendance Tracking System</div>
</div>
<script>window.onload=()=>{window.print();}<\/script>
</body></html>`);
  w.document.close();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
