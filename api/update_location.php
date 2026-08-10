<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireRider();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
$longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
$accuracy = isset($data['accuracy']) ? (float) $data['accuracy'] : null;

if ($latitude === null || $longitude === null) {
    jsonResponse(['success' => false, 'message' => 'Invalid coordinates.']);
}

if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    jsonResponse(['success' => false, 'message' => 'Coordinates out of range.']);
}

$rider = getRiderProfile(true);
if (!$rider) {
    jsonResponse(['success' => false, 'message' => 'Rider profile not found.']);
}

// Re-read online status directly from DB
$stmt = db()->prepare('SELECT is_online FROM riders WHERE id = ?');
$stmt->execute([(int) $rider['id']]);
$isOnline = (int) $stmt->fetchColumn();

if (!$isOnline) {
    jsonResponse(['success' => false, 'message' => 'You must be online to send location updates.']);
}

$result = saveRiderLocation((int) $rider['id'], $latitude, $longitude, $accuracy, currentUserId());

jsonResponse(array_merge($result, [
    'latitude' => $latitude,
    'longitude' => $longitude,
    'timestamp' => date('Y-m-d H:i:s')
]));
