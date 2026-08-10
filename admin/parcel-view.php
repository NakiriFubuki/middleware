<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$parcel = getParcelById($id);
if (!$parcel) {
    redirect(baseUrl('admin/parcels.php'));
}

$history = getParcelHistory($id);
$photos = getParcelPhotos($id);

$pageTitle = 'Parcel ' . $parcel['tracking_number'];
$currentPage = 'parcels';
$bodyClass = 'app-layout';
$extraJs = ['assign-rider.js'];
$assignRiders = getActiveRiders();
$backHref = backUrl('admin/parcels.php');

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <?php if (isset($_GET['created'])): ?>
            <div class="alert alert-success">Parcel created successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Parcel updated successfully.</div>
        <?php endif; ?>

        <div class="toolbar">
            <a href="<?= sanitize($backHref) ?>" class="btn btn-outline">&larr; Back</a>
            <?php if (!$parcel['assigned_rider_id']): ?>
            <button type="button" class="btn btn-primary assign-btn" data-id="<?= (int) $parcel['id'] ?>">Assign Rider</button>
            <?php endif; ?>
            <?php if ($parcel['assigned_rider_id']): ?>
            <a href="<?= sanitize(linkWithReturn('admin/rider-view.php', ['id' => (int) $parcel['assigned_rider_id'], 'parcel_id' => $id])) ?>" class="btn btn-outline">View Route</a>
            <?php endif; ?>
            <a href="<?= sanitize(linkWithReturn('admin/parcel-edit.php', ['id' => $id])) ?>" class="btn btn-outline">Edit</a>
        </div>

        <div class="detail-grid">
            <div class="card">
                <div class="card-header">
                    <h2><?= sanitize($parcel['tracking_number']) ?></h2>
                    <span class="badge <?= statusBadgeClass($parcel['status']) ?>"><?= formatStatus($parcel['status']) ?></span>
                </div>
                <div class="detail-list">
                    <?php if (!isPlaceholderParcelField($parcel['sender_name'])): ?>
                    <div class="detail-item"><span>Sender</span><strong><?= sanitize($parcel['sender_name']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!isPlaceholderParcelField($parcel['sender_phone'])): ?>
                    <div class="detail-item"><span>Sender Phone</span><strong><?= sanitize($parcel['sender_phone']) ?></strong></div>
                    <?php endif; ?>
                    <div class="detail-item"><span>Receiver</span><strong><?= sanitize($parcel['receiver_name']) ?></strong></div>
                    <?php if (!isPlaceholderParcelField($parcel['receiver_phone'])): ?>
                    <div class="detail-item"><span>Receiver Phone</span><strong><?= sanitize($parcel['receiver_phone']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!isPlaceholderParcelField($parcel['pickup_address'])): ?>
                    <div class="detail-item"><span>Pickup</span><strong><?= sanitize($parcel['pickup_address']) ?></strong></div>
                    <?php endif; ?>
                    <div class="detail-item"><span>Address</span><strong><?= sanitize($parcel['delivery_address']) ?></strong></div>
                    <div class="detail-item"><span>Remarks</span><strong><?= sanitize($parcel['parcel_description'] ?? '—') ?></strong></div>
                    <div class="detail-item"><span>Weight</span><strong><?= $parcel['parcel_weight'] ?> kg</strong></div>
                    <?php if ((float) $parcel['delivery_fee'] > 0): ?>
                    <div class="detail-item"><span>Fee</span><strong>₱<?= number_format($parcel['delivery_fee'], 2) ?></strong></div>
                    <?php endif; ?>
                    <div class="detail-item"><span>Rider</span><strong><?= sanitize($parcel['rider_name'] ?? 'Unassigned') ?></strong></div>
                    <?php if (!$parcel['assigned_rider_id']): ?>
                    <div class="detail-item assign-inline-row">
                        <span>Assign</span>
                        <button type="button" class="btn btn-sm btn-primary assign-btn" data-id="<?= (int) $parcel['id'] ?>">Choose Rider</button>
                    </div>
                    <?php endif; ?>
                    <div class="detail-item"><span>Created</span><strong><?= formatDate($parcel['created_at']) ?></strong></div>
                    <?php if ($parcel['delivered_at']): ?>
                    <div class="detail-item"><span>Delivered</span><strong><?= formatDate($parcel['delivered_at']) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Status History</h2></div>
                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <span class="badge <?= statusBadgeClass($h['status']) ?>"><?= formatStatus($h['status']) ?></span>
                            <p><?= sanitize($h['remarks'] ?? '') ?></p>
                            <small><?= formatDate($h['created_at']) ?> — <?= sanitize($h['rider_name'] ?? 'System') ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($photos)): ?>
        <div class="card">
            <div class="card-header"><h2>Delivery Proof Photos</h2></div>
            <div class="photo-gallery">
                <?php foreach ($photos as $photo): ?>
                <div class="photo-item">
                    <a href="<?= baseUrl($photo['file_path']) ?>" target="_blank">
                        <img src="<?= baseUrl($photo['file_path']) ?>" alt="Delivery proof">
                    </a>
                    <small><?= formatDate($photo['created_at']) ?> — <?= sanitize($photo['rider_name']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/assign-rider-modal.php'; ?>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
