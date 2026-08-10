<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireRider();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$parcelId = (int) ($data['parcel_id'] ?? 0);

if (!$parcelId) {
    jsonResponse(['success' => false, 'message' => 'Parcel ID is required.']);
}

$rider = getRiderProfile();
if (!$rider) {
    jsonResponse(['success' => false, 'message' => 'Rider profile not found.']);
}

$result = startDelivery($parcelId, (int) $rider['id'], currentUserId());
jsonResponse($result);
