<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = 'Admin Dashboard';
$currentPage = 'dashboard';
$bodyClass = 'app-layout';
$extraJs = ['admin-dashboard.js', 'live-tracking.js', 'assign-rider.js'];

$stats = getDashboardStats();
$recentResult = getParcels([], 1, 8);
$recentParcels = $recentResult['parcels'];
$activeDeliveries = getActiveDeliveries();
$assignRiders = getActiveRiders();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="stats-grid" id="dashboardStats">
            <div class="stat-card stat-primary">
                <div class="stat-icon">🏍️</div>
                <div class="stat-info">
                    <span class="stat-value" data-stat="total_riders"><?= $stats['total_riders'] ?></span>
                    <span class="stat-label">Total Riders</span>
                </div>
            </div>
            <div class="stat-card stat-success">
                <div class="stat-icon">🟢</div>
                <div class="stat-info">
                    <span class="stat-value" data-stat="online_riders"><?= $stats['online_riders'] ?></span>
                    <span class="stat-label">Online Riders</span>
                </div>
            </div>
            <div class="stat-card stat-secondary">
                <div class="stat-icon">⚫</div>
                <div class="stat-info">
                    <span class="stat-value" data-stat="offline_riders"><?= $stats['offline_riders'] ?></span>
                    <span class="stat-label">Offline Riders</span>
                </div>
            </div>
            <div class="stat-card stat-info">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <span class="stat-value" data-stat="total_parcels"><?= $stats['total_parcels'] ?></span>
                    <span class="stat-label">Total Parcels</span>
                </div>
            </div>
            <a href="<?= baseUrl('admin/parcels.php?status=pending') ?>" class="stat-card stat-warning stat-card-link">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <span class="stat-value" data-stat="pending_parcels"><?= $stats['pending_parcels'] ?></span>
                    <span class="stat-label">Pending Parcels</span>
                </div>
            </a>
            <div class="stat-card stat-info">
                <div class="stat-icon">🚚</div>
                <div class="stat-info">
                    <span class="stat-value" data-stat="out_for_delivery"><?= $stats['out_for_delivery'] ?></span>
                    <span class="stat-label">Out For Delivery</span>
                </div>
            </div>
            <div class="stat-card stat-success">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-value" data-stat="delivered"><?= $stats['delivered'] ?></span>
                    <span class="stat-label">Delivered</span>
                </div>
            </div>
            <div class="stat-card stat-danger">
                <div class="stat-icon">❌</div>
                <div class="stat-info">
                    <span class="stat-value" data-stat="failed"><?= $stats['failed'] ?></span>
                    <span class="stat-label">Failed</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>🟢 Online Riders Now</h2>
                <a href="<?= baseUrl('admin/live-tracking.php') ?>" class="btn btn-sm btn-outline">Live Tracking</a>
            </div>
            <div class="online-riders-panel" id="onlineRidersList"></div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>🚚 Active Deliveries</h2>
                <div>
                    <span id="liveIndicator" class="live-indicator">Live</span>
                    <a href="<?= baseUrl('admin/live-tracking.php') ?>" class="btn btn-sm btn-outline">Full Tracking</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>Rider</th>
                            <th>Receiver</th>
                            <th>GPS</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody id="activeDeliveriesBody">
                        <?php if (empty($activeDeliveries)): ?>
                            <tr><td colspan="5" class="text-center">No active deliveries.</td></tr>
                        <?php else: ?>
                            <?php foreach ($activeDeliveries as $d): ?>
                            <tr>
                                <td><strong><?= sanitize($d['tracking_number']) ?></strong></td>
                                <td><?= sanitize($d['rider_name']) ?></td>
                                <td><?= sanitize($d['receiver_name']) ?></td>
                                <td><?= $d['last_latitude'] ? '✓' : '—' ?></td>
                                <td><?= formatDate($d['updated_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Recent Parcels</h2>
                <a href="<?= baseUrl('admin/parcels.php') ?>" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>Receiver</th>
                            <th>Rider</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="recentParcelsBody">
                        <?php foreach ($recentParcels as $p): ?>
                        <tr>
                            <td><strong><?= sanitize($p['tracking_number']) ?></strong></td>
                            <td><?= sanitize($p['receiver_name']) ?></td>
                            <td>
                                <?php if ($p['assigned_rider_id']): ?>
                                    <?= sanitize($p['rider_name'] ?? $p['rider_code']) ?>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-primary assign-btn" data-id="<?= (int) $p['id'] ?>">Assign</button>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= statusBadgeClass($p['status']) ?>"><?= formatStatus($p['status']) ?></span></td>
                            <td><?= formatDate($p['created_at']) ?></td>
                            <td>
                                <a href="<?= baseUrl('admin/parcel-view.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline">View</a>
                                <?php if (!$p['assigned_rider_id']): ?>
                                <button type="button" class="btn btn-sm btn-outline assign-btn" data-id="<?= (int) $p['id'] ?>">Assign</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<?php require __DIR__ . '/../includes/assign-rider-modal.php'; ?>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
