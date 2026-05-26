<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AttendTrack – QR Check-In Kiosk</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <!-- jsQR: lightweight QR decoder that works from a webcam stream -->
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
  <style>
    /* =========================================================
       KIOSK PAGE – Fullscreen, dark, high-contrast for lobby use
       ========================================================= */
    :root {
      --accent:   #f0ff4b;
      --bg:       #0a0a0a;
      --bg2:      #141414;
      --surface:  #1c1c1c;
      --border:   #2a2a2a;
      --text:     #f0f0f0;
      --dim:      #777;
      --green:    #22c55e;
      --red:      #ef4444;
      --amber:    #f59e0b;
      --blue:     #3b82f6;
      --r:        16px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      height: 100%; background: var(--bg);
      font-family: 'DM Sans', sans-serif; color: var(--text);
      -webkit-font-smoothing: antialiased; overflow: hidden;
    }

    /* ---- Full-page layout ---------------------------------- */
    .kiosk {
      display: grid;
      grid-template-columns: 1fr 420px;
      height: 100vh;
    }

    /* ---- Camera side --------------------------------------- */
    .camera-side {
      position: relative;
      background: #000;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    #video {
      width: 100%; height: 100%;
      object-fit: cover;
      transform: scaleX(-1); /* mirror for natural feel */
    }
    #canvas { display: none; } /* off-screen decode canvas */

    /* Scan overlay frame */
    .scan-frame {
      position: absolute;
      width: 280px; height: 280px;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
    }
    .scan-frame::before,
    .scan-frame::after {
      content: '';
      position: absolute;
      width: 50px; height: 50px;
      border-color: var(--accent);
      border-style: solid;
    }
    .scan-frame::before { top: 0; left: 0; border-width: 4px 0 0 4px; border-radius: 8px 0 0 0; }
    .scan-frame::after  { bottom: 0; right: 0; border-width: 0 4px 4px 0; border-radius: 0 0 8px 0; }
    .scan-frame .corner-tr {
      position: absolute; top: 0; right: 0;
      width: 50px; height: 50px;
      border-top: 4px solid var(--accent);
      border-right: 4px solid var(--accent);
      border-radius: 0 8px 0 0;
    }
    .scan-frame .corner-bl {
      position: absolute; bottom: 0; left: 0;
      width: 50px; height: 50px;
      border-bottom: 4px solid var(--accent);
      border-left: 4px solid var(--accent);
      border-radius: 0 0 0 8px;
    }

    /* Scanning beam animation */
    .scan-beam {
      position: absolute;
      left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, transparent, var(--accent), transparent);
      top: 0;
      animation: scanBeam 2s ease-in-out infinite;
      box-shadow: 0 0 12px var(--accent);
    }
    @keyframes scanBeam {
      0%   { top: 0; opacity: 1; }
      50%  { top: calc(100% - 3px); opacity: 1; }
      100% { top: 0; opacity: 1; }
    }

    /* Camera status banner */
    .cam-status {
      position: absolute;
      bottom: 24px; left: 50%; transform: translateX(-50%);
      background: rgba(0,0,0,.7);
      border: 1px solid var(--border);
      border-radius: 30px;
      padding: 8px 20px;
      font-size: .82rem; color: var(--dim);
      backdrop-filter: blur(8px);
      white-space: nowrap;
    }
    .cam-status.active { color: var(--green); border-color: rgba(34,197,94,.3); }
    .cam-status.error  { color: var(--red);   border-color: rgba(239,68,68,.3);  }

    /* Mode toggle (check-in / check-out) */
    .mode-toggle {
      position: absolute;
      top: 20px; left: 50%; transform: translateX(-50%);
      display: flex;
      background: rgba(0,0,0,.7);
      border: 1px solid var(--border);
      border-radius: 30px;
      padding: 4px;
      gap: 4px;
      backdrop-filter: blur(8px);
      z-index: 10;
    }
    .mode-btn {
      padding: 8px 22px;
      border-radius: 26px;
      border: none;
      background: transparent;
      color: var(--dim);
      font-size: .88rem;
      font-weight: 600;
      font-family: 'Syne', sans-serif;
      cursor: pointer;
      transition: all .2s;
    }
    .mode-btn.active {
      background: var(--accent);
      color: #0a0a0a;
    }
    .mode-btn:not(.active):hover { color: var(--text); }

    /* ---- Info panel ---------------------------------------- */
    .info-side {
      background: var(--bg2);
      border-left: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      padding: 2rem 1.75rem;
      gap: 1.5rem;
      overflow-y: auto;
    }

    /* Branding */
    .kiosk-brand {
      display: flex; align-items: center; gap: 12px;
    }
    .kiosk-logo {
      width: 44px; height: 44px;
      background: var(--accent);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .kiosk-logo svg { width: 26px; height: 26px; }
    .kiosk-brand-name {
      font-family: 'Syne', sans-serif;
      font-size: 1.1rem;
      font-weight: 800;
    }
    .kiosk-brand-sub { font-size: .75rem; color: var(--dim); }

    /* Clock */
    .kiosk-clock {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 1.5rem;
      text-align: center;
    }
    .clock-time {
      font-family: 'Syne', sans-serif;
      font-size: 3.5rem;
      font-weight: 800;
      line-height: 1;
      letter-spacing: -.04em;
    }
    .clock-date { font-size: .88rem; color: var(--dim); margin-top: .4rem; }

    /* Instructions */
    .instructions {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 1.25rem;
    }
    .inst-title {
      font-family: 'Syne', sans-serif;
      font-size: .9rem;
      font-weight: 700;
      margin-bottom: 1rem;
      color: var(--dim);
      text-transform: uppercase;
      letter-spacing: .08em;
    }
    .inst-step {
      display: flex; align-items: flex-start; gap: .75rem;
      margin-bottom: .85rem;
      font-size: .88rem;
    }
    .inst-step:last-child { margin-bottom: 0; }
    .inst-num {
      width: 24px; height: 24px;
      border-radius: 50%;
      background: rgba(240,255,75,.12);
      color: var(--accent);
      font-size: .72rem;
      font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      margin-top: 1px;
    }
    .inst-text { color: var(--dim); line-height: 1.5; }

    /* ---- Result card --------------------------------------- */
    .result-card {
      border-radius: var(--r);
      padding: 1.5rem;
      border: 1px solid var(--border);
      background: var(--surface);
      transition: all .3s;
      display: none;
    }
    .result-card.visible { display: block; animation: popIn .3s cubic-bezier(.34,1.56,.64,1); }
    @keyframes popIn {
      from { opacity: 0; transform: scale(.95) translateY(8px); }
      to   { opacity: 1; transform: none; }
    }
    .result-card.success { border-color: rgba(34,197,94,.4);  background: rgba(34,197,94,.07);  }
    .result-card.error   { border-color: rgba(239,68,68,.4);  background: rgba(239,68,68,.07);  }
    .result-card.warning { border-color: rgba(245,158,11,.4); background: rgba(245,158,11,.07); }
    .result-card.info    { border-color: rgba(59,130,246,.4); background: rgba(59,130,246,.07); }

    .result-header { display: flex; align-items: center; gap: .85rem; margin-bottom: 1rem; }
    .result-avatar {
      width: 52px; height: 52px;
      border-radius: 50%;
      background: var(--accent);
      color: #0a0a0a;
      font-family: 'Syne', sans-serif;
      font-size: 1.3rem;
      font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .result-avatar.error-av { background: rgba(239,68,68,.2); color: var(--red); }
    .result-name   { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; }
    .result-sub    { font-size: .8rem; color: var(--dim); margin-top: 2px; }
    .result-msg    { font-size: .9rem; line-height: 1.6; }
    .result-time   {
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid rgba(255,255,255,.06);
      display: flex; gap: 1.5rem;
    }
    .result-time-item { display: flex; flex-direction: column; gap: 2px; }
    .result-time-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; color: var(--dim); }
    .result-time-val   { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 700; }

    /* Big status icon */
    .result-icon {
      font-size: 2.5rem;
      margin-bottom: .75rem;
      display: block;
    }

    /* Recent scans list */
    .recent-scans {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 1.25rem;
      flex: 1;
      overflow: hidden;
    }
    .rs-title {
      font-family: 'Syne', sans-serif;
      font-size: .9rem;
      font-weight: 700;
      margin-bottom: .85rem;
      color: var(--dim);
      text-transform: uppercase;
      letter-spacing: .08em;
    }
    .scan-log { display: flex; flex-direction: column; gap: .5rem; }
    .scan-log-item {
      display: flex; align-items: center; gap: .65rem;
      padding: .5rem .6rem;
      border-radius: 8px;
      background: rgba(255,255,255,.03);
      font-size: .82rem;
      animation: slideIn .25s ease;
    }
    @keyframes slideIn {
      from { opacity: 0; transform: translateX(-10px); }
      to   { opacity: 1; transform: none; }
    }
    .sli-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .sli-dot.in  { background: var(--green); }
    .sli-dot.out { background: var(--blue);  }
    .sli-dot.err { background: var(--red);   }
    .sli-name  { font-weight: 600; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sli-type  { color: var(--dim); font-size: .75rem; }
    .sli-time  { color: var(--dim); font-size: .75rem; white-space: nowrap; }

    /* Manual token input (fallback / testing) */
    .manual-input {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 1.25rem;
    }
    .manual-input label {
      font-size: .75rem; text-transform: uppercase; letter-spacing: .08em;
      color: var(--dim); display: block; margin-bottom: .5rem;
    }
    .manual-row { display: flex; gap: .5rem; }
    .manual-row input {
      flex: 1;
      background: var(--bg);
      border: 1.5px solid var(--border);
      border-radius: 8px;
      padding: .55rem .75rem;
      color: var(--text);
      font-size: .85rem;
    }
    .manual-row input:focus { outline: none; border-color: var(--accent); }
    .manual-row button {
      background: var(--accent);
      color: #0a0a0a;
      border: none;
      border-radius: 8px;
      padding: .55rem 1rem;
      font-size: .85rem;
      font-weight: 700;
      cursor: pointer;
    }
    .manual-row button:hover { opacity: .88; }

    /* Sound indicator */
    .sound-indicator {
      position: fixed; top: 20px; right: 20px;
      background: rgba(0,0,0,.6);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 6px 12px;
      font-size: .75rem; color: var(--dim);
      backdrop-filter: blur(6px);
      cursor: pointer;
    }
    .sound-indicator:hover { color: var(--text); }

    /* Responsive: on smaller/tablet screens stack vertically */
    @media (max-width: 800px) {
      .kiosk { grid-template-columns: 1fr; grid-template-rows: 60vh 1fr; overflow-y: auto; }
      .info-side { overflow: visible; }
      .camera-side { min-height: 60vh; }
    }
  </style>
</head>
<body>

<div class="kiosk">

  <!-- ===== CAMERA SIDE ===== -->
  <div class="camera-side">

    <!-- Mode toggle -->
    <div class="mode-toggle">
      <button class="mode-btn active" id="btnCheckin"  onclick="setMode('checkin')">▶ Check In</button>
      <button class="mode-btn"        id="btnCheckout" onclick="setMode('checkout')">■ Check Out</button>
    </div>

    <!-- Video stream -->
    <video id="video" playsinline autoplay muted></video>
    <canvas id="canvas"></canvas>

    <!-- Scan frame overlay -->
    <div class="scan-frame">
      <div class="corner-tr"></div>
      <div class="corner-bl"></div>
      <div class="scan-beam"></div>
    </div>

    <!-- Camera status -->
    <div class="cam-status" id="camStatus">Initialising camera…</div>

  </div><!-- /camera-side -->

  <!-- ===== INFO PANEL ===== -->
  <div class="info-side">

    <!-- Brand -->
    <div class="kiosk-brand">
      <div class="kiosk-logo">
        <svg viewBox="0 0 26 26" fill="none">
          <circle cx="13" cy="13" r="11" stroke="#0a0a0a" stroke-width="2.5"/>
          <path d="M13 8v5l3 2" stroke="#0a0a0a" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </div>
      <div>
        <div class="kiosk-brand-name">AttendTrack Pro</div>
        <div class="kiosk-brand-sub">QR Check-In Kiosk</div>
      </div>
    </div>

    <!-- Clock -->
    <div class="kiosk-clock">
      <div class="clock-time" id="kioskTime">--:--:--</div>
      <div class="clock-date" id="kioskDate"></div>
    </div>

    <!-- Result card (hidden until scan) -->
    <div class="result-card" id="resultCard">
      <span class="result-icon" id="resultIcon">✓</span>
      <div class="result-header">
        <div class="result-avatar" id="resultAvatar">?</div>
        <div>
          <div class="result-name" id="resultName">–</div>
          <div class="result-sub"  id="resultSub">–</div>
        </div>
      </div>
      <div class="result-msg" id="resultMsg"></div>
      <div class="result-time" id="resultTimeRow" style="display:none">
        <div class="result-time-item">
          <span class="result-time-label" id="rtLabel1">Time</span>
          <span class="result-time-val"   id="rtVal1">–</span>
        </div>
        <div class="result-time-item" id="rtItem2" style="display:none">
          <span class="result-time-label">Total Hours</span>
          <span class="result-time-val" id="rtVal2">–</span>
        </div>
      </div>
    </div>

    <!-- Instructions -->
    <div class="instructions">
      <div class="inst-title">How to Use</div>
      <div class="inst-step">
        <span class="inst-num">1</span>
        <span class="inst-text">Open your employee QR card (from your dashboard or printed card)</span>
      </div>
      <div class="inst-step">
        <span class="inst-num">2</span>
        <span class="inst-text">Select <strong>Check In</strong> or <strong>Check Out</strong> above</span>
      </div>
      <div class="inst-step">
        <span class="inst-num">3</span>
        <span class="inst-text">Hold your QR code up to the camera until it scans</span>
      </div>
      <div class="inst-step">
        <span class="inst-num">4</span>
        <span class="inst-text">Wait for the green confirmation screen</span>
      </div>
    </div>

    <!-- Recent scans log -->
    <div class="recent-scans">
      <div class="rs-title">Recent Activity</div>
      <div class="scan-log" id="scanLog">
        <div style="font-size:.82rem;color:var(--dim)">No scans yet today.</div>
      </div>
    </div>

    <!-- Manual entry fallback (useful for testing) -->
    <div class="manual-input">
      <label>Manual Token Entry (Testing / Fallback)</label>
      <div class="manual-row">
        <input type="text" id="manualToken" placeholder="Paste employee QR token…" />
        <button onclick="processManualToken()">Scan</button>
      </div>
    </div>

  </div><!-- /info-side -->
</div><!-- /kiosk -->

<!-- Sound toggle -->
<div class="sound-indicator" id="soundToggle" onclick="toggleSound()" title="Toggle sound feedback">
  🔊 Sound On
</div>

<script>
// =============================================================
// Kiosk JavaScript
// =============================================================

const API_URL   = 'api/qr_scan.php';  // Relative path – adjust if needed
let scanMode    = 'checkin';           // 'checkin' | 'checkout'
let lastToken   = '';
let lastScanTs  = 0;
const COOLDOWN  = 3000;               // ms between scans of same QR
let soundOn     = true;
let scanEnabled = true;               // Disable briefly after a hit

// ---- Audio feedback (Web Audio API) -----------------------
const AudioCtx = window.AudioContext || window.webkitAudioContext;
let audioCtx   = null;

function playBeep(type = 'success') {
  if (!soundOn) return;
  try {
    if (!audioCtx) audioCtx = new AudioCtx();
    const o = audioCtx.createOscillator();
    const g = audioCtx.createGain();
    o.connect(g); g.connect(audioCtx.destination);
    if (type === 'success') {
      o.frequency.value = 880; g.gain.value = .18;
      o.start(); o.stop(audioCtx.currentTime + .12);
      setTimeout(() => {
        const o2 = audioCtx.createOscillator();
        const g2 = audioCtx.createGain();
        o2.connect(g2); g2.connect(audioCtx.destination);
        o2.frequency.value = 1100; g2.gain.value = .18;
        o2.start(); o2.stop(audioCtx.currentTime + .18);
      }, 140);
    } else if (type === 'error') {
      o.frequency.value = 220; g.gain.value = .18;
      o.start(); o.stop(audioCtx.currentTime + .35);
    } else {
      o.frequency.value = 600; g.gain.value = .12;
      o.start(); o.stop(audioCtx.currentTime + .15);
    }
  } catch (_) {}
}

function toggleSound() {
  soundOn = !soundOn;
  document.getElementById('soundToggle').textContent = soundOn ? '🔊 Sound On' : '🔇 Sound Off';
}

// ---- Mode toggle ------------------------------------------
function setMode(mode) {
  scanMode = mode;
  document.getElementById('btnCheckin').classList.toggle('active',  mode === 'checkin');
  document.getElementById('btnCheckout').classList.toggle('active', mode === 'checkout');
  clearResult();
}

// ---- Clock ------------------------------------------------
function updateClock() {
  const d   = new Date();
  document.getElementById('kioskTime').textContent =
    d.toLocaleTimeString('en-GB', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
  document.getElementById('kioskDate').textContent =
    d.toLocaleDateString('en-GB', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
}
updateClock();
setInterval(updateClock, 1000);

// ---- Camera init ------------------------------------------
const video  = document.getElementById('video');
const canvas = document.getElementById('canvas');
const ctx    = canvas.getContext('2d');
const camSt  = document.getElementById('camStatus');

async function startCamera() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
    });
    video.srcObject = stream;
    video.play();
    camSt.textContent = '● Camera active – hold QR code in frame';
    camSt.className   = 'cam-status active';
    requestAnimationFrame(scanFrame);
  } catch (err) {
    camSt.textContent = '✕ Camera unavailable: ' + err.message;
    camSt.className   = 'cam-status error';
    console.error(err);
  }
}

