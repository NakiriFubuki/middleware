<?php
/**
 * Parcel Management Functions
 */

function getParcelById(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, r.rider_code, u.full_name AS rider_name
         FROM parcels p
         LEFT JOIN riders r ON r.id = p.assigned_rider_id
         LEFT JOIN users u ON u.id = r.user_id
         WHERE p.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getParcelByTracking(string $tracking): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, r.rider_code, u.full_name AS rider_name
         FROM parcels p
         LEFT JOIN riders r ON r.id = p.assigned_rider_id
         LEFT JOIN users u ON u.id = r.user_id
         WHERE p.tracking_number = ?'
    );
    $stmt->execute([$tracking]);
    return $stmt->fetch() ?: null;
}

function getParcels(array $filters = [], int $page = 1, int $perPage = 15): array
{
    $pdo = db();
    $where = [];
    $params = [];

    if (!empty($filters['status'])) {
        $where[] = 'p.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['search'])) {
        $where[] = '(p.tracking_number LIKE ? OR p.sender_name LIKE ? OR p.receiver_name LIKE ? OR p.sender_phone LIKE ? OR p.receiver_phone LIKE ?)';
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search, $search, $search]);
    }
    if (!empty($filters['rider_id'])) {
        $where[] = 'p.assigned_rider_id = ?';
        $params[] = $filters['rider_id'];
    }
    if (!empty($filters['date_from'])) {
        $where[] = 'DATE(p.created_at) >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[] = 'DATE(p.created_at) <= ?';
        $params[] = $filters['date_to'];
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM parcels p $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pagination = paginate($total, $page, $perPage);

    $stmt = $pdo->prepare(
        "SELECT p.*, r.rider_code, u.full_name AS rider_name
         FROM parcels p
         LEFT JOIN riders r ON r.id = p.assigned_rider_id
         LEFT JOIN users u ON u.id = r.user_id
         $whereClause
         ORDER BY p.created_at DESC
         LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
    );
    $stmt->execute($params);

    return [
        'parcels' => $stmt->fetchAll(),
        'pagination' => $pagination
    ];
}

function getRiderParcels(int $riderId, ?string $status = null, int $page = 1, int $perPage = 15): array
{
    $filters = ['rider_id' => $riderId];
    if ($status) {
        $filters['status'] = $status;
    }
    return getParcels($filters, $page, $perPage);
}

