<?php
/**
 * Report Generation Functions
 */

function getRiderPerformanceReport(?string $dateFrom = null, ?string $dateTo = null): array
{
    $pdo = db();
    $params = [];
    $whereClause = '';
    if ($dateFrom || $dateTo) {
        $conditions = ["p.status IN ('delivered', 'failed')"];
        if ($dateFrom) {
            $conditions[] = 'DATE(p.updated_at) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $conditions[] = 'DATE(p.updated_at) <= ?';
            $params[] = $dateTo;
        }
        $whereClause = ' AND ' . implode(' AND ', $conditions);
    } else {
        $whereClause = " AND p.status IN ('delivered', 'failed')";
    }

    $stmt = $pdo->prepare(
        "SELECT r.rider_code, u.full_name,
                COUNT(p.id) AS total_deliveries,
                SUM(CASE WHEN p.status = 'delivered' THEN 1 ELSE 0 END) AS successful,
                SUM(CASE WHEN p.status = 'failed' THEN 1 ELSE 0 END) AS failed,
                ROUND(SUM(CASE WHEN p.status = 'delivered' THEN 1 ELSE 0 END) / NULLIF(COUNT(p.id), 0) * 100, 1) AS success_rate
         FROM riders r
         JOIN users u ON u.id = r.user_id
         LEFT JOIN parcels p ON p.assigned_rider_id = r.id{$whereClause}
         GROUP BY r.id, r.rider_code, u.full_name
         ORDER BY successful DESC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getParcelDeliveryReport(?string $dateFrom = null, ?string $dateTo = null, ?string $status = null): array
{
    $pdo = db();
    $where = [];
    $params = [];

    if ($dateFrom) {
        $where[] = 'DATE(p.created_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = 'DATE(p.created_at) <= ?';
        $params[] = $dateTo;
    }
    if ($status) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare(
        "SELECT p.tracking_number, p.sender_name, p.receiver_name, p.delivery_address,
                p.status, p.delivery_fee, p.created_at, p.delivered_at,
                u.full_name AS rider_name, r.rider_code
         FROM parcels p
         LEFT JOIN riders r ON r.id = p.assigned_rider_id
         LEFT JOIN users u ON u.id = r.user_id
         $whereClause
         ORDER BY p.created_at DESC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getDailyDeliveriesReport(?string $date = null): array
{
    $date = $date ?? date('Y-m-d');
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT p.tracking_number, p.receiver_name, p.delivery_address, p.status,
                p.delivered_at, u.full_name AS rider_name
         FROM parcels p
         LEFT JOIN riders r ON r.id = p.assigned_rider_id
         LEFT JOIN users u ON u.id = r.user_id
         WHERE DATE(p.created_at) = ? OR DATE(p.delivered_at) = ?
         ORDER BY p.created_at DESC"
    );
    $stmt->execute([$date, $date]);
    return $stmt->fetchAll();
}

function getMonthlyDeliveriesReport(int $year, int $month): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT DATE(p.delivered_at) AS delivery_date,
                COUNT(*) AS total,
                SUM(CASE WHEN p.status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN p.status = 'failed' THEN 1 ELSE 0 END) AS failed,
                SUM(p.delivery_fee) AS total_fees
         FROM parcels p
         WHERE YEAR(p.delivered_at) = ? AND MONTH(p.delivered_at) = ?
         GROUP BY DATE(p.delivered_at)
         ORDER BY delivery_date ASC"
    );
    $stmt->execute([$year, $month]);
    return $stmt->fetchAll();
}

function getFailedDeliveriesReport(?string $dateFrom = null, ?string $dateTo = null): array
{
    $pdo = db();
    $where = ["p.status = 'failed'"];
    $params = [];

    if ($dateFrom) {
        $where[] = 'DATE(p.updated_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = 'DATE(p.updated_at) <= ?';
        $params[] = $dateTo;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT p.tracking_number, p.receiver_name, p.receiver_phone, p.delivery_address,
                p.updated_at, u.full_name AS rider_name,
                (SELECT remarks FROM parcel_status_history WHERE parcel_id = p.id AND status = 'failed' ORDER BY created_at DESC LIMIT 1) AS failure_reason
         FROM parcels p
         LEFT JOIN riders r ON r.id = p.assigned_rider_id
         LEFT JOIN users u ON u.id = r.user_id
         $whereClause
         ORDER BY p.updated_at DESC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function calculateRouteDistanceKm(array $points): float
{
    if (count($points) < 2) {
        return 0.0;
    }

    $totalMeters = 0.0;
    for ($i = 1, $count = count($points); $i < $count; $i++) {
        $totalMeters += locationDistanceMeters(
            (float) $points[$i - 1]['latitude'],
            (float) $points[$i - 1]['longitude'],
            (float) $points[$i]['latitude'],
            (float) $points[$i]['longitude']
        );
    }

    return round($totalMeters / 1000, 2);
}

function attachRouteMetrics(array $orders): array
{
    if (empty($orders)) {
        return $orders;
    }

    $parcelIds = array_values(array_unique(array_map(static function (array $order): int {
        return (int) $order['id'];
    }, $orders)));

    $placeholders = implode(',', array_fill(0, count($parcelIds), '?'));
    $stmt = db()->prepare(
        "SELECT parcel_id, latitude, longitude, created_at
         FROM rider_locations
         WHERE parcel_id IN ($placeholders)
         ORDER BY parcel_id ASC, created_at ASC"
    );
    $stmt->execute($parcelIds);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int) $row['parcel_id']][] = $row;
    }

    foreach ($orders as &$order) {
        $points = $grouped[(int) $order['id']] ?? [];
        $order['route_points'] = count($points);
        $order['route_distance_km'] = calculateRouteDistanceKm($points);
    }
    unset($order);

    return $orders;
}

