<?php
$currentPage = $currentPage ?? '';
$pendingCount = countPendingRiders();
?>
<div class="app-shell">
    <header class="app-header" id="appHeader">
        <div class="app-header-inner">
            <a href="<?= baseUrl('admin/dashboard.php') ?>" class="app-brand">
                <span class="app-brand-icon">📦</span>
                <span class="app-brand-text"><strong>PDMS</strong></span>
            </a>

            <button class="app-nav-toggle" id="mobileMenuBtn" type="button" aria-label="Open menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>

            <nav class="app-nav" id="appNav" aria-label="Main navigation">
                <a href="<?= baseUrl('admin/dashboard.php') ?>" class="app-nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span><span>Dashboard</span>
                </a>
                <a href="<?= baseUrl('admin/parcels.php') ?>" class="app-nav-link <?= $currentPage === 'parcels' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span><span>Parcels</span>
                </a>
                <a href="<?= baseUrl('admin/parcel-create.php') ?>" class="app-nav-link <?= $currentPage === 'parcel-create' ? 'active' : '' ?>">
                    <span class="nav-icon">➕</span><span>New</span>
                </a>
                <a href="<?= baseUrl('admin/riders.php') ?>" class="app-nav-link <?= $currentPage === 'riders' ? 'active' : '' ?>">
                    <span class="nav-icon">🏍️</span><span>Riders</span>
                </a>
                <a href="<?= baseUrl('admin/live-tracking.php') ?>" class="app-nav-link <?= $currentPage === 'live-tracking' ? 'active' : '' ?>">
                    <span class="nav-icon">📡</span><span>Live</span>
                </a>
                <a href="<?= baseUrl('admin/map.php') ?>" class="app-nav-link <?= $currentPage === 'map' ? 'active' : '' ?>">
                    <span class="nav-icon">🗺️</span><span>Map</span>
                </a>
                <a href="<?= baseUrl('admin/pending-riders.php') ?>" class="app-nav-link <?= $currentPage === 'pending-riders' ? 'active' : '' ?>">
                    <span class="nav-icon">⏳</span><span>Approvals</span>
                    <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
                </a>
                <a href="<?= baseUrl('admin/reports.php') ?>" class="app-nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span><span>Reports</span>
                </a>
                <a href="<?= baseUrl('admin/activity-logs.php') ?>" class="app-nav-link <?= $currentPage === 'activity' ? 'active' : '' ?>">
                    <span class="nav-icon">📝</span><span>Logs</span>
                </a>
                <a href="<?= baseUrl('logout.php') ?>" class="app-nav-link app-nav-logout" data-logout>
                    <span class="nav-icon">🚪</span><span>Logout</span>
                </a>
            </nav>

            <div class="app-header-actions">
                <?php $user = currentUser(); ?>
                <div class="app-user-chip">
                    <div class="user-avatar">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img src="<?= baseUrl($user['profile_photo']) ?>" alt="Profile">
                        <?php else: ?>
                            <span><?= strtoupper(substr($user['full_name'] ?? 'A', 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?= sanitize($user['full_name'] ?? '') ?></span>
                </div>
                <a href="<?= baseUrl('logout.php') ?>" class="btn btn-sm btn-outline app-logout-btn" data-logout>Logout</a>
            </div>
        </div>
    </header>
    <div class="app-nav-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
    <main class="app-main">
