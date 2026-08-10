<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = sanitizeInput($_POST);
requireCsrf();

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$remember = !empty($data['remember']);

if (empty($username) || empty($password)) {
    jsonResponse(['success' => false, 'message' => 'Username and password are required.']);
}

$result = attemptLogin($username, $password);

if (!$result['success']) {
    jsonResponse(['success' => false, 'message' => $result['message']]);
}

loginUser($result['user'], $remember);

$redirect = $result['user']['role'] === 'admin'
    ? baseUrl('admin/dashboard.php')
    : baseUrl('rider/dashboard.php');

jsonResponse([
    'success' => true,
    'message' => 'Login successful.',
    'redirect' => $redirect,
    'user' => [
        'id' => $result['user']['id'],
        'name' => $result['user']['full_name'],
        'role' => $result['user']['role']
    ]
]);
