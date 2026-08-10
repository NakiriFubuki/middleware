<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireAdmin();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int) ($data['id'] ?? 0);

if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Parcel ID is required.']);
}

$result = deleteParcel($id, currentUserId());
jsonResponse($result);
