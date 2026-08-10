<?php
/**
 * Minimal health check — no database, no bootstrap.
 * Visit: yoursite.com/GPS_System/api/ping.php
 */
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'php_version' => PHP_VERSION,
    'time' => date('c'),
], JSON_UNESCAPED_SLASHES);
