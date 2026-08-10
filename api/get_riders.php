<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireAdmin();

$search = $_GET['search'] ?? null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$onlineOnly = isset($_GET['online_only']) && $_GET['online_only'] === '1';

$result = getAllRiders($search, $page, 50);
$riders = $result['riders'];

if ($onlineOnly) {
    $riders = array_values(array_filter($riders, function ($r) {
        return (int) $r['is_online'] === 1;
    }));
}

jsonResponse([
    'success' => true,
    'riders' => $riders,
    'pagination' => $result['pagination']
]);
