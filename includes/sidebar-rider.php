<?php
$currentPage = $currentPage ?? '';
?>
<div class="app-shell">
    <header class="app-header app-header-rider" id="appHeader">
        <div class="app-header-inner">
            <a href="<?= baseUrl('rider/dashboard.php') ?>" class="app-brand">
                <span class="app-brand-icon">🏍️</span>
                <span class="app-brand-text"><strong>Rider Hub</strong></span>
            </a>

            <button class="app-nav-toggle" id="mobileMenuBtn" type="button" aria-label="Open menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>

            <nav class="app-nav" id="appNav" aria-label="Main navigation">
                <a href="<?= baseUrl('rider/dashboard.php') ?>" class="app-nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span><span>Dashboard</span>
                </a>
                <a href="<?= baseUrl('rider/parcels.php') ?>" class="app-nav-link <?= $currentPage === 'parcels' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span><span>My Parcels</span>
                </a>
                <a href="<?= baseUrl('rider/history.php') ?>" class="app-nav-link <?= $currentPage === 'history' ? 'active' : '' ?>">
                    <span class="nav-icon">📜</span><span>History</span>
                </a>
                <a href="<?= baseUrl('rider/profile.php') ?>" class="app-nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
                    <span class="nav-icon">👤</span><span>Profile</span>
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
                            <span><?= strtoupper(substr($user['full_name'] ?? 'R', 0, 1)) ?></span>
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
