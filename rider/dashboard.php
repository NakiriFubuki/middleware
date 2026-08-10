<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRider();

$rider = getRiderProfile();
$stats = getRiderStats((int) $rider['id']);
$assigned = getRiderParcels((int) $rider['id'], null, 1, 5);
$todayDeliveries = getTodayDeliveries((int) $rider['id']);

$pageTitle = 'Rider Dashboard';
$currentPage = 'dashboard';
$bodyClass = 'app-layout';
$extraJs = ['gps-tracker.js', 'rider-dashboard.js', 'rider-delivery.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-rider.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="rider-status-bar card">
            <div class="status-info">
                <h3>Delivery Status</h3>
                <p id="locationStatus">
                    <span class="badge badge-success">Online</span>
                    <?php if ($rider['last_latitude']): ?>
                        — <?= formatDate($rider['last_location_at']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div id="locationHelp" class="location-help card hidden">
            <button type="button" class="btn btn-primary" id="enableLocationBtn">Enable Location</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card stat-info">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['total_assigned'] ?></span>
                    <span class="stat-label">Assigned</span>
                </div>
            </div>
            <div class="stat-card stat-warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['pending'] ?></span>
                    <span class="stat-label">Pending</span>
                </div>
            </div>
            <div class="stat-card stat-info">
                <div class="stat-icon">🚚</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['out_for_delivery'] ?></span>
                    <span class="stat-label">Out For Delivery</span>
                </div>
            </div>
            <div class="stat-card stat-success">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['delivered'] ?></span>
                    <span class="stat-label">Delivered</span>
                </div>
            </div>
            <div class="stat-card stat-danger">
                <div class="stat-icon">❌</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['failed'] ?></span>
                    <span class="stat-label">Failed</span>
                </div>
            </div>
            <div class="stat-card stat-primary">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['today_delivered'] ?></span>
                    <span class="stat-label">Today's Deliveries</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Assigned Parcels</h2>
                <a href="<?= baseUrl('rider/parcels.php') ?>" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="parcel-cards">
                <?php if (empty($assigned['parcels'])): ?>
                    <p class="text-center empty-state">—</p>
                <?php else: ?>
                    <?php foreach ($assigned['parcels'] as $p): ?>
                    <div class="parcel-card">
                        <div class="parcel-card-header">
                            <strong><?= sanitize($p['tracking_number']) ?></strong>
                            <span class="badge <?= statusBadgeClass($p['status']) ?>"><?= formatStatus($p['status']) ?></span>
                        </div>
                        <p><strong>To:</strong> <?= sanitize($p['receiver_name']) ?></p>
                        <p class="parcel-address"><?= sanitize($p['delivery_address']) ?></p>
                        <div class="parcel-actions">
                            <?php if (in_array($p['status'], ['pending', 'out_for_delivery'], true)): ?>
                                <button class="btn btn-sm btn-success start-delivery-btn"
                                        data-parcel-id="<?= $p['id'] ?>"
                                        data-label="<?= $p['status'] === 'pending' ? 'Start Delivery' : 'Continue Delivery' ?>">
                                    <?= $p['status'] === 'pending' ? '🚚 Start Delivery' : '📍 Continue Delivery' ?>
                                </button>
                            <?php endif; ?>
                            <a href="<?= baseUrl('rider/parcel-detail.php?id=' . $p['id']) ?>" class="btn btn-sm btn-primary">Manage</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Today's Activity</h2></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Tracking</th><th>Receiver</th><th>Status</th><th>Time</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todayDeliveries)): ?>
                            <tr><td colspan="4" class="text-center">No deliveries today.</td></tr>
                        <?php else: ?>
                            <?php foreach ($todayDeliveries as $p): ?>
                            <tr>
                                <td><?= sanitize($p['tracking_number']) ?></td>
                                <td><?= sanitize($p['receiver_name']) ?></td>
                                <td><span class="badge <?= statusBadgeClass($p['status']) ?>"><?= formatStatus($p['status']) ?></span></td>
                                <td><?= formatDate($p['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
