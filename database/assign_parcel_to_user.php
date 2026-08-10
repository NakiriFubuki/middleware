<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$stmt = db()->prepare('SELECT r.id FROM riders r JOIN users u ON u.id = r.user_id WHERE u.username = ?');
$stmt->execute(['user']);
$riderId = $stmt->fetchColumn();

if (!$riderId) {
    echo "Rider 'user' not found.\n";
    exit(1);
}

$stmt = db()->prepare('SELECT id FROM parcels WHERE assigned_rider_id IS NULL AND status = ? LIMIT 1');
$stmt->execute(['pending']);
$parcelId = $stmt->fetchColumn();

if (!$parcelId) {
    echo "No unassigned pending parcel found.\n";
    exit(0);
}

$stmt = db()->prepare('UPDATE parcels SET assigned_rider_id = ?, updated_at = NOW() WHERE id = ?');
$stmt->execute([$riderId, $parcelId]);

addParcelStatusHistory((int) $parcelId, 'pending', 'Assigned to rider for delivery', (int) $riderId);
echo "Assigned parcel #$parcelId to rider #$riderId (user)\n";
