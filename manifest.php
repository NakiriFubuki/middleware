<?php
/**
 * Web App Manifest (dynamic paths for subfolder / cPanel deploy)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions/helpers.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$appId = rtrim(baseUrl(), '/') . '/';

$manifest = [
    'id' => $appId,
    'name' => APP_NAME,
    'short_name' => 'PDMS',
    'description' => 'Parcel Delivery Management System',
    'start_url' => baseUrl('login.php'),
    'scope' => $appId,
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#f8fafc',
    'theme_color' => '#2563eb',
    'lang' => 'en',
    'prefer_related_applications' => false,
    'related_applications' => [
        [
            'platform' => 'webapp',
            'url' => baseUrl('manifest.php'),
            'id' => $appId,
        ],
    ],
    'icons' => [
        [
            'src' => baseUrl('assets/icons/pwa-icon.svg'),
            'sizes' => 'any',
            'type' => 'image/svg+xml',
            'purpose' => 'any',
        ],
        [
            'src' => baseUrl('pwa/icon.php?size=192'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => baseUrl('pwa/icon.php?size=512'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
