<?php
// =============================================================
// employee/my_qr.php
// Shows the employee's personal QR code card for kiosk scanning.
// Also handles token regeneration if requested.
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$cu = currentUser();
$db = getDB();

// ---- Regenerate token (if employee requests) ---------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate') {
    $newToken = bin2hex(random_bytes(32)); // 64-char hex token
    $db->prepare("UPDATE employees SET qr_token=? WHERE id=?")->execute([$newToken, $cu['id']]);
    redirect(APP_URL . '/employee/my_qr.php?regenerated=1');
}

// ---- Fetch current token -----------------------------------
$stmt = $db->prepare("SELECT qr_token, full_name, emp_code, department, position FROM employees WHERE id=? LIMIT 1");
$stmt->execute([$cu['id']]);
$emp = $stmt->fetch();

// Auto-generate token if missing (first visit)
if (!$emp['qr_token']) {
    $newToken = bin2hex(random_bytes(32));
    $db->prepare("UPDATE employees SET qr_token=? WHERE id=?")->execute([$newToken, $cu['id']]);
    $emp['qr_token'] = $newToken;
}

$token   = $emp['qr_token'];
$regenerated = isset($_GET['regenerated']);

$pageTitle  = 'My QR Code';
$activePage = 'qr';
require_once __DIR__ . '/../includes/nav.php';
?>

<?php if ($regenerated): ?>
  <div class="alert alert-success">✓ Your QR code has been regenerated. Old QR codes are now invalid.</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;max-width:860px">

  <!-- ===== QR Card ===== -->
  <div class="form-card" style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:1.5rem;padding:2rem">

    <div>
      <h2 style="font-family:var(--ff-display);font-size:1.3rem;font-weight:800;margin-bottom:.3rem">
        My Check-In QR Code
      </h2>
      <p class="text-dim text-sm">Hold this up to any AttendTrack kiosk camera to check in or out.</p>
    </div>

    <!-- QR Code rendered by qrcode.js via canvas, then shown as img -->
    <div id="qrWrapper" style="
      background:#fff;
      border-radius:16px;
      padding:20px;
      display:inline-block;
      box-shadow:0 0 0 1px rgba(255,255,255,.08), 0 8px 32px rgba(0,0,0,.5)
    ">
      <canvas id="qrCanvas"></canvas>
    </div>

    <!-- Employee identity strip -->
    <div style="
      background:var(--bg3);
      border:1px solid var(--border);
      border-radius:var(--radius);
      padding:1rem 1.5rem;
      width:100%;
    ">
      <div style="font-family:var(--ff-display);font-size:1.1rem;font-weight:700"><?= e($emp['full_name']) ?></div>
      <div class="text-sm text-dim" style="margin-top:.2rem">
        <?= e($emp['emp_code']) ?>
        <?php if ($emp['department']): ?> · <?= e($emp['department']) ?><?php endif; ?>
        <?php if ($emp['position']):   ?> · <?= e($emp['position']) ?><?php endif; ?>
      </div>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center">
      <button onclick="downloadQR()" class="btn btn-primary">⬇ Download Card</button>
      <button onclick="printQR()"    class="btn btn-ghost">🖨 Print</button>
    </div>

  </div><!-- /qr card -->

  <!-- ===== Instructions + Regenerate ===== -->
  <div style="display:flex;flex-direction:column;gap:1rem">

    <div class="form-card">
      <div class="form-card-title">How to Use Your QR Code</div>
      <div style="display:flex;flex-direction:column;gap:1rem">

        <div style="display:flex;gap:.75rem;align-items:flex-start">
          <div style="width:32px;height:32px;background:rgba(240,255,75,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700;flex-shrink:0">1</div>
          <div>
            <div class="text-sm font-bold">Go to the kiosk</div>
            <div class="text-sm text-dim">Locate the AttendTrack kiosk screen at the entrance.</div>
          </div>
        </div>

        <div style="display:flex;gap:.75rem;align-items:flex-start">
          <div style="width:32px;height:32px;background:rgba(240,255,75,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700;flex-shrink:0">2</div>
          <div>
            <div class="text-sm font-bold">Select Check In or Check Out</div>
            <div class="text-sm text-dim">Tap the correct mode on the kiosk touchscreen.</div>
          </div>
        </div>

        <div style="display:flex;gap:.75rem;align-items:flex-start">
          <div style="width:32px;height:32px;background:rgba(240,255,75,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700;flex-shrink:0">3</div>
          <div>
            <div class="text-sm font-bold">Show your QR to the camera</div>
            <div class="text-sm text-dim">Hold your phone screen or printed card steady in the scan frame. The system confirms within 2 seconds.</div>
          </div>
        </div>

        <div style="display:flex;gap:.75rem;align-items:flex-start">
          <div style="width:32px;height:32px;background:rgba(34,197,94,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--green);font-weight:700;flex-shrink:0">✓</div>
          <div>
            <div class="text-sm font-bold">Done!</div>
            <div class="text-sm text-dim">A green screen confirms your attendance is recorded. Your dashboard updates instantly.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Token info -->
    <div class="form-card">
      <div class="form-card-title">Security</div>
      <p class="text-sm text-dim" style="margin-bottom:1rem">
        Your QR code contains a unique secure token tied only to your account.
        If you think your QR code has been compromised, regenerate it below — your old code will immediately stop working.
      </p>
      <div style="background:var(--bg3);border-radius:8px;padding:.75rem;margin-bottom:1rem">
        <div class="text-xs text-dim" style="margin-bottom:.3rem">Token ID (first 16 chars)</div>
        <code style="font-size:.82rem;color:var(--accent)"><?= e(substr($token, 0, 16)) ?>…</code>
      </div>
      <form method="POST" onsubmit="return confirm('Are you sure? Your current QR code will stop working immediately.')">
        <input type="hidden" name="action" value="regenerate"/>
        <button type="submit" class="btn btn-danger w-full">↻ Regenerate QR Code</button>
      </form>
    </div>

    <!-- Kiosk link (admin can share this URL) -->
    <div class="form-card">
      <div class="form-card-title">Kiosk Access</div>
      <p class="text-sm text-dim" style="margin-bottom:.75rem">Share this link to open the kiosk on any screen or tablet:</p>
      <div style="display:flex;gap:.5rem;align-items:center">
        <code style="flex:1;background:var(--bg3);padding:.6rem .8rem;border-radius:8px;font-size:.78rem;color:var(--accent);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= APP_URL ?>/kiosk.php
        </code>
        <button onclick="navigator.clipboard.writeText('<?= APP_URL ?>/kiosk.php');showToast('Copied!','success')"
                class="btn btn-ghost btn-sm">Copy</button>
      </div>
    </div>

  </div><!-- /right col -->