// ---- QR scanning loop -------------------------------------
function scanFrame() {
  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;

    // Draw mirrored back (un-mirror for QR decode)
    ctx.save();
    ctx.scale(-1, 1);
    ctx.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);
    ctx.restore();

    if (scanEnabled) {
      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: 'dontInvert',
      });

      if (code) {
        const token = code.data.trim();
        const now   = Date.now();
        if (token && (token !== lastToken || now - lastScanTs > COOLDOWN)) {
          lastToken  = token;
          lastScanTs = now;
          handleScan(token);
        }
      }
    }
  }
  requestAnimationFrame(scanFrame);
}

// ---- Process a scanned token ------------------------------
async function handleScan(token) {
  scanEnabled = false;
  camSt.textContent = '⟳ Processing…';
  camSt.className   = 'cam-status';

  try {
    const res  = await fetch(API_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ token, type: scanMode }),
    });
    const data = await res.json();
    showResult(data);
  } catch (err) {
    showResult({ success: false, message: 'Network error. Please try again.', result: 'invalid' });
  }

  // Re-enable scanning after 3 s
  setTimeout(() => {
    scanEnabled = true;
    camSt.textContent = '● Camera active – hold QR code in frame';
    camSt.className   = 'cam-status active';
  }, 3000);
}

// ---- Manual token fallback --------------------------------
function processManualToken() {
  const token = document.getElementById('manualToken').value.trim();
  if (!token) return;
  handleScan(token);
  document.getElementById('manualToken').value = '';
}
document.getElementById('manualToken').addEventListener('keydown', e => {
  if (e.key === 'Enter') processManualToken();
});

