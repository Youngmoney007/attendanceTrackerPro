<?php
// =============================================================
// employee/notifications.php
// =============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$cu = currentUser();
$db = getDB();

// Mark all as read
$db->prepare("UPDATE notifications SET is_read=1 WHERE employee_id=?")->execute([$cu['id']]);

$stmt = $db->prepare("SELECT * FROM notifications WHERE employee_id=? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$cu['id']]);
$notifs = $stmt->fetchAll();

$pageTitle  = 'Notifications';
$activePage = 'notifications';
require_once __DIR__ . '/../includes/nav.php';
?>
<div class="table-card">
  <div class="table-card-header">
    <div class="table-card-title">All Notifications</div>
  </div>
  <div style="padding:.5rem 0">
  <?php if ($notifs): foreach ($notifs as $n): ?>
    <div style="display:flex;align-items:flex-start;gap:1rem;padding:.9rem 1.25rem;border-bottom:1px solid var(--border)">
      <div style="width:8px;height:8px;border-radius:50%;background:var(--accent);margin-top:6px;flex-shrink:0"></div>
      <div style="flex:1">
        <div style="font-size:.9rem"><?= e($n['message']) ?></div>
        <div class="text-xs text-dim" style="margin-top:.2rem"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
      </div>
      <?php if ($n['link']): ?>
        <a href="<?= e($n['link']) ?>" class="btn btn-ghost btn-sm">View →</a>
      <?php endif; ?>
    </div>
  <?php endforeach; else: ?>
    <div class="empty-state">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <p>No notifications yet.</p>
    </div>
  <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
