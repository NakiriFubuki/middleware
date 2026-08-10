<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = 'Live Tracking';
$currentPage = 'live-tracking';
$bodyClass = 'app-layout';
$extraCss = ['map.css'];
$extraJs = ['route-utils.js', 'live-tracking.js', 'map-manager.js'];

$liveData = getLiveTrackingData();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap">
        <div class="live-header">
            <span id="liveIndicator" class="live-indicator">Live</span>
            <a href="<?= baseUrl('admin/map.php') ?>" class="btn btn-sm btn-primary">Full Map View</a>
        </div>

        <div class="detail-grid">
            <div class="card">
                <div class="card-header"><h2>🟢 Online Riders</h2></div>
                <div class="online-riders-panel" id="onlineRidersList">
                    <?php if (!empty($liveData['online_riders'])): ?>
                        <?php foreach ($liveData['online_riders'] as $r): ?>
                        <?php $hasGps = $r['last_latitude'] && $r['last_longitude']; ?>
                        <div class="online-rider-item"
                             data-rider-id="<?= (int) $r['id'] ?>"
                             data-lat="<?= $hasGps ? $r['last_latitude'] : '' ?>"
                             data-lng="<?= $hasGps ? $r['last_longitude'] : '' ?>">
                            <span class="badge badge-success">Online</span>
                            <strong><?= sanitize($r['full_name']) ?></strong> (<?= sanitize($r['rider_code']) ?>)
                            <small><?= (int) $r['active_deliveries'] ?> active<?= $hasGps ? ' · ' . formatDate($r['last_location_at']) : '' ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Live Map</h2></div>
                <div id="liveMap" class="map-container" style="height:320px"
                     data-api="<?= baseUrl('api/get_locations.php') ?>"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>🚚 Active Deliveries</h2>
                <span class="badge badge-info" id="activeCount"><?= count($liveData['active_deliveries']) ?></span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>Rider</th>
                            <th>Receiver</th>
                            <th>Address</th>
                            <th>GPS</th>
                            <th>Last Update</th>
                        </tr>
                    </thead>
                    <tbody id="activeDeliveriesBody">
                        <?php if (empty($liveData['active_deliveries'])): ?>
                            <tr><td colspan="6" class="text-center">—</td></tr>
                        <?php else: ?>
                            <?php foreach ($liveData['active_deliveries'] as $d): ?>
                            <tr>
                                <td><a href="<?= baseUrl('admin/parcel-view.php?id=' . $d['id']) ?>"><strong><?= sanitize($d['tracking_number']) ?></strong></a></td>
                                <td><?= sanitize($d['rider_name']) ?> (<?= sanitize($d['rider_code']) ?>)</td>
                                <td><?= sanitize($d['receiver_name']) ?></td>
                                <td><?= sanitize($d['delivery_address']) ?></td>
                                <td><?= $d['last_latitude'] ? $d['last_latitude'] . ', ' . $d['last_longitude'] : '—' ?></td>
                                <td><?= formatDate($d['last_location_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="detail-grid">
            <div class="card">
                <div class="card-header"><h2>📋 Status Updates</h2></div>
                <div class="live-feed" id="statusFeed">
                    <?php foreach (array_slice($liveData['status_updates'], 0, 10) as $u): ?>
                    <div class="feed-item">
                        <div class="feed-time"><?= formatDate($u['created_at']) ?></div>
                        <div class="feed-content">
                            <strong><?= sanitize($u['tracking_number']) ?></strong>
                            <span class="badge <?= statusBadgeClass($u['status']) ?>"><?= formatStatus($u['status']) ?></span>
                            <p><?= sanitize($u['remarks'] ?? '') ?> — <em><?= sanitize($u['rider_name'] ?? 'System') ?></em></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>📍 Current Positions</h2></div>
                <div class="live-feed" id="locationFeed">
                    <?php if (!empty($liveData['location_updates'])): ?>
                        <?php foreach ($liveData['location_updates'] as $l): ?>
                        <div class="feed-item feed-item-location feed-item-clickable"
                             data-rider-id="<?= (int) $l['rider_id'] ?>"
                             data-lat="<?= $l['latitude'] ?>" data-lng="<?= $l['longitude'] ?>">
                            <div class="feed-time"><?= formatDate($l['created_at']) ?></div>
                            <div class="feed-content">
                                <strong><?= sanitize($l['rider_name']) ?></strong> (<?= sanitize($l['rider_code']) ?>)
                                <p>📍 <?= $l['latitude'] ?>, <?= $l['longitude'] ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
</div>

<link rel="stylesheet" href="<?= assetUrl('vendor/leaflet/leaflet.css') ?>">
<script src="<?= assetUrl('vendor/leaflet/leaflet.js') ?>"></script>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
