<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireAdmin();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$parcelId = (int) ($data['parcel_id'] ?? 0);
$riderId = (int) ($data['rider_id'] ?? 0);

if (!$parcelId || !$riderId) {
    jsonResponse(['success' => false, 'message' => 'Parcel and rider are required.']);
}

$result = assignParcel($parcelId, $riderId, currentUserId());
jsonResponse($result);
