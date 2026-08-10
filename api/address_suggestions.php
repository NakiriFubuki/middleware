<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireAdmin();

$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'receiver';
$limit = min(10, max(3, (int) ($_GET['limit'] ?? 8)));

if ($type === 'sender') {
    $sql = "SELECT sender_name AS name, sender_phone AS phone, pickup_address AS address,
                   MAX(created_at) AS last_used
            FROM parcels
            WHERE sender_name <> '-' AND pickup_address <> '-'";
    $params = [];

    if ($query !== '') {
        $sql .= ' AND (sender_name LIKE ? OR pickup_address LIKE ? OR sender_phone LIKE ?)';
        $like = '%' . $query . '%';
        $params = [$like, $like, $like];
    }

    $sql .= ' GROUP BY sender_name, sender_phone, pickup_address
              ORDER BY last_used DESC
              LIMIT ' . $limit;
} else {
    $sql = "SELECT receiver_name AS name, receiver_phone AS phone, delivery_address AS address,
                   MAX(created_at) AS last_used
            FROM parcels
            WHERE receiver_name <> '' AND delivery_address <> '' AND delivery_address <> '-'";
    $params = [];

    if ($query !== '') {
        $sql .= ' AND (receiver_name LIKE ? OR delivery_address LIKE ? OR receiver_phone LIKE ?)';
        $like = '%' . $query . '%';
        $params = [$like, $like, $like];
    }

    $sql .= ' GROUP BY receiver_name, receiver_phone, delivery_address
              ORDER BY last_used DESC
              LIMIT ' . $limit;
}

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'suggestions' => array_map(static function (array $row): array {
        return [
            'name' => $row['name'],
            'phone' => $row['phone'] ?? '',
            'address' => $row['address'],
            'label' => trim($row['name'] . ($row['phone'] && $row['phone'] !== '-' ? ' — ' . $row['phone'] : '')),
        ];
    }, $rows),
]);
