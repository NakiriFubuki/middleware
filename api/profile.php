<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = currentUserId();

if (!empty($data['change_password'])) {
    $result = changePassword(
        $userId,
        $data['current_password'] ?? '',
        $data['new_password'] ?? ''
    );
    jsonResponse($result);
}

if (!empty($_FILES['profile_photo'])) {
    $result = uploadProfilePhoto($_FILES['profile_photo'], $userId);
    jsonResponse($result);
}

$profileData = sanitizeInput($data);
$result = updateRiderProfile($userId, $profileData);
jsonResponse($result);