// ---- Display result card ----------------------------------
function showResult(data) {
  const card     = document.getElementById('resultCard');
  const icon     = document.getElementById('resultIcon');
  const avatar   = document.getElementById('resultAvatar');
  const name     = document.getElementById('resultName');
  const sub      = document.getElementById('resultSub');
  const msg      = document.getElementById('resultMsg');
  const timeRow  = document.getElementById('resultTimeRow');
  const rtLabel1 = document.getElementById('rtLabel1');
  const rtVal1   = document.getElementById('rtVal1');
  const rtItem2  = document.getElementById('rtItem2');
  const rtVal2   = document.getElementById('rtVal2');

  card.className = 'result-card visible';
  msg.textContent = data.message || '';

  const emp    = data.employee;
  const result = data.result;

  // Avatar / name
  if (emp) {
    avatar.textContent = emp.initials || emp.full_name?.charAt(0) || '?';
    avatar.className   = 'result-avatar';
    name.textContent   = emp.full_name;
    sub.textContent    = [emp.emp_code, emp.department, emp.position].filter(Boolean).join(' · ');
  } else {
    avatar.textContent = '?';
    avatar.className   = 'result-avatar error-av';
    name.textContent   = 'Unknown';
    sub.textContent    = 'QR code not recognised';
  }

  // Status-specific styling
  if (!data.success || result === 'invalid') {
    card.classList.add('error');
    icon.textContent = '✕';
    playBeep('error');
  } else if (result === 'already_done') {
    card.classList.add('info');
    icon.textContent = 'ℹ';
    playBeep('info');
  } else {
    card.classList.add('success');
    icon.textContent = scanMode === 'checkin' ? '▶' : '■';
    playBeep('success');
  }

  // Time row
  if (data.time) {
    timeRow.style.display = 'flex';
    rtLabel1.textContent  = scanMode === 'checkin' ? 'Check-In Time' : 'Check-Out Time';
    rtVal1.textContent    = data.time;
    if (data.hours_fmt) {
      rtItem2.style.display = 'flex';
      rtVal2.textContent    = data.hours_fmt;
    } else {
      rtItem2.style.display = 'none';
    }
  } else {
    timeRow.style.display = 'none';
  }

  // Add to recent log
  if (emp) {
    addScanLog(emp.full_name, scanMode, result);
  } else {
    addScanLog('Unknown QR', scanMode, 'invalid');
  }

  // Auto-clear after 6 seconds
  setTimeout(clearResult, 6000);
}

