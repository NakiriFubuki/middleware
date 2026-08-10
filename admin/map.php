<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$focusRiderId = (int) ($_GET['rider_id'] ?? 0);
$focusParcelId = (int) ($_GET['parcel_id'] ?? 0);
$backHref = backUrl('admin/map.php');

$pageTitle = 'Live Map';
$currentPage = 'map';
$bodyClass = 'app-layout';
$extraCss = ['map.css'];
$extraJs = ['route-utils.js', 'map-manager.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar-admin.php';
?>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="content-area content-wrap map-page">
        <div class="toolbar map-page-toolbar">
            <a href="<?= sanitize($backHref) ?>" class="btn btn-outline">&larr; Back</a>
        </div>
        <div class="map-toolbar">
            <div class="map-filters">
                <label class="checkbox-label">
                    <input type="checkbox" id="onlineOnlyFilter">
                    <span>Online riders only</span>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" id="showRouteFilter" checked>
                    <span>Show route when rider selected</span>
                </label>
                <button class="btn btn-sm btn-outline" id="fitBoundsBtn">Fit All Riders</button>
                <button class="btn btn-sm btn-primary" id="refreshMapBtn">Refresh</button>
            </div>
            <div class="map-legend">
                <span class="legend-item"><span class="legend-dot online"></span> Online</span>
                <span class="legend-item"><span class="legend-dot offline"></span> Offline</span>
                <span class="legend-item"><span class="legend-line route"></span> Route</span>
            </div>
        </div>

        <div id="mapEmptyHint" class="map-empty-hint hidden"></div>

        <div class="map-layout">
            <div id="liveMap" class="map-container full-map"
                 data-api="<?= baseUrl('api/get_locations.php') ?>"
                 data-focus-rider="<?= $focusRiderId ?>"
                 data-focus-parcel="<?= $focusParcelId ?>"></div>
            <div class="map-sidebar" id="riderListPanel">
                <h3>Riders</h3>
                <div id="riderList" class="rider-list"></div>
            </div>
        </div>
</div>

<link rel="stylesheet" href="<?= assetUrl('vendor/leaflet/leaflet.css') ?>">
<script src="<?= assetUrl('vendor/leaflet/leaflet.js') ?>"></script>

<?php require __DIR__ . '/../includes/app-shell-end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
