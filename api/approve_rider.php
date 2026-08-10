<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireAdmin();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = (int) ($data['user_id'] ?? 0);

if (!$userId) {
    jsonResponse(['success' => false, 'message' => 'User ID is required.']);
}

$result = approveRider($userId, currentUserId());
jsonResponse($result);
