<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();

$parcelId = (int) ($_GET['parcel_id'] ?? 0);
if (!$parcelId) {
    jsonResponse(['success' => false, 'message' => 'Parcel ID is required.']);
}

$parcel = getParcelById($parcelId);
if (!$parcel) {
    jsonResponse(['success' => false, 'message' => 'Parcel not found.']);
}

if (isRider()) {
    $rider = getRiderProfile();
    if (!$rider || (int) $parcel['assigned_rider_id'] !== (int) $rider['id']) {
        jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
    }
}

$history = getParcelHistory($parcelId);
$photos = getParcelPhotos($parcelId);

jsonResponse([
    'success' => true,
    'parcel' => $parcel,
    'history' => $history,
    'photos' => $photos
]);
