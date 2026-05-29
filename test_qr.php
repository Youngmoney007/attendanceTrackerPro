<?php
// =============================================================
// test_qr.php
// Quick test page to verify QR check-in/check-out works.
// Access: http://localhost/attendance_tracker/test_qr.php
// =============================================================

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');

$db = getDB();

// Fetch all employees with their tokens
$employees = $db->query("
    SELECT id, emp_code, full_name, qr_token 
    FROM employees 
    WHERE status = 'active' AND qr_token IS NOT NULL AND qr_token != ''
    ORDER BY id
")->fetchAll();

// Handle test scan
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $type  = $_POST['type'] ?? 'checkin';
    
    // Call the API internally
    $apiUrl = APP_URL . '/api/qr_scan.php';
    
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['token' => $token, 'type' => $type]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    
    if ($curlErr) {
        $testResult = ['error' => "cURL error: $curlErr"];
    } else {
        $testResult = json_decode($response, true);
        $testResult['_http_code'] = $httpCode;
        $testResult['_raw'] = $response;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>QR Scan Test</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a1a; color: #eee; padding: 2rem; max-width: 800px; margin: 0 auto; }
        h1 { color: #f0ff4b; }
        .card { background: #222; border: 1px solid #333; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .5rem .75rem; text-align: left; border-bottom: 1px solid #333; font-size: .9rem; }
        th { color: #999; font-size: .75rem; text-transform: uppercase; }
        .token { font-family: monospace; font-size: .75rem; color: #f0ff4b; }
        .btn { background: #f0ff4b; color: #000; border: none; padding: .5rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: .85rem; }
        .btn:hover { opacity: .85; }
        .btn-blue { background: #3b82f6; color: #fff; }
        .result { background: #1e3a1e; border: 1px solid #2d5a2d; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
        .result.error { background: #3a1e1e; border-color: #5a2d2d; }
        pre { font-size: .8rem; overflow-x: auto; white-space: pre-wrap; }
        select, input[type=text] { background: #333; border: 1px solid #555; color: #eee; padding: .5rem; border-radius: 6px; font-size: .9rem; }
        form { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
        a { color: #f0ff4b; }
    </style>
</head>
<body>
    <h1>🔍 QR Scan Test Tool</h1>
    <p style="color:#999;margin-bottom:1.5rem">
        Use this page to test the QR check-in/check-out API without a camera.<br>
        <a href="<?= APP_URL ?>/kiosk.php" target="_blank">Open Kiosk (camera)</a> · 
        <a href="<?= APP_URL ?>/admin/qr_scans.php" target="_blank">View Scan Log</a>
    </p>

    <?php if (empty($employees)): ?>
        <div class="card" style="border-color:#f44;color:#faa">
            <strong>⚠️ No employees with QR tokens found!</strong><br>
            Run <a href="<?= APP_URL ?>/setup_qr.php">setup_qr.php</a> first to generate tokens.
        </div>
    <?php else: ?>

    <!-- Employee tokens -->
    <div class="card">
        <h3 style="margin-top:0">Employee QR Tokens</h3>
        <table>
            <thead><tr><th>Code</th><th>Name</th><th>Token (full)</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr>
                    <td><?= htmlspecialchars($emp['emp_code']) ?></td>
                    <td><?= htmlspecialchars($emp['full_name']) ?></td>
                    <td class="token"><?= htmlspecialchars($emp['qr_token']) ?></td>
                    <td>
                        <form method="POST" style="display:inline;gap:.25rem">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($emp['qr_token']) ?>"/>
                            <button type="submit" name="type" value="checkin" class="btn">Check In</button>
                            <button type="submit" name="type" value="checkout" class="btn btn-blue">Check Out</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Manual test -->
    <div class="card">
        <h3 style="margin-top:0">Manual Token Test</h3>
        <form method="POST">
            <input type="text" name="token" placeholder="Paste any 64-char token…" style="flex:1;min-width:300px"/>
            <select name="type">
                <option value="checkin">Check In</option>
                <option value="checkout">Check Out</option>
            </select>
            <button type="submit" class="btn">Test Scan</button>
        </form>
    </div>

    <?php endif; ?>

    <!-- Result -->
    <?php if ($testResult !== null): ?>
    <div class="card">
        <h3 style="margin-top:0">API Response</h3>
        <div class="result <?= (!empty($testResult['success'])) ? '' : 'error' ?>">
            <?php if (isset($testResult['error'])): ?>
                <strong>❌ Error:</strong> <?= htmlspecialchars($testResult['error']) ?>
            <?php else: ?>
                <strong><?= $testResult['success'] ? '✅ Success' : '❌ Failed' ?>:</strong> 
                <?= htmlspecialchars($testResult['message'] ?? 'No message') ?>
                <pre><?= htmlspecialchars(json_encode($testResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card" style="border-color:#555">
        <h3 style="margin-top:0;color:#999">Troubleshooting</h3>
        <ul style="color:#999;font-size:.85rem;line-height:1.8">
            <li>If you see "cURL error" — the API URL might be wrong. Check APP_URL in <code>includes/config.php</code></li>
            <li>If tokens are empty — run <a href="<?= APP_URL ?>/setup_qr.php">setup_qr.php</a></li>
            <li>If "Unrecognised QR code" — the token doesn't match any employee's qr_token in the database</li>
            <li>Current APP_URL: <code><?= APP_URL ?></code></li>
            <li>API endpoint: <code><?= APP_URL ?>/api/qr_scan.php</code></li>
        </ul>
    </div>
</body>
</html>
