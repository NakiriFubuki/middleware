<?php
/**
 * One-time setup: create rider account user / user123
 * Run: php database/seed_rider_user.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute(['user']);
if ($stmt->fetch()) {
    echo "User 'user' already exists.\n";
    exit(0);
}

$result = createRiderAccount([
    'username' => 'user',
    'email' => 'user@parceldelivery.com',
    'password' => 'user123',
    'full_name' => 'Delivery Rider',
    'phone' => '09170000001',
    'vehicle_type' => 'Motorcycle',
], true);

if ($result['success']) {
    echo "Created rider: user / user123 ({$result['rider_code']})\n";
} else {
    echo "Error: {$result['message']}\n";
    exit(1);
}
