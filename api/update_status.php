<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$parcelId = (int) ($data['parcel_id'] ?? 0);
$status = $data['status'] ?? '';
$remarks = $data['remarks'] ?? null;

if (!$parcelId || !$status) {
    jsonResponse(['success' => false, 'message' => 'Parcel ID and status are required.']);
}

if (isRider()) {
    $rider = getRiderProfile();
    if (!$rider) {
        jsonResponse(['success' => false, 'message' => 'Rider profile not found.']);
    }

    $parcel = getParcelById($parcelId);
    if (!$parcel) {
        jsonResponse(['success' => false, 'message' => 'Parcel not found.']);
    }

    $manageAccess = getRiderParcelManageAccess($parcel);
    if (!$manageAccess['allowed']) {
        jsonResponse(['success' => false, 'message' => $manageAccess['message']]);
    }

    $result = updateParcelStatus($parcelId, $status, $remarks, (int) $rider['id'], currentUserId());
} elseif (isAdmin()) {
    $parcel = getParcelById($parcelId);
    if (!$parcel) {
        jsonResponse(['success' => false, 'message' => 'Parcel not found.']);
    }
    $riderId = (int) ($parcel['assigned_rider_id'] ?? 0);
    $result = updateParcelStatus($parcelId, $status, $remarks, $riderId, currentUserId());
} else {
    jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
}

jsonResponse($result);
