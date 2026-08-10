<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRider();

$rider = getRiderProfile();
$status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = getRiderParcels((int) $rider['id'], $status ?: null, $page);
$parcels = $result['parcels'];
$pagination = $result['pagination'];

$pageTitle = 'My Parcels';
$currentPage = 'parcels';
$bodyClass = 'app-layout';
$extraJs = ['rider-delivery.js', 'gps-tracker.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-rider.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div id="locationHelp" class="location-help card hidden">
            <button type="button" class="btn btn-primary btn-sm" id="enableLocationBtn">Enable Location</button>
        </div>

        <div class="toolbar">
            <form method="GET" class="search-form">
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="out_for_delivery" <?= $status === 'out_for_delivery' ? 'selected' : '' ?>>Out For Delivery</option>
                    <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <div class="parcel-cards grid-2">
            <?php if (empty($parcels)): ?>
                <p class="empty-state">—</p>
            <?php else: ?>
                <?php foreach ($parcels as $p): ?>
                <div class="parcel-card">
                    <div class="parcel-card-header">
                        <strong><?= sanitize($p['tracking_number']) ?></strong>
                        <span class="badge <?= statusBadgeClass($p['status']) ?>"><?= formatStatus($p['status']) ?></span>
                    </div>
                    <div class="parcel-details">
                        <p><strong>From:</strong> <?= sanitize($p['sender_name']) ?></p>
                        <p><strong>To:</strong> <?= sanitize($p['receiver_name']) ?></p>
                        <p><strong>Phone:</strong> <?= sanitize($p['receiver_phone']) ?></p>
                        <p class="parcel-address"><?= sanitize($p['delivery_address']) ?></p>
                        <p><strong>Fee:</strong> ₱<?= number_format($p['delivery_fee'], 2) ?></p>
                    </div>
                    <div class="parcel-actions">
                        <?php if (in_array($p['status'], ['pending', 'out_for_delivery'], true)): ?>
                            <button class="btn btn-success start-delivery-btn"
                                    data-parcel-id="<?= $p['id'] ?>"
                                    data-label="<?= $p['status'] === 'pending' ? 'Start Delivery' : 'Continue Delivery' ?>">
                                <?= $p['status'] === 'pending' ? '🚚 Start Delivery' : '📍 Continue Delivery' ?>
                            </button>
                        <?php endif; ?>
                        <a href="<?= baseUrl('rider/parcel-detail.php?id=' . $p['id']) ?>" class="btn btn-outline">Details</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?= buildPaginationHtml($pagination['current_page'], $pagination['total_pages'], baseUrl('rider/parcels.php') . '?status=' . urlencode($status)) ?>
</div>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
