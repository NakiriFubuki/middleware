<?php
/**
 * Rider Management Functions
 */

function riderPlateFromData(array $data): ?string
{
    $plate = trim($data['plate_number'] ?? $data['license_number'] ?? '');
    return $plate !== '' ? $plate : null;
}

function getAllRiders(?string $search = null, int $page = 1, int $perPage = 15): array
{
    $pdo = db();
    $where = '';
    $params = [];

    if ($search) {
        $where = 'WHERE u.full_name LIKE ? OR u.email LIKE ? OR r.rider_code LIKE ? OR u.phone LIKE ? OR u.username LIKE ?';
        $s = '%' . $search . '%';
        $params = [$s, $s, $s, $s, $s];
    }

    $whereClause = $where ? 'WHERE ' . substr($where, 6) : '';

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM riders r JOIN users u ON u.id = r.user_id $whereClause"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pagination = paginate($total, $page, $perPage);

    $stmt = $pdo->prepare(
        "SELECT r.*, u.full_name, u.email, u.username, u.phone, u.profile_photo, u.is_active, u.last_login,
                (SELECT COUNT(*) FROM parcels p
                 WHERE p.assigned_rider_id = r.id AND p.status = 'out_for_delivery') AS active_deliveries
         FROM riders r
         JOIN users u ON u.id = r.user_id
         $whereClause
         ORDER BY u.is_active DESC, u.full_name ASC
         LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
    );
    $stmt->execute($params);

    return [
        'riders' => $stmt->fetchAll(),
        'pagination' => $pagination
    ];
}