function createParcel(array $data, int $createdBy): array
{
    $errors = validateRequired([
        'receiver_name' => 'Receiver name',
        'delivery_address' => 'Address',
    ], $data);

    if (!isset($data['parcel_weight']) || $data['parcel_weight'] === '') {
        $errors[] = 'Weight is required.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(' ', $errors)];
    }

    $weight = (float) $data['parcel_weight'];
    if ($weight < 0) {
        return ['success' => false, 'message' => 'Weight must be 0 or greater.'];
    }

    $tracking = generateTrackingNumber();
    $pdo = db();

    $receiverName = $data['receiver_name'];
    $deliveryAddress = $data['delivery_address'];
    $remarks = trim($data['parcel_description'] ?? '') ?: null;

    $stmt = $pdo->prepare(
        'INSERT INTO parcels (tracking_number, sender_name, sender_phone, receiver_name, receiver_phone,
         pickup_address, delivery_address, parcel_description, parcel_weight, delivery_fee,
         assigned_rider_id, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $riderId = !empty($data['assigned_rider_id']) ? (int) $data['assigned_rider_id'] : null;
    $status = 'pending';

    $stmt->execute([
        $tracking,
        '-',
        '-',
        $receiverName,
        '-',
        '-',
        $deliveryAddress,
        $remarks,
        $weight,
        0,
        $riderId,
        $status,
        $createdBy
    ]);

    $parcelId = (int) $pdo->lastInsertId();

    addParcelStatusHistory($parcelId, 'pending', 'Parcel created', null);

    if ($riderId) {
        addParcelStatusHistory($parcelId, 'pending', 'Assigned to rider', $riderId);
        logActivity($createdBy, 'parcel_assign', "Assigned parcel $tracking to rider ID $riderId");
    }

    logActivity($createdBy, 'parcel_create', "Created parcel $tracking");

    return ['success' => true, 'message' => 'Parcel created successfully.', 'parcel_id' => $parcelId, 'tracking_number' => $tracking];
}

function updateParcel(int $id, array $data, int $updatedBy): array
{
    $parcel = getParcelById($id);
    if (!$parcel) {
        return ['success' => false, 'message' => 'Parcel not found.'];
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'UPDATE parcels SET sender_name = ?, sender_phone = ?, receiver_name = ?, receiver_phone = ?,
         pickup_address = ?, delivery_address = ?, parcel_description = ?, parcel_weight = ?,
         delivery_fee = ?, assigned_rider_id = ?, updated_at = NOW()
         WHERE id = ?'
    );

    $riderId = !empty($data['assigned_rider_id']) ? (int) $data['assigned_rider_id'] : null;

    $stmt->execute([
        $data['sender_name'],
        $data['sender_phone'],
        $data['receiver_name'],
        $data['receiver_phone'],
        $data['pickup_address'],
        $data['delivery_address'],
        $data['parcel_description'] ?? null,
        (float) ($data['parcel_weight'] ?? 0),
        (float) ($data['delivery_fee'] ?? 0),
        $riderId,
        $id
    ]);

    if ($riderId && $riderId !== (int) $parcel['assigned_rider_id']) {
        addParcelStatusHistory($id, $parcel['status'], 'Reassigned to rider', $riderId);
        logActivity($updatedBy, 'parcel_assign', "Reassigned parcel {$parcel['tracking_number']} to rider ID $riderId");
    }

    logActivity($updatedBy, 'parcel_update', "Updated parcel {$parcel['tracking_number']}");

    return ['success' => true, 'message' => 'Parcel updated successfully.'];
}

function deleteParcel(int $id, int $deletedBy): array
{
    $parcel = getParcelById($id);
    if (!$parcel) {
        return ['success' => false, 'message' => 'Parcel not found.'];
    }

    $stmt = db()->prepare('DELETE FROM parcels WHERE id = ?');
    $stmt->execute([$id]);

    logActivity($deletedBy, 'parcel_delete', "Deleted parcel {$parcel['tracking_number']}");

    return ['success' => true, 'message' => 'Parcel deleted successfully.'];
}

function assignParcel(int $parcelId, int $riderId, int $assignedBy): array
{
    $parcel = getParcelById($parcelId);
    if (!$parcel) {
        return ['success' => false, 'message' => 'Parcel not found.'];
    }

    $stmt = db()->prepare('UPDATE parcels SET assigned_rider_id = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$riderId, $parcelId]);

    addParcelStatusHistory($parcelId, $parcel['status'], 'Assigned to rider', $riderId);
    logActivity($assignedBy, 'parcel_assign', "Assigned parcel {$parcel['tracking_number']} to rider ID $riderId");

    return ['success' => true, 'message' => 'Parcel assigned successfully.'];
}

function updateParcelStatus(int $parcelId, string $status, ?string $remarks, int $riderId, int $userId): array
{
    $validStatuses = ['pending', 'out_for_delivery', 'delivered', 'failed'];
    if (!in_array($status, $validStatuses, true)) {
        return ['success' => false, 'message' => 'Invalid status.'];
    }

    $parcel = getParcelById($parcelId);
    if (!$parcel) {
        return ['success' => false, 'message' => 'Parcel not found.'];
    }

    if ((int) $parcel['assigned_rider_id'] !== $riderId) {
        return ['success' => false, 'message' => 'This parcel is not assigned to you.'];
    }

    if ($status === 'delivered' && !parcelHasDeliveryPhoto($parcelId)) {
        return [
            'success' => false,
            'message' => 'Please upload a delivery photo before marking as delivered.',
            'requires_photo' => true,
        ];
    }

    $pdo = db();
    $deliveredAt = $status === 'delivered' ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare(
        'UPDATE parcels SET status = ?, delivered_at = COALESCE(?, delivered_at), updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$status, $deliveredAt, $parcelId]);

    addParcelStatusHistory($parcelId, $status, $remarks, $riderId);

    if ($status === 'delivered') {
        $pdo->prepare('UPDATE riders SET total_deliveries = total_deliveries + 1 WHERE id = ?')
            ->execute([$riderId]);
    }

    logActivity($userId, 'parcel_status_update', "Updated parcel {$parcel['tracking_number']} to $status");

    return ['success' => true, 'message' => 'Status updated successfully.', 'requires_photo' => in_array($status, ['delivered', 'out_for_delivery'], true)];
}

function startDelivery(int $parcelId, int $riderId, int $userId): array
{
    $parcel = getParcelById($parcelId);
    if (!$parcel) {
        return ['success' => false, 'message' => 'Parcel not found.'];
    }

    if ((int) $parcel['assigned_rider_id'] !== $riderId) {
        return ['success' => false, 'message' => 'This parcel is not assigned to you.'];
    }

    if (!in_array($parcel['status'], ['pending', 'out_for_delivery'], true)) {
        return ['success' => false, 'message' => 'This parcel cannot be started for delivery.'];
    }

    updateRiderOnlineStatus($riderId, true, $userId);

    if ($parcel['status'] === 'pending') {
        $result = updateParcelStatus($parcelId, 'out_for_delivery', 'Delivery started', $riderId, $userId);
        if (!$result['success']) {
            return $result;
        }
    } else {
        logActivity($userId, 'delivery_resume', "Resumed delivery for {$parcel['tracking_number']}");
    }

    return [
        'success' => true,
        'message' => 'Delivery started! GPS tracking is active.',
        'tracking_number' => $parcel['tracking_number'],
        'status' => 'out_for_delivery'
    ];
}

function getActiveDeliveries(): array
{
    $stmt = db()->query(
        "SELECT p.id, p.tracking_number, p.receiver_name, p.receiver_phone, p.delivery_address,
                p.status, p.updated_at,
                r.id AS rider_id, r.rider_code, r.is_online,
                r.last_latitude, r.last_longitude, r.last_location_at,
                u.full_name AS rider_name
         FROM parcels p
         JOIN riders r ON r.id = p.assigned_rider_id
         JOIN users u ON u.id = r.user_id
         WHERE p.status = 'out_for_delivery'
         ORDER BY p.updated_at DESC"
    );
    return $stmt->fetchAll();
}

function getRecentDeliveryUpdates(int $limit = 25): array
{
    $stmt = db()->prepare(
        "SELECT psh.status, psh.remarks, psh.created_at,
                p.tracking_number, p.receiver_name, u.full_name AS rider_name, r.rider_code
         FROM parcel_status_history psh
         JOIN parcels p ON p.id = psh.parcel_id
         LEFT JOIN riders r ON r.id = psh.rider_id
         LEFT JOIN users u ON u.id = r.user_id
         ORDER BY psh.created_at DESC
         LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getRidersCurrentPositions(int $limit = 15): array
{
    $stmt = db()->prepare(
        "SELECT r.id AS rider_id, r.last_latitude AS latitude, r.last_longitude AS longitude,
                r.last_location_at AS created_at, r.rider_code, u.full_name AS rider_name
         FROM riders r
         JOIN users u ON u.id = r.user_id
         WHERE u.is_active = 1 AND r.is_online = 1
           AND r.last_latitude IS NOT NULL AND r.last_longitude IS NOT NULL
         ORDER BY r.last_location_at DESC
         LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getLiveTrackingData(): array
{
    return [
        'active_deliveries' => getActiveDeliveries(),
        'online_riders' => getOnlineRidersWithLocation(),
        'status_updates' => getRecentDeliveryUpdates(20),
        'location_updates' => getRidersCurrentPositions(15),
        'stats' => getDashboardStats(),
        'server_time' => date('Y-m-d H:i:s')
    ];
}

function addParcelStatusHistory(int $parcelId, string $status, ?string $remarks, ?int $riderId): void
{
    $stmt = db()->prepare(
        'INSERT INTO parcel_status_history (parcel_id, status, remarks, rider_id) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$parcelId, $status, $remarks, $riderId]);
}

function getParcelHistory(int $parcelId): array
{
    $stmt = db()->prepare(
        'SELECT psh.*, u.full_name AS rider_name
         FROM parcel_status_history psh
         LEFT JOIN riders r ON r.id = psh.rider_id
         LEFT JOIN users u ON u.id = r.user_id
         WHERE psh.parcel_id = ?
         ORDER BY psh.created_at ASC'
    );
    $stmt->execute([$parcelId]);
    return $stmt->fetchAll();
}

function getParcelPhotos(int $parcelId): array
{
    $stmt = db()->prepare(
        'SELECT dp.*, u.full_name AS rider_name
         FROM delivery_photos dp
         JOIN riders r ON r.id = dp.rider_id
         JOIN users u ON u.id = r.user_id
         WHERE dp.parcel_id = ?
         ORDER BY dp.created_at DESC'
    );
    $stmt->execute([$parcelId]);
    return $stmt->fetchAll();
}

function parcelHasDeliveryPhoto(int $parcelId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM delivery_photos WHERE parcel_id = ?');
    $stmt->execute([$parcelId]);
    return (int) $stmt->fetchColumn() > 0;
}

function getParcelStatusChangedAt(int $parcelId, string $status): ?string
{
    $stmt = db()->prepare(
        'SELECT created_at FROM parcel_status_history
         WHERE parcel_id = ? AND status = ?
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $stmt->execute([$parcelId, $status]);
    $changedAt = $stmt->fetchColumn();

    return $changedAt ?: null;
}

function getRiderParcelManageAccess(array $parcel): array
{
    $status = $parcel['status'] ?? '';

    if (in_array($status, ['pending', 'out_for_delivery'], true)) {
        return [
            'allowed' => true,
            'expires_at' => null,
            'remaining_seconds' => null,
            'message' => '',
        ];
    }

    if (!in_array($status, ['delivered', 'failed'], true)) {
        return [
            'allowed' => true,
            'expires_at' => null,
            'remaining_seconds' => null,
            'message' => '',
        ];
    }

    $changedAt = getParcelStatusChangedAt((int) $parcel['id'], $status)
        ?? ($parcel['updated_at'] ?? null);

    if (!$changedAt) {
        return [
            'allowed' => true,
            'expires_at' => null,
            'remaining_seconds' => null,
            'message' => '',
        ];
    }

    $expiresAt = strtotime($changedAt) + (RIDER_PARCEL_MANAGE_MINUTES * 60);
    $remaining = $expiresAt - time();

    if ($remaining <= 0) {
        return [
            'allowed' => false,
            'expires_at' => $expiresAt,
            'remaining_seconds' => 0,
            'message' => 'This parcel can no longer be updated. The ' . RIDER_PARCEL_MANAGE_MINUTES . '-minute management window has expired.',
        ];
    }

    return [
        'allowed' => true,
        'expires_at' => $expiresAt,
        'remaining_seconds' => $remaining,
        'message' => '',
    ];
}

function getDashboardStats(): array
{
    $pdo = db();

    $stats = [];

    $stats['total_riders'] = (int) $pdo->query('SELECT COUNT(*) FROM riders')->fetchColumn();
    $stats['online_riders'] = (int) $pdo->query('SELECT COUNT(*) FROM riders WHERE is_online = 1')->fetchColumn();
    $stats['offline_riders'] = $stats['total_riders'] - $stats['online_riders'];
    $stats['total_parcels'] = (int) $pdo->query('SELECT COUNT(*) FROM parcels')->fetchColumn();
    $stats['pending_parcels'] = (int) $pdo->query("SELECT COUNT(*) FROM parcels WHERE status = 'pending'")->fetchColumn();
    $stats['out_for_delivery'] = (int) $pdo->query("SELECT COUNT(*) FROM parcels WHERE status = 'out_for_delivery'")->fetchColumn();
    $stats['delivered'] = (int) $pdo->query("SELECT COUNT(*) FROM parcels WHERE status = 'delivered'")->fetchColumn();
    $stats['failed'] = (int) $pdo->query("SELECT COUNT(*) FROM parcels WHERE status = 'failed'")->fetchColumn();

    return $stats;
}

function getRiderStats(int $riderId): array
{
    $pdo = db();
    $stats = [];

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = ?');
    $stmt->execute([$riderId]);
    $stats['total_assigned'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = ? AND status = 'pending'");
    $stmt->execute([$riderId]);
    $stats['pending'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = ? AND status = 'out_for_delivery'");
    $stmt->execute([$riderId]);
    $stats['out_for_delivery'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = ? AND status = 'delivered'");
    $stmt->execute([$riderId]);
    $stats['delivered'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = ? AND status = 'failed'");
    $stmt->execute([$riderId]);
    $stats['failed'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = ? AND status = 'delivered' AND DATE(delivered_at) = CURDATE()"
    );
    $stmt->execute([$riderId]);
    $stats['today_delivered'] = (int) $stmt->fetchColumn();

    return $stats;
}

function getTodayDeliveries(int $riderId): array
{
    $stmt = db()->prepare(
        "SELECT * FROM parcels WHERE assigned_rider_id = ? AND DATE(created_at) = CURDATE() ORDER BY created_at DESC"
    );
    $stmt->execute([$riderId]);
    return $stmt->fetchAll();
}

function getRiderDeliveryHistory(int $riderId, int $limit = 50): array
{
    $stmt = db()->prepare(
        "SELECT p.* FROM parcels p
         WHERE p.assigned_rider_id = ? AND p.status IN ('delivered', 'failed')
         ORDER BY p.updated_at DESC LIMIT ?"
    );
    $stmt->execute([$riderId, $limit]);
    return $stmt->fetchAll();
}
