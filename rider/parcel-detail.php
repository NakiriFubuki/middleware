<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRider();

$rider = getRiderProfile();
$id = (int) ($_GET['id'] ?? 0);
$parcel = getParcelById($id);

if (!$parcel || (int) $parcel['assigned_rider_id'] !== (int) $rider['id']) {
    redirect(baseUrl('rider/parcels.php'));
}

$history = getParcelHistory($id);
$photos = getParcelPhotos($id);
$hasDeliveryPhoto = !empty($photos);
$manageAccess = getRiderParcelManageAccess($parcel);
$canManage = $manageAccess['allowed'];
$canNavigate = $canManage && in_array($parcel['status'], ['pending', 'out_for_delivery'], true);
$canPhoto = $canManage && in_array($parcel['status'], ['out_for_delivery', 'delivered'], true);

$pageTitle = $parcel['tracking_number'];
$currentPage = 'parcels';
$bodyClass = 'app-layout';
$extraCss = ['map.css'];
$extraJs = ['route-utils.js', 'parcel-updater.js', 'image-uploader.js', 'rider-delivery.js', 'gps-tracker.js', 'delivery-map.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-rider.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="toolbar">
            <a href="<?= baseUrl('rider/parcels.php') ?>" class="btn btn-outline">&larr; Back</a>
        </div>

        <?php if (!$canManage): ?>
        <div class="alert alert-warning manage-expired-alert">
            This parcel is locked. The <?= (int) RIDER_PARCEL_MANAGE_MINUTES ?>-minute management window has expired.
        </div>
        <?php elseif (!empty($manageAccess['remaining_seconds'])): ?>
        <div class="alert alert-info manage-timer-alert" id="manageTimerAlert"
             data-expires-at="<?= (int) $manageAccess['expires_at'] ?>">
            You can still update this parcel for <strong id="manageTimerText">--:--</strong>.
        </div>
        <?php endif; ?>

        <div class="detail-grid">
            <div class="card">
                <div class="card-header">
                    <h2><?= sanitize($parcel['tracking_number']) ?></h2>
                    <span class="badge <?= statusBadgeClass($parcel['status']) ?>" id="currentStatus"><?= formatStatus($parcel['status']) ?></span>
                </div>
                <div class="detail-list">
                    <div class="detail-item"><span>Sender</span><strong><?= sanitize($parcel['sender_name']) ?> — <?= sanitize($parcel['sender_phone']) ?></strong></div>
                    <div class="detail-item"><span>Pickup</span><strong><?= sanitize($parcel['pickup_address']) ?></strong></div>
                    <div class="detail-item"><span>Receiver</span><strong><?= sanitize($parcel['receiver_name']) ?> — <?= sanitize($parcel['receiver_phone']) ?></strong></div>
                    <div class="detail-item"><span>Delivery Address</span><strong id="deliveryAddressText"><?= sanitize($parcel['delivery_address']) ?></strong></div>
                    <div class="detail-item"><span>Description</span><strong><?= sanitize($parcel['parcel_description'] ?? '—') ?></strong></div>
                    <div class="detail-item"><span>Weight</span><strong><?= $parcel['parcel_weight'] ?> kg</strong></div>
                </div>
            </div>

            <div id="photoUploadSection" class="card <?= $canPhoto ? '' : 'hidden' ?>">
                <div class="card-header"><h2>Delivery Photo</h2></div>
                <div style="padding: 0 1.25rem 1.25rem">
                    <p class="photo-required-note" id="photoRequiredNote" <?= $hasDeliveryPhoto ? 'hidden' : '' ?>>
                        Upload a delivery proof photo before you can mark this parcel as Delivered.
                    </p>
                    <div class="camera-container">
                        <video id="cameraPreview" autoplay playsinline class="hidden"></video>
                        <canvas id="photoCanvas" class="hidden"></canvas>
                        <img id="photoPreview" class="photo-preview hidden" alt="Preview">
                        <div class="camera-actions">
                            <button type="button" class="btn btn-primary" id="startCameraBtn">Take Photo</button>
                            <button type="button" class="btn btn-outline hidden" id="captureBtn">Capture</button>
                            <label class="btn btn-outline">
                                Upload from Gallery
                                <input type="file" id="fileInput" accept="image/jpeg,image/png,image/webp" capture="environment" hidden>
                            </label>
                            <button type="button" class="btn btn-success hidden" id="uploadPhotoBtn">Save Photo</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($canManage): ?>
            <div class="card">
                <div class="card-header"><h2>Update Status</h2></div>
                <?php if (in_array($parcel['status'], ['pending', 'out_for_delivery'], true)): ?>
                <div style="padding:0 1.25rem 1rem">
                    <button class="btn btn-success btn-block start-delivery-btn"
                            data-parcel-id="<?= $id ?>"
                            data-label="Start Delivery">
                        <?= $parcel['status'] === 'pending' ? 'Start Delivery' : 'Continue Delivery' ?>
                    </button>
                </div>
                <?php endif; ?>
                <form id="statusUpdateForm" data-parcel-id="<?= $id ?>" data-has-photo="<?= $hasDeliveryPhoto ? '1' : '0' ?>" data-manage-allowed="1">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="parcelStatus" required>
                            <option value="pending" <?= $parcel['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="out_for_delivery" <?= $parcel['status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out For Delivery</option>
                            <option value="delivered" <?= $parcel['status'] === 'delivered' ? 'selected' : '' ?> <?= !$hasDeliveryPhoto && $parcel['status'] !== 'delivered' ? 'disabled' : '' ?>>Delivered</option>
                            <option value="failed" <?= $parcel['status'] === 'failed' ? 'selected' : '' ?>>Failed Delivery</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" id="statusRemarks" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Update Status</button>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header"><h2>Parcel Locked</h2></div>
                <div style="padding: 0 1.25rem 1.25rem; color: var(--text-secondary);">
                    Updates are no longer allowed for this parcel.
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($canNavigate): ?>
        <div class="card">
            <div class="card-header">
                <h2>Navigation</h2>
                <span class="badge badge-info" id="deliveryMapStatus"></span>
            </div>
            <div class="map-legend" style="padding: 0 1rem 0.5rem">
                <span class="legend-item"><span class="legend-line route"></span> Driving route</span>
                <span class="legend-item"><span class="legend-line" style="background:#16a34a"></span> Your movement trail</span>
            </div>
            <div class="btn-group" style="padding: 0 1rem 0.75rem">
                <button type="button" class="btn btn-sm btn-outline" id="refreshRouteBtn">Refresh Route</button>
                <button type="button" class="btn btn-sm btn-primary" id="openGoogleMapsBtn">Open in Google Maps</button>
                <button type="button" class="btn btn-sm btn-outline" id="openWazeBtn">Open in Waze</button>
            </div>
            <div id="deliveryMap" class="map-container" style="height:360px;margin:0 1rem 1rem"
                 data-address="<?= sanitize($parcel['delivery_address']) ?>"
                 data-parcel-id="<?= $id ?>"
                 data-rider-lat="<?= $rider['last_latitude'] ?? '' ?>"
                 data-rider-lng="<?= $rider['last_longitude'] ?? '' ?>"></div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h2>Status History</h2></div>
            <div class="timeline" id="statusTimeline">
                <?php foreach ($history as $h): ?>
                <div class="timeline-item">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <span class="badge <?= statusBadgeClass($h['status']) ?>"><?= formatStatus($h['status']) ?></span>
                        <p><?= sanitize($h['remarks'] ?? '') ?></p>
                        <small><?= formatDate($h['created_at']) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" id="photoGalleryCard">
            <div class="card-header"><h2>Uploaded Photos</h2></div>
            <div class="photo-gallery" id="photoGallery">
                <?php if (empty($photos)): ?>
                    <p class="text-muted empty-state hidden" id="noPhotosMsg"></p>
                <?php else: ?>
                    <?php foreach ($photos as $photo): ?>
                    <div class="photo-item">
                        <img src="<?= baseUrl($photo['file_path']) ?>" alt="Proof">
                        <small><?= formatDate($photo['created_at']) ?></small>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
</div>

<link rel="stylesheet" href="<?= assetUrl('vendor/leaflet/leaflet.css') ?>">
<script src="<?= assetUrl('vendor/leaflet/leaflet.js') ?>"></script>
<?php if ($canManage && !empty($manageAccess['remaining_seconds'])): ?>
<script>
(function () {
    const alertEl = document.getElementById('manageTimerAlert');
    const textEl = document.getElementById('manageTimerText');
    if (!alertEl || !textEl) return;

    const expiresAt = parseInt(alertEl.dataset.expiresAt, 10) * 1000;

    function tick() {
        const remaining = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
        const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
        const seconds = String(remaining % 60).padStart(2, '0');
        textEl.textContent = minutes + ':' + seconds;

        if (remaining <= 0) {
            window.location.reload();
        }
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