function getRiderById(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*, u.full_name, u.email, u.phone, u.profile_photo, u.is_active, u.last_login, u.username
         FROM riders r
         JOIN users u ON u.id = r.user_id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getActiveRiders(): array
{
    $stmt = db()->query(
        "SELECT r.id, r.rider_code, u.full_name
         FROM riders r
         JOIN users u ON u.id = r.user_id
         WHERE u.is_active = 1
         ORDER BY u.full_name ASC"
    );
    return $stmt->fetchAll();
}

function getOnlineRiders(): array
{
    $stmt = db()->query(
        'SELECT r.*, u.full_name, u.phone, u.profile_photo
         FROM riders r
         JOIN users u ON u.id = r.user_id
         WHERE r.is_online = 1 AND u.is_active = 1'
    );
    return $stmt->fetchAll();
}

function getOnlineRidersWithLocation(): array
{
    $stmt = db()->query(
        "SELECT r.id, r.rider_code, r.is_online,
                r.last_latitude, r.last_longitude, r.last_location_at,
                u.full_name, u.phone,
                (SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = r.id AND status = 'out_for_delivery') AS active_deliveries
         FROM riders r
         JOIN users u ON u.id = r.user_id
         WHERE u.is_active = 1 AND r.is_online = 1
         ORDER BY u.full_name ASC"
    );
    return $stmt->fetchAll();
}

function updateRiderOnlineStatus(int $riderId, bool $isOnline, int $userId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'UPDATE riders SET is_online = ?, last_online_at = NOW(), updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$isOnline ? 1 : 0, $riderId]);

    $status = $isOnline ? 'online' : 'offline';
    logActivity($userId, 'rider_status', "Rider went $status");

    return ['success' => true, 'message' => 'Status updated.', 'is_online' => $isOnline];
}

function getRiderActiveRouteParcelId(int $riderId): ?int
{
    $stmt = db()->prepare(
        "SELECT id FROM parcels
         WHERE assigned_rider_id = ? AND status = 'out_for_delivery'
         ORDER BY updated_at DESC
         LIMIT 1"
    );
    $stmt->execute([$riderId]);
    $id = $stmt->fetchColumn();

    return $id ? (int) $id : null;
}

function resolveRiderRouteParcelId(int $riderId, ?int $requestedParcelId = null): ?int
{
    if ($requestedParcelId) {
        $stmt = db()->prepare(
            'SELECT p.id
             FROM parcels p
             LEFT JOIN rider_locations rl ON rl.parcel_id = p.id AND rl.rider_id = ?
             WHERE p.id = ? AND (p.assigned_rider_id = ? OR rl.id IS NOT NULL)
             LIMIT 1'
        );
        $stmt->execute([$riderId, $requestedParcelId, $riderId]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    $active = getRiderActiveRouteParcelId($riderId);
    if ($active) {
        return $active;
    }

    $stmt = db()->prepare(
        'SELECT parcel_id FROM rider_locations
         WHERE rider_id = ? AND parcel_id IS NOT NULL
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $stmt->execute([$riderId]);
    $id = $stmt->fetchColumn();

    return $id ? (int) $id : null;
}

function saveRiderLocation(int $riderId, float $latitude, float $longitude, ?float $accuracy, int $userId): array
{
    $pdo = db();
    $parcelId = getRiderActiveRouteParcelId($riderId);

    $stmt = $pdo->prepare(
        'UPDATE riders SET last_latitude = ?, last_longitude = ?, last_location_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$latitude, $longitude, $riderId]);

    if (!$parcelId) {
        return [
            'success' => true,
            'message' => 'Location saved.',
            'recorded' => false,
            'parcel_id' => null,
        ];
    }

    $shouldRecord = true;
    $minRouteMeters = defined('GPS_ROUTE_MIN_METERS') ? (float) GPS_ROUTE_MIN_METERS : 10.0;

    if ($accuracy !== null && $accuracy > 75) {
        $shouldRecord = false;
    }

    $stmt = $pdo->prepare(
        'SELECT latitude, longitude, accuracy, created_at FROM rider_locations
         WHERE rider_id = ? AND parcel_id = ?
         ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([$riderId, $parcelId]);
    $last = $stmt->fetch();

    if ($shouldRecord && $last) {
        $seconds = max(1, time() - strtotime($last['created_at']));
        $meters = locationDistanceMeters(
            (float) $last['latitude'],
            (float) $last['longitude'],
            $latitude,
            $longitude
        );

        if ($meters < $minRouteMeters) {
            $shouldRecord = false;
        }

        $speed = $meters / $seconds;
        if ($speed > 45) {
            $shouldRecord = false;
        }
    }

    if ($shouldRecord) {
        $stmt = $pdo->prepare(
            'INSERT INTO rider_locations (rider_id, parcel_id, latitude, longitude, accuracy) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$riderId, $parcelId, $latitude, $longitude, $accuracy]);
    }

    return [
        'success' => true,
        'message' => 'Location saved.',
        'recorded' => $shouldRecord,
        'parcel_id' => $parcelId,
    ];
}

function locationDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function getRiderRouteParcels(int $riderId, int $limit = 30): array
{
    $stmt = db()->prepare(
        'SELECT p.id AS parcel_id, p.tracking_number, p.status, p.receiver_name,
                COUNT(rl.id) AS points,
                MIN(rl.created_at) AS route_started_at,
                MAX(rl.created_at) AS route_ended_at
         FROM rider_locations rl
         JOIN parcels p ON p.id = rl.parcel_id
         WHERE rl.rider_id = ?
         GROUP BY p.id, p.tracking_number, p.status, p.receiver_name
         ORDER BY MAX(rl.created_at) DESC
         LIMIT ?'
    );
    $stmt->execute([$riderId, $limit]);
    return $stmt->fetchAll();
}

function getRiderRouteDates(int $riderId, int $limit = 30): array
{
    return getRiderRouteParcels($riderId, $limit);
}

function getRiderCurrentLocation(int $riderId): ?array
{
    $stmt = db()->prepare(
        'SELECT last_latitude AS latitude, last_longitude AS longitude, last_location_at AS created_at
         FROM riders WHERE id = ? AND last_latitude IS NOT NULL AND last_longitude IS NOT NULL'
    );
    $stmt->execute([$riderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getRiderLocationHistory(int $riderId, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 500, ?int $parcelId = null): array
{
    $where = ['rider_id = ?'];
    $params = [$riderId];

    if ($parcelId) {
        $where[] = 'parcel_id = ?';
        $params[] = $parcelId;
    }

    if ($dateFrom) {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $dateTo;
    }

    $whereClause = implode(' AND ', $where);
    $params[] = $limit;

    $stmt = db()->prepare(
        "SELECT * FROM rider_locations WHERE $whereClause ORDER BY created_at ASC LIMIT ?"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getRidersWithLocations(): array
{
    $stmt = db()->query(
        "SELECT r.id, r.rider_code, r.is_online,
                r.last_latitude, r.last_longitude, r.last_location_at,
                u.full_name, u.phone, u.profile_photo,
                (SELECT tracking_number FROM parcels WHERE assigned_rider_id = r.id AND status = 'out_for_delivery' ORDER BY updated_at DESC LIMIT 1) AS active_parcel,
                (SELECT id FROM parcels WHERE assigned_rider_id = r.id AND status = 'out_for_delivery' ORDER BY updated_at DESC LIMIT 1) AS active_parcel_id
         FROM riders r
         JOIN users u ON u.id = r.user_id
         WHERE u.is_active = 1
         ORDER BY r.is_online DESC, u.full_name ASC"
    );
    return $stmt->fetchAll();
}

function updateRiderProfile(int $userId, array $data): array
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'UPDATE users SET full_name = ?, phone = ?, email = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([
        $data['full_name'],
        $data['phone'] ?? null,
        $data['email'],
        $userId
    ]);

    $plateNumber = riderPlateFromData($data);

    if (!empty($data['vehicle_type']) || $plateNumber !== null) {
        $rider = getRiderProfile();
        if ($rider) {
            $stmt = $pdo->prepare(
                'UPDATE riders SET vehicle_type = ?, license_number = ? WHERE user_id = ?'
            );
            $stmt->execute([
                $data['vehicle_type'] ?? $rider['vehicle_type'],
                $plateNumber ?? $rider['license_number'],
                $userId
            ]);
        }
    }

    logActivity($userId, 'profile_update', 'Profile updated');

    return ['success' => true, 'message' => 'Profile updated successfully.'];
}

function checkUserDuplicate(string $username, string $email): ?string
{
    $stmt = db()->prepare('SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $email]);
    $existing = $stmt->fetch();

    if (!$existing) {
        return null;
    }
    if ($existing['username'] === $username) {
        return 'Username "' . $username . '" is already taken. Please choose another.';
    }
    return 'This email is already registered. Please use another email.';
}

function generateRiderCode(): string
{
    $pdo = db();
    $stmt = $pdo->query("SELECT rider_code FROM riders ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();

    if ($last && preg_match('/RDR-(\d+)/', $last, $m)) {
        $num = (int) $m[1] + 1;
    } else {
        $num = 1;
    }

    return 'RDR-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
}

function registerRider(array $data): array
{
    $errors = validateRequired([
        'username' => 'Username',
        'email' => 'Email',
        'password' => 'Password',
        'full_name' => 'Full name',
    ], $data);

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(' ', $errors)];
    }

    if (!validateEmail($data['email'])) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    if (strlen($data['password']) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }

    if (($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
        return ['success' => false, 'message' => 'Username must be 3-50 characters (letters, numbers, underscore).'];
    }

    $pdo = db();

    $duplicate = checkUserDuplicate($data['username'], $data['email']);
    if ($duplicate) {
        return ['success' => false, 'message' => $duplicate];
    }

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $riderCode = generateRiderCode();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password, role, full_name, phone, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([
            $data['username'],
            $data['email'],
            $hash,
            'rider',
            $data['full_name'],
            $data['phone'] ?? null
        ]);

        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO riders (user_id, rider_code, vehicle_type, license_number)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $riderCode,
            $data['vehicle_type'] ?? 'Motorcycle',
            riderPlateFromData($data)
        ]);

        $pdo->commit();

        logActivity($userId, 'rider_register', "Rider registered: {$data['username']} ($riderCode), pending approval");

        return [
            'success' => true,
            'message' => 'Registration successful! Please wait for admin approval before logging in.',
            'pending' => true
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Rider registration failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

function getPendingRiders(): array
{
    $stmt = db()->query(
        "SELECT r.id AS rider_id, r.rider_code, r.vehicle_type, r.license_number, r.created_at AS registered_at,
                u.id AS user_id, u.username, u.email, u.full_name, u.phone
         FROM users u
         JOIN riders r ON r.user_id = u.id
         WHERE u.role = 'rider' AND u.is_active = 0
         ORDER BY u.created_at ASC"
    );
    return $stmt->fetchAll();
}

function countPendingRiders(): int
{
    return (int) db()->query(
        "SELECT COUNT(*) FROM users WHERE role = 'rider' AND is_active = 0"
    )->fetchColumn();
}

function approveRider(int $userId, int $adminId): array
{
    $stmt = db()->prepare(
        "SELECT u.*, r.rider_code FROM users u
         JOIN riders r ON r.user_id = u.id
         WHERE u.id = ? AND u.role = 'rider' AND u.is_active = 0"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Pending rider not found.'];
    }

    $stmt = db()->prepare('UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$userId]);

    logActivity($adminId, 'rider_approve', "Approved rider {$user['username']} ({$user['rider_code']})");

    return ['success' => true, 'message' => "Rider {$user['full_name']} has been approved."];
}

function rejectRider(int $userId, int $adminId): array
{
    $stmt = db()->prepare(
        "SELECT u.*, r.rider_code FROM users u
         JOIN riders r ON r.user_id = u.id
         WHERE u.id = ? AND u.role = 'rider' AND u.is_active = 0"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Pending rider not found.'];
    }

    $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$userId]);

    logActivity($adminId, 'rider_reject', "Rejected rider registration: {$user['username']} ({$user['rider_code']})");

    return ['success' => true, 'message' => "Registration for {$user['full_name']} has been rejected."];
}

function createRiderAccount(array $data, bool $active = true): array
{
    $pdo = db();

    $duplicate = checkUserDuplicate($data['username'], $data['email']);
    if ($duplicate) {
        return ['success' => false, 'message' => $duplicate];
    }

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $riderCode = generateRiderCode();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password, role, full_name, phone, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['username'],
            $data['email'],
            $hash,
            'rider',
            $data['full_name'],
            $data['phone'] ?? null,
            $active ? 1 : 0
        ]);

        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO riders (user_id, rider_code, vehicle_type, license_number)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $riderCode,
            $data['vehicle_type'] ?? 'Motorcycle',
            riderPlateFromData($data)
        ]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Rider account created.',
            'user_id' => $userId,
            'rider_code' => $riderCode
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Failed to create rider account.'];
    }
}

function getRiderActiveDeliveryCount(int $riderId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM parcels WHERE assigned_rider_id = ? AND status = 'out_for_delivery'"
    );
    $stmt->execute([$riderId]);
    return (int) $stmt->fetchColumn();
}

function getRiderDeleteBlockers(int $riderId): array
{
    $reasons = [];
    $active = getRiderActiveDeliveryCount($riderId);

    if ($active > 0) {
        $reasons[] = $active === 1
            ? 'This rider has 1 parcel currently out for delivery.'
            : "This rider has {$active} parcels currently out for delivery.";
    }

    return $reasons;
}

function deleteRider(int $riderId, int $adminId): array
{
    $rider = getRiderById($riderId);
    if (!$rider) {
        return ['success' => false, 'message' => 'Rider not found.'];
    }

    $blockers = getRiderDeleteBlockers($riderId);
    if (!empty($blockers)) {
        return [
            'success' => false,
            'message' => 'Cannot delete rider while active deliveries are in progress. Complete or reassign deliveries first.',
            'reasons' => $blockers,
        ];
    }

    $pdo = db();
    $userId = (int) $rider['user_id'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE parcels SET assigned_rider_id = NULL WHERE assigned_rider_id = ?');
        $stmt->execute([$riderId]);

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = ?');
        $stmt->execute([$userId, 'rider']);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Failed to delete rider account.'];
        }

        $pdo->commit();

        logActivity($adminId, 'rider_delete', "Deleted rider {$rider['rider_code']} ({$rider['full_name']})");

        return ['success' => true, 'message' => "Rider {$rider['full_name']} has been deleted."];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Delete rider failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete rider. Please try again.'];
    }
}
