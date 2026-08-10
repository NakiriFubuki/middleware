<?php
/**
 * Step-by-step bootstrap diagnostic.
 * Visit: yoursite.com/GPS_System/api/debug-bootstrap.php
 * Delete after fixing production issues.
 */
header('Content-Type: application/json; charset=utf-8');

$steps = [];

function debugStep(string $label, callable $fn): void
{
    global $steps;
    try {
        $fn();
        $steps[] = ['step' => $label, 'ok' => true];
    } catch (Throwable $e) {
        $steps[] = [
            'step' => $label,
            'ok' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
        echo json_encode(['steps' => $steps], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

debugStep('database.php', function () {
    require_once __DIR__ . '/../config/database.php';
});

debugStep('helpers.php', function () {
    require_once __DIR__ . '/../functions/helpers.php';
});

debugStep('security.php', function () {
    require_once __DIR__ . '/../functions/security.php';
});

debugStep('activity.php', function () {
    require_once __DIR__ . '/../functions/activity.php';
});

debugStep('auth.php', function () {
    require_once __DIR__ . '/../functions/auth.php';
});

debugStep('parcel.php', function () {
    require_once __DIR__ . '/../functions/parcel.php';
});

debugStep('rider.php', function () {
    require_once __DIR__ . '/../functions/rider.php';
});

debugStep('upload.php', function () {
    require_once __DIR__ . '/../functions/upload.php';
});

debugStep('report.php', function () {
    require_once __DIR__ . '/../functions/report.php';
});

debugStep('checkRememberMe()', function () {
    checkRememberMe();
});

debugStep('header helpers', function () {
    $user = function_exists('currentUser') ? currentUser() : null;
    $token = function_exists('generateCsrfToken') ? generateCsrfToken() : null;
    $css = function_exists('stylesheetUrls') ? stylesheetUrls() : [];
    $url = function_exists('assetUrl') ? assetUrl('css/login.css') : null;
});

echo json_encode([
    'ok' => true,
    'steps' => $steps,
    'app_url' => defined('APP_URL') ? APP_URL : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
