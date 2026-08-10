<?php
/**
 * Quick diagnostic — visit: yoursite.com/GPS_System/api/health.php
 * Optional: ?bootstrap=1 to test full app bootstrap
 */
header('Content-Type: application/json; charset=utf-8');

$configLocalPath = __DIR__ . '/../config/config.local.php';
$databasePath = __DIR__ . '/../config/database.php';
$helpersPath = __DIR__ . '/../functions/helpers.php';

try {
    require_once $databasePath;
    $pdo = db();
    $databaseStatus = 'connected';
    $databaseError = null;
} catch (Throwable $e) {
    $pdo = null;
    $databaseStatus = 'failed';
    $databaseError = $e->getMessage();
}

$result = [
    'php_version' => PHP_VERSION,
    'app_url_defined' => defined('APP_URL'),
    'app_url' => defined('APP_URL') ? APP_URL : null,
    'config_local_exists' => is_readable($configLocalPath),
    'database' => $databaseStatus,
    'db_name' => defined('DB_NAME') ? DB_NAME : null,
    'server_files' => [
        'database_has_duplicate_jsonResponse' => strpos(
            (string) @file_get_contents($databasePath),
            'function jsonResponse'
        ) !== false,
        'helpers_has_json_guard' => strpos(
            (string) @file_get_contents($helpersPath),
            'function_exists(\'jsonResponse\')'
        ) !== false,
    ],
    'deploy' => [
        'login_has_winter_layout' => strpos(
            (string) @file_get_contents(__DIR__ . '/../login.php'),
            'id="snowCanvas"'
        ) !== false && strpos(
            (string) @file_get_contents(__DIR__ . '/../login.php'),
            'login-showcase.php'
        ) === false,
        'login_uses_minimal_bootstrap' => strpos(
            (string) @file_get_contents(__DIR__ . '/../login.php'),
            'bootstrap-minimal.php'
        ) !== false,
        'login_css_has_winter_image' => strpos(
            (string) @file_get_contents(__DIR__ . '/../assets/css/login.css'),
            'login-winter.png'
        ) !== false,
        'winter_image_exists' => is_file(__DIR__ . '/../assets/images/login-winter.png'),
    ],
];

if ($databaseError) {
    $result['database_error'] = $databaseError;
}

$moduleFiles = [
    'helpers.php' => __DIR__ . '/../functions/helpers.php',
    'security.php' => __DIR__ . '/../functions/security.php',
    'activity.php' => __DIR__ . '/../functions/activity.php',
    'auth.php' => __DIR__ . '/../functions/auth.php',
    'parcel.php' => __DIR__ . '/../functions/parcel.php',
    'rider.php' => __DIR__ . '/../functions/rider.php',
    'upload.php' => __DIR__ . '/../functions/upload.php',
    'report.php' => __DIR__ . '/../functions/report.php',
];

$result['modules'] = [];
foreach ($moduleFiles as $name => $path) {
    $result['modules'][$name] = is_file($path) ? 'exists' : 'missing';
}

if (!empty($_GET['bootstrap'])) {
    $bootstrapSteps = [];
    foreach ($moduleFiles as $name => $path) {
        try {
            require_once $path;
            $bootstrapSteps[$name] = 'ok';
        } catch (Throwable $e) {
            $bootstrapSteps[$name] = 'failed: ' . $e->getMessage();
            break;
        }
    }
    $result['bootstrap_test'] = $bootstrapSteps;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
