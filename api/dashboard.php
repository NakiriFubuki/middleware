<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();

if (isAdmin()) {
    $stats = getDashboardStats();
    $recentParcels = getParcels([], 1, 5);

    jsonResponse([
        'success' => true,
        'stats' => $stats,
        'recent_parcels' => $recentParcels['parcels']
    ]);
}

if (isRider()) {
    $rider = getRiderProfile();
    if (!$rider) {
        jsonResponse(['success' => false, 'message' => 'Rider not found.']);
    }

    $stats = getRiderStats((int) $rider['id']);
    $assigned = getRiderParcels((int) $rider['id'], null, 1, 10);

    jsonResponse([
        'success' => true,
        'stats' => $stats,
        'is_online' => (bool) $rider['is_online'],
        'last_location' => [
            'latitude' => $rider['last_latitude'],
            'longitude' => $rider['last_longitude'],
            'updated_at' => $rider['last_location_at']
        ],
        'assigned_parcels' => $assigned['parcels']
    ]);
}

jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
