<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = sanitizeInput($_POST);
requireCsrf();

$result = registerRider($data);
jsonResponse($result);