</div><!-- /grid -->

<!-- qrcode.js: tiny pure-JS QR generator, no server-side lib needed -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<script>
const TOKEN   = <?= json_encode($token) ?>;
const EMP_NAME = <?= json_encode($emp['full_name']) ?>;
const EMP_CODE = <?= json_encode($emp['emp_code']) ?>;

// Generate QR code on canvas
QRCode.toCanvas(
  document.getElementById('qrCanvas'),
  TOKEN,
  {
    width:        260,
    margin:       2,
    color: { dark: '#0a0a0a', light: '#ffffff' },
    errorCorrectionLevel: 'H',  // High – better scan reliability
  },
  err => { if (err) console.error('QR error:', err); }
);

// ---- Download as PNG card --------------------------------
function downloadQR() {
  const src = document.getElementById('qrCanvas');
  const out = document.createElement('canvas');
  out.width  = 320;
  out.height = 400;
  const ctx  = out.getContext('2d');

  // Background
  ctx.fillStyle = '#0d0d0d';
  ctx.roundRect(0, 0, 320, 400, 16);
  ctx.fill();

  // Accent top strip
  ctx.fillStyle = '#f0ff4b';
  ctx.roundRect(0, 0, 320, 6, [16, 16, 0, 0]);
  ctx.fill();

  // QR code (white bg)
  ctx.fillStyle = '#fff';
  ctx.roundRect(30, 30, 260, 260, 12);
  ctx.fill();
  ctx.drawImage(src, 30, 30, 260, 260);

  // Name
  ctx.fillStyle = '#e8e8e8';
  ctx.font = 'bold 22px "Helvetica Neue", Arial, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText(EMP_NAME, 160, 330);

  // Code
  ctx.fillStyle = '#888';
  ctx.font = '14px "Helvetica Neue", Arial, sans-serif';
  ctx.fillText(EMP_CODE + ' · AttendTrack Pro', 160, 355);

  // Bottom label
  ctx.fillStyle = '#f0ff4b';
  ctx.font = 'bold 11px "Helvetica Neue", Arial, sans-serif';
  ctx.fillText('ATTENDANCE QR CODE', 160, 385);

  // Download
  const link = document.createElement('a');
  link.download = `AttendTrack_QR_${EMP_CODE}.png`;
  link.href = out.toDataURL('image/png');
  link.click();
}

// ---- Print -----------------------------------------------
function printQR() {
  const src = document.getElementById('qrCanvas').toDataURL('image/png');
  const w   = window.open('', '_blank');
  w.document.write(`
    <html><head><title>QR Card – ${EMP_NAME}</title>
    <style>
      body { margin:0; display:flex; align-items:center; justify-content:center; min-height:100vh; background:#fff; font-family:Arial,sans-serif; }
      .card { text-align:center; border:2px solid #0a0a0a; border-radius:12px; padding:30px 24px; width:280px; }
      .card img { width:220px; height:220px; display:block; margin:0 auto 16px; }
      .card h2 { font-size:18px; margin:0 0 4px; }
      .card p  { font-size:12px; color:#555; margin:0 0 12px; }
      .card .label { font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:#aaa; border-top:1px solid #eee; padding-top:8px; margin-top:8px; }
      @media print { body { margin:0; } }
    </style></head>
    <body>
      <div class="card">
        <img src="${src}" alt="QR Code"/>
        <h2>${EMP_NAME}</h2>
        <p>${EMP_CODE}</p>
        <div class="label">AttendTrack Pro – Attendance QR Code</div>
      </div>
      <script>window.onload=()=>{window.print();window.close()}<\/script>
    </body></html>
  `);
  w.document.close();
}
</script>

<!-- Print style for this page -->
<style>
@media print {
  .sidebar, .topbar, .form-card:last-child, .btn-danger { display: none !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
