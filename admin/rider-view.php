<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$rider = getRiderById($id);
if (!$rider) {
    redirect(baseUrl('admin/riders.php'));
}

$location = getRiderCurrentLocation($id);
$activityLogs = getRiderActivityLogs((int) $rider['user_id'], 30);
$parcelResult = getParcels(['rider_id' => $id], 1, 10);
$hasLocation = $rider['last_latitude'] && $rider['last_longitude'];
$activeDelivery = null;
foreach ($parcelResult['parcels'] as $p) {
    if ($p['status'] === 'out_for_delivery') {
        $activeDelivery = $p;
        break;
    }
}

$routeParcels = getRiderRouteParcels($id, 20);
$requestedParcelId = isset($_GET['parcel_id']) ? (int) $_GET['parcel_id'] : 0;
$defaultRouteParcelId = $requestedParcelId ?: ($activeDelivery
    ? (int) $activeDelivery['id']
    : (int) ($routeParcels[0]['parcel_id'] ?? 0));
$deleteBlockers = getRiderDeleteBlockers($id);
$canDeleteRider = empty($deleteBlockers);
$backHref = backUrl('admin/riders.php');

$pageTitle = $rider['full_name'];
$currentPage = 'riders';
$bodyClass = 'app-layout';
$extraCss = ['map.css'];
$extraJs = ['route-utils.js', 'map-manager.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="toolbar">
            <a href="<?= sanitize($backHref) ?>" class="btn btn-outline">&larr; Back</a>
            <div class="btn-group">
                <a href="<?= sanitize(linkWithReturn('admin/map.php', ['rider_id' => $id, 'parcel_id' => $defaultRouteParcelId])) ?>" class="btn btn-sm btn-outline">Track on Map</a>
                <button type="button" class="btn btn-sm btn-danger" id="deleteRiderBtn"
                        data-id="<?= $rider['id'] ?>"
                        data-name="<?= sanitize($rider['full_name']) ?>"
                        data-code="<?= sanitize($rider['rider_code']) ?>"
                        <?= $canDeleteRider ? '' : 'disabled title="Complete active deliveries before deleting"' ?>>Delete Rider</button>
            </div>
        </div>

        <?php if (!$canDeleteRider): ?>
        <div class="alert alert-warning">
            <?= sanitize(implode(' ', $deleteBlockers)) ?> Complete or reassign those deliveries before deleting this rider.
        </div>
        <?php endif; ?>

        <div class="detail-grid">
            <div class="card">
                <div class="card-header">
                    <h2><?= sanitize($rider['full_name']) ?></h2>
                    <?php if ($rider['is_online']): ?>
                        <span class="badge badge-success">Online</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Offline</span>
                    <?php endif; ?>
                </div>
                <div class="detail-list">
                    <div class="detail-item"><span>Code</span><strong><?= sanitize($rider['rider_code']) ?></strong></div>
                    <div class="detail-item"><span>Username</span><strong><?= sanitize($rider['username']) ?></strong></div>
                    <div class="detail-item"><span>Email</span><strong><?= sanitize($rider['email']) ?></strong></div>
                    <div class="detail-item"><span>Phone</span><strong><?= sanitize($rider['phone'] ?? '—') ?></strong></div>
                    <div class="detail-item"><span>Vehicle</span><strong><?= sanitize($rider['vehicle_type']) ?></strong></div>
                    <div class="detail-item"><span>License Plate</span><strong><?= sanitize($rider['license_number'] ?? '—') ?></strong></div>
                    <div class="detail-item"><span>Total Deliveries</span><strong><?= $rider['total_deliveries'] ?></strong></div>
                    <div class="detail-item"><span>Account</span><strong><?= $rider['is_active'] ? 'Active' : 'Pending Approval' ?></strong></div>
                    <div class="detail-item"><span>Last Login</span><strong><?= formatDate($rider['last_login']) ?></strong></div>
                    <div class="detail-item"><span>Last Location</span><strong><?= formatDate($rider['last_location_at']) ?></strong></div>
                    <?php if ($rider['last_latitude']): ?>
                    <div class="detail-item"><span>Coordinates</span><strong><?= $rider['last_latitude'] ?>, <?= $rider['last_longitude'] ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Location &amp; Route History</h2>
                    <?php if ($rider['is_online']): ?>
                        <span class="badge badge-success">Live</span>
                    <?php endif; ?>
                </div>
                <?php if ($activeDelivery): ?>
                <div class="map-live-meta">
                    <span><strong>Delivering to:</strong> <?= sanitize($activeDelivery['delivery_address']) ?></span>
                </div>
                <?php endif; ?>
                <div class="map-route-toolbar">
                    <label class="route-filter-label">
                        <span>Parcel route</span>
                        <select id="routeParcelFilter" class="form-control form-control-sm">
                            <option value="active" <?= $activeDelivery ? 'selected' : '' ?>>Current delivery</option>
                            <?php foreach ($routeParcels as $rp): ?>
                            <option value="<?= (int) $rp['parcel_id'] ?>"
                                <?= (int) $rp['parcel_id'] === $defaultRouteParcelId ? 'selected' : '' ?>>
                                <?= sanitize($rp['tracking_number']) ?> (<?= (int) $rp['points'] ?> pts)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="btn btn-sm btn-outline" id="loadRouteBtn">Show Route</button>
                    <button type="button" class="btn btn-sm btn-primary" id="replayRouteBtn">Replay Route</button>
                </div>
                <div class="map-live-meta">
                    <span><strong>Position:</strong> <span id="liveCoords"><?= $hasLocation ? $rider['last_latitude'] . ', ' . $rider['last_longitude'] : '—' ?></span></span>
                    <span><strong>Updated:</strong> <span id="liveLocationTime"><?= formatDate($rider['last_location_at']) ?></span></span>
                    <span id="routePointCount"></span>
                </div>
                <div class="map-legend map-legend-inline">
                    <span class="legend-item"><span class="legend-line route"></span></span>
                    <span class="legend-item"><span class="legend-dot start"></span></span>
                    <span class="legend-item"><span class="legend-dot end"></span></span>
                </div>
                <div id="riderDetailMap" class="map-container" style="height:380px"
                     data-rider-id="<?= (int) $rider['id'] ?>"
                     data-online="<?= $rider['is_online'] ? '1' : '0' ?>"
                     data-api="<?= baseUrl('api/get_locations.php') ?>"
                     data-lat="<?= $rider['last_latitude'] ?? '' ?>"
                     data-lng="<?= $rider['last_longitude'] ?? '' ?>"
                     data-name="<?= sanitize($rider['full_name']) ?>"
                     data-active-parcel-id="<?= $activeDelivery ? (int) $activeDelivery['id'] : (int) ($routeParcels[0]['parcel_id'] ?? 0) ?>"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Recent Parcels</h2></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Tracking</th><th>Receiver</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parcelResult['parcels'] as $p): ?>
                        <tr>
                            <td><a href="<?= baseUrl('admin/parcel-view.php?id=' . $p['id']) ?>"><?= sanitize($p['tracking_number']) ?></a></td>
                            <td><?= sanitize($p['receiver_name']) ?></td>
                            <td><span class="badge <?= statusBadgeClass($p['status']) ?>"><?= formatStatus($p['status']) ?></span></td>
                            <td><?= formatDate($p['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($routeParcels)): ?>
        <div class="card">
            <div class="card-header"><h2>Route History by Parcel</h2></div>
            <div class="table-responsive">
                <table class="data-table route-dates-table">
                    <thead>
                        <tr><th>Tracking</th><th>Status</th><th>GPS Points</th><th>Period</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($routeParcels as $rp): ?>
                        <tr>
                            <td><a href="<?= baseUrl('admin/parcel-view.php?id=' . (int) $rp['parcel_id']) ?>"><?= sanitize($rp['tracking_number']) ?></a></td>
                            <td><span class="badge <?= statusBadgeClass($rp['status']) ?>"><?= formatStatus($rp['status']) ?></span></td>
                            <td><?= (int) $rp['points'] ?></td>
                            <td><?= formatDate($rp['route_started_at']) ?> — <?= formatDate($rp['route_ended_at']) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline view-route-parcel-btn"
                                        data-parcel-id="<?= (int) $rp['parcel_id'] ?>">View on map</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h2>Activity Logs</h2></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Action</th><th>Description</th><th>IP</th><th>Time</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLogs as $log): ?>
                        <tr>
                            <td><?= sanitize($log['action']) ?></td>
                            <td><?= sanitize($log['description'] ?? '') ?></td>
                            <td><?= sanitize(formatIpAddress($log['ip_address'] ?? null)) ?></td>
                            <td><?= formatDate($log['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
</div>

<div class="modal" id="deleteRiderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Rider</h3>
            <button class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete rider <strong id="deleteRiderName"></strong> (<span id="deleteRiderCode"></span>)?</p>
            <p class="text-muted">This will permanently remove the account and unassign their parcels. This action cannot be undone.</p>
            <div class="alert alert-danger hidden" id="deleteRiderError"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close-modal>Cancel</button>
            <button class="btn btn-danger" id="confirmDeleteRiderBtn">Delete Rider</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?= assetUrl('vendor/leaflet/leaflet.css') ?>">
<script src="<?= assetUrl('vendor/leaflet/leaflet.js') ?>"></script>
<script>
document.getElementById('deleteRiderBtn')?.addEventListener('click', function () {
    if (this.disabled) return;
    document.getElementById('deleteRiderName').textContent = this.dataset.name;
    document.getElementById('deleteRiderCode').textContent = this.dataset.code;
    document.getElementById('deleteRiderError')?.classList.add('hidden');
    document.getElementById('deleteRiderModal').classList.add('active');
});

document.getElementById('routeParcelFilter')?.addEventListener('change', function () {
    MapManager.loadRouteFromFilter();
});

document.querySelectorAll('.view-route-parcel-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const filter = document.getElementById('routeParcelFilter');
        if (filter) {
            filter.value = btn.dataset.parcelId;
            MapManager.loadRouteFromFilter();
            document.getElementById('riderDetailMap')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});

document.getElementById('confirmDeleteRiderBtn').addEventListener('click', async function () {
    const btn = this;
    const riderId = document.getElementById('deleteRiderBtn').dataset.id;
    const errorBox = document.getElementById('deleteRiderError');
    btn.disabled = true;
    errorBox?.classList.add('hidden');

    try {
        const res = await Ajax.post('<?= baseUrl('api/delete_rider.php') ?>', { rider_id: riderId });
        if (res.success) {
            Toast.success(res.message);
            window.location.href = '<?= sanitize($backHref) ?>';
        } else {
            const message = res.message || 'Unable to delete rider.';
            if (errorBox) {
                errorBox.textContent = message;
                errorBox.classList.remove('hidden');
            }
            Toast.error(message);
        }
    } catch (e) {
        const message = 'Failed to delete rider.';
        if (errorBox) {
            errorBox.textContent = message;
            errorBox.classList.remove('hidden');
        }
        Toast.error(message);
    } finally {
        btn.disabled = false;
    }
});
</script>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