function getOrderDeliveryReport(
    ?string $dateFrom = null,
    ?string $dateTo = null,
    ?string $search = null,
    ?string $status = null
): array {
    $pdo = db();
    $where = ['1 = 1'];
    $params = [];

    if ($dateFrom) {
        $where[] = 'DATE(COALESCE(p.delivered_at, p.updated_at, p.created_at)) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = 'DATE(COALESCE(p.delivered_at, p.updated_at, p.created_at)) <= ?';
        $params[] = $dateTo;
    }
    if ($status) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }
    if ($search) {
        $where[] = '(p.tracking_number LIKE ? OR p.receiver_name LIKE ? OR p.receiver_phone LIKE ?
                      OR p.delivery_address LIKE ? OR p.parcel_description LIKE ?
                      OR u.full_name LIKE ? OR r.rider_code LIKE ?)';
        $like = '%' . $search . '%';
        $params = array_merge($params, array_fill(0, 7, $like));
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT p.id, p.tracking_number, p.receiver_name, p.receiver_phone,
                p.delivery_address, p.parcel_description, p.status,
                p.created_at, p.delivered_at, p.updated_at, p.assigned_rider_id,
                u.full_name AS rider_name, r.rider_code
         FROM parcels p
         LEFT JOIN riders r ON r.id = p.assigned_rider_id
         LEFT JOIN users u ON u.id = r.user_id
         WHERE $whereClause
         ORDER BY COALESCE(p.delivered_at, p.updated_at, p.created_at) DESC
         LIMIT 500"
    );
    $stmt->execute($params);

    return attachRouteMetrics($stmt->fetchAll());
}

function formatRouteDistance(?float $km, int $points = 0): string
{
    if ($points < 2 || $km === null || $km <= 0) {
        return '—';
    }

    return number_format($km, 2) . ' km';
}

function generateReportPdf(string $title, array $headers, array $rows): void
{
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($title) ?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            h1 { font-size: 18px; margin-bottom: 5px; }
            .meta { color: #666; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #f5f5f5; }
            @media print { body { margin: 0; } }
        </style>
    </head>
    <body onload="window.print()">
        <h1><?= htmlspecialchars($title) ?></h1>
        <p class="meta">Generated: <?= date('M d, Y h:i A') ?></p>
        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $h): ?>
                        <th><?= htmlspecialchars($h) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= htmlspecialchars((string) $cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
