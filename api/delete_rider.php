<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireAdmin();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$riderId = (int) ($data['rider_id'] ?? $data['id'] ?? 0);

if (!$riderId) {
    jsonResponse(['success' => false, 'message' => 'Rider ID is required.']);
}

$result = deleteRider($riderId, currentUserId());
jsonResponse($result);
