<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireRider();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$rider = getRiderProfile();
if (!$rider) {
    jsonResponse(['success' => false, 'message' => 'Rider profile not found.']);
}

$parcelId = (int) ($_POST['parcel_id'] ?? 0);
if (!$parcelId) {
    jsonResponse(['success' => false, 'message' => 'Parcel ID is required.']);
}

$parcel = getParcelById($parcelId);
if (!$parcel || (int) $parcel['assigned_rider_id'] !== (int) $rider['id']) {
    jsonResponse(['success' => false, 'message' => 'Invalid parcel assignment.']);
}

$manageAccess = getRiderParcelManageAccess($parcel);
if (!$manageAccess['allowed']) {
    jsonResponse(['success' => false, 'message' => $manageAccess['message']]);
}

if (!in_array($parcel['status'], ['out_for_delivery', 'delivered'], true)) {
    jsonResponse(['success' => false, 'message' => 'Photos can only be uploaded during or after delivery.']);
}

if (!empty($_POST['image_data'])) {
    $result = handleBase64ImageUpload($_POST['image_data'], $parcelId, (int) $rider['id']);
    jsonResponse($result);
}

if (!empty($_FILES['photo'])) {
    $result = uploadDeliveryPhoto($_FILES['photo'], $parcelId, (int) $rider['id']);
    jsonResponse($result);
}

jsonResponse(['success' => false, 'message' => 'No photo provided.']);
