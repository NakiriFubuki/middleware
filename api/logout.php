<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

requireLogin();
requireCsrf();

logoutUser();

jsonResponse(['success' => true, 'message' => 'Logged out.', 'redirect' => baseUrl('login.php')]);
