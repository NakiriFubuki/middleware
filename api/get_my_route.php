<?php
require_once __DIR__ . '/bootstrap.php';

requireRider();

$rider = getRiderProfile();
if (!$rider) {
    jsonResponse(['success' => false, 'message' => 'Rider profile not found.'], 404);
}

$riderId = (int) $rider['id'];
$requestedParcelId = isset($_GET['parcel_id']) ? (int) $_GET['parcel_id'] : null;
$parcelId = resolveRiderRouteParcelId($riderId, $requestedParcelId);

if ($requestedParcelId && !$parcelId) {
    jsonResponse(['success' => false, 'message' => 'Parcel route not found.'], 404);
}

$parcel = null;
if ($parcelId) {
    $parcel = getParcelById($parcelId);
    if (!$parcel || (int) $parcel['assigned_rider_id'] !== $riderId) {
        jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
    }
}

$limit = min(5000, max(50, (int) ($_GET['limit'] ?? 2000)));
$history = $parcelId
    ? getRiderLocationHistory($riderId, null, null, $limit, $parcelId)
    : [];

jsonResponse([
    'success' => true,
    'parcel_id' => $parcelId,
    'tracking_number' => $parcel['tracking_number'] ?? null,
    'history' => $history,
    'route_points' => count($history),
    'current' => getRiderCurrentLocation($riderId),
]);
