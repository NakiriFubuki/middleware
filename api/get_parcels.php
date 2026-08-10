<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();

$filters = [
    'status' => $_GET['status'] ?? null,
    'search' => $_GET['search'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null,
];

$page = max(1, (int) ($_GET['page'] ?? 1));

if (isRider()) {
    $rider = getRiderProfile();
    if (!$rider) {
        jsonResponse(['success' => false, 'message' => 'Rider not found.']);
    }
    $filters['rider_id'] = (int) $rider['id'];
}

$result = getParcels(array_filter($filters), $page, (int) ($_GET['per_page'] ?? 15));

jsonResponse([
    'success' => true,
    'parcels' => $result['parcels'],
    'pagination' => $result['pagination']
]);
