<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireRider();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$isOnline = !empty($data['is_online']);

$rider = getRiderProfile();
if (!$rider) {
    jsonResponse(['success' => false, 'message' => 'Rider profile not found.']);
}

$result = updateRiderOnlineStatus((int) $rider['id'], $isOnline, currentUserId());

jsonResponse(array_merge($result, ['is_online' => $isOnline]));
