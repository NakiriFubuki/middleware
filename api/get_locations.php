<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireAdmin();

$riderId = isset($_GET['rider_id']) ? (int) $_GET['rider_id'] : null;
$onlineOnly = isset($_GET['online_only']) && $_GET['online_only'] === '1';

if ($riderId) {
    $rider = getRiderById($riderId);
    if (!$rider) {
        jsonResponse(['success' => false, 'message' => 'Rider not found.'], 404);
    }

    $limit = min(5000, max(50, (int) ($_GET['limit'] ?? 2000)));
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $requestedParcelId = isset($_GET['parcel_id']) ? (int) $_GET['parcel_id'] : null;

    if (!empty($_GET['today'])) {
        $today = date('Y-m-d');
        $dateFrom = $today;
        $dateTo = $today;
    }

    $activeParcelId = getRiderActiveRouteParcelId($riderId);
    $routeParcelId = resolveRiderRouteParcelId($riderId, $requestedParcelId ?: null);
    $history = getRiderLocationHistory($riderId, $dateFrom, $dateTo, $limit, $routeParcelId);
    $routeParcels = getRiderRouteParcels($riderId);

    jsonResponse([
        'success' => true,
        'rider' => array_merge($rider, [
            'active_parcel_id' => $activeParcelId,
        ]),
        'current' => getRiderCurrentLocation($riderId),
        'history' => $history,
        'route_points' => count($history),
        'route_parcels' => $routeParcels,
        'route_dates' => $routeParcels,
        'active_parcel_id' => $activeParcelId,
        'route_parcel_id' => $routeParcelId,
    ]);
}

$riders = getRidersWithLocations();

if ($onlineOnly) {
    $riders = array_values(array_filter($riders, function ($r) {
        return (int) $r['is_online'] === 1;
    }));
}

$locations = array_map(function ($r) {
    return [
        'id' => (int) $r['id'],
        'rider_code' => $r['rider_code'],
        'full_name' => $r['full_name'],
        'phone' => $r['phone'],
        'is_online' => (bool) $r['is_online'],
        'latitude' => $r['last_latitude'] ? (float) $r['last_latitude'] : null,
        'longitude' => $r['last_longitude'] ? (float) $r['last_longitude'] : null,
        'last_update' => $r['last_location_at'],
        'profile_photo' => $r['profile_photo'],
        'active_parcel' => $r['active_parcel'] ?? null,
        'active_parcel_id' => !empty($r['active_parcel_id']) ? (int) $r['active_parcel_id'] : null,
        'has_location' => $r['last_latitude'] !== null && $r['last_longitude'] !== null
    ];
}, $riders);

$withGps = array_values(array_filter($locations, function ($l) {
    return $l['has_location'];
}));

jsonResponse(['success' => true, 'locations' => $withGps, 'riders' => $locations]);