function clearResult() {
  const card = document.getElementById('resultCard');
  card.className = 'result-card';
}

// ---- Recent scan log (in-memory, last 6) ------------------
const scanLogs = [];
function addScanLog(name, type, result) {
  const time = new Date().toLocaleTimeString('en-GB', { hour:'2-digit', minute:'2-digit' });
  scanLogs.unshift({ name, type, result, time });
  if (scanLogs.length > 6) scanLogs.pop();
  renderScanLog();
}

function renderScanLog() {
  const container = document.getElementById('scanLog');
  if (!scanLogs.length) {
    container.innerHTML = '<div style="font-size:.82rem;color:var(--dim)">No scans yet today.</div>';
    return;
  }
  container.innerHTML = scanLogs.map(s => {
    const dot = s.result === 'invalid' ? 'err' : s.type === 'checkin' ? 'in' : 'out';
    const typeLabel = s.type === 'checkin' ? 'Check In' : 'Check Out';
    return `<div class="scan-log-item">
      <span class="sli-dot ${dot}"></span>
      <span class="sli-name">${s.name}</span>
      <span class="sli-type">${typeLabel}</span>
      <span class="sli-time">${s.time}</span>
    </div>`;
  }).join('');
}

// ---- Boot --------------------------------------------------
startCamera();
</script>
</body>
</html>
