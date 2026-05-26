<?php
// =============================================================
// includes/nav.php
// Shared sidebar and topbar (included on every dashboard page)
// $pageTitle and $activePage must be set before including.
// =============================================================

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

startSession();
$cu      = currentUser();
$unread  = getUnreadNotifications($cu['id']);
$initials = strtoupper(substr($cu['full_name'], 0, 1) . substr(strrchr($cu['full_name'], ' '), 1, 1));
$isAdmin = ($cu['role'] === 'admin');
$dashUrl = $isAdmin ? APP_URL . '/admin/dashboard.php' : APP_URL . '/employee/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($pageTitle ?? 'Dashboard') ?> – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css"/>
  <!-- Chart.js for performance/attendance graphs -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div id="sidebar-overlay" class="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="app-layout">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">
        <svg viewBox="0 0 20 20" fill="none">
          <circle cx="10" cy="10" r="8" stroke="#0a0a0a" stroke-width="2"/>
          <path d="M10 6v4l2.5 1.5" stroke="#0a0a0a" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="sidebar-title">
        <?= APP_NAME ?>
        <small><?= $isAdmin ? 'Admin Panel' : 'Employee Portal' ?></small>
      </div>
    </div>

    <nav class="sidebar-nav">

      <!-- Common links -->
      <div class="nav-section">
        <div class="nav-section-label">Main</div>
        <a href="<?= $dashUrl ?>" class="nav-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          Dashboard
        </a>
        <a href="<?= APP_URL ?>/employee/attendance.php" class="nav-link <?= ($activePage ?? '') === 'attendance' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          Attendance
        </a>
        <a href="<?= APP_URL ?>/employee/leave.php" class="nav-link <?= ($activePage ?? '') === 'leave' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          Leave Requests
        </a>
        <a href="<?= APP_URL ?>/employee/performance.php" class="nav-link <?= ($activePage ?? '') === 'performance' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          Performance
        </a>
        <a href="<?= APP_URL ?>/employee/my_qr.php" class="nav-link <?= ($activePage ?? '') === 'qr' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8H3m2 8H2m10-2V8"/></svg>
          My QR Code
        </a>
        <a href="<?= APP_URL ?>/employee/notifications.php" class="nav-link <?= ($activePage ?? '') === 'notifications' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          Notifications
          <?php if ($unread > 0): ?>
            <span class="badge badge-absent" style="margin-left:auto;padding:2px 7px;"><?= $unread ?></span>
          <?php endif; ?>
        </a>
      </div>

      <?php if ($isAdmin): ?>
      <!-- Admin-only links -->
      <div class="nav-section">
        <div class="nav-section-label">Administration</div>
        <a href="<?= APP_URL ?>/admin/employees.php" class="nav-link <?= ($activePage ?? '') === 'employees' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          Employees
        </a>
        <a href="<?= APP_URL ?>/admin/attendance_overview.php" class="nav-link <?= ($activePage ?? '') === 'att_overview' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
          Attendance Overview
        </a>
        <a href="<?= APP_URL ?>/admin/leave_management.php" class="nav-link <?= ($activePage ?? '') === 'leave_mgmt' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Leave Management
        </a>
        <a href="<?= APP_URL ?>/admin/performance_admin.php" class="nav-link <?= ($activePage ?? '') === 'perf_admin' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
          KPI Management
        </a>
        <a href="<?= APP_URL ?>/admin/office_qr.php" class="nav-link <?= ($activePage ?? '') === 'office_qr' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8H3m2 8H2m10-2V8"/></svg>
          Office QR Code
        </a>
        <a href="<?= APP_URL ?>/admin/qr_scans.php" class="nav-link <?= ($activePage ?? '') === 'qr_scans' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
          QR Scan Log
        </a>
        <a href="<?= APP_URL ?>/admin/reports.php" class="nav-link <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Reports
        </a>
      </div>
      <?php endif; ?>

    </nav>

    <!-- User footer -->
    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar"><?= e($initials) ?></div>
        <div class="user-info">
          <div class="user-name"><?= e($cu['full_name']) ?></div>
          <div class="user-role"><?= e($cu['role']) ?></div>
        </div>
        <form action="<?= APP_URL ?>/logout.php" method="POST" style="margin:0">
          <button type="submit" class="logout-btn" title="Logout">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          </button>
        </form>
      </div>
    </div>
  </aside>
  <!-- END SIDEBAR -->

  <div class="main-wrapper">
    <!-- ===== TOPBAR ===== -->
    <header class="topbar">
      <div style="display:flex;align-items:center;gap:1rem">
        <button class="menu-toggle" onclick="openSidebar()" aria-label="Menu">
          <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="topbar-left">
          <div class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></div>
          <div class="breadcrumb"><?= APP_NAME ?> / <?= e($pageTitle ?? 'Dashboard') ?></div>
        </div>
      </div>
      <div class="topbar-right">
        <span class="topbar-date" id="live-clock"></span>
        <a href="<?= APP_URL ?>/employee/notifications.php" class="notif-btn" title="Notifications">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <?php if ($unread > 0): ?><span class="notif-badge"><?= $unread ?></span><?php endif; ?>
        </a>
      </div>
    </header>
    <!-- END TOPBAR -->

    <main class="main-content">
<!-- PAGE CONTENT BEGINS BELOW -->
