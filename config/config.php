<?php
/**
 * Application Configuration
 */

/**
 * Normalize a filesystem/web path into a URL path (handles spaces and %20).
 */
function pdmsNormalizeUrlPath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = trim($path, '/');

    if ($path === '' || $path === '.') {
        return '';
    }

    $segments = array_filter(explode('/', $path), static function (string $segment): bool {
        return $segment !== '' && $segment !== '.';
    });

    $segments = array_map(static function (string $segment): string {
        return rawurlencode(rawurldecode($segment));
    }, $segments);

    return '/' . implode('/', $segments);
}

/**
 * Auto-detect site URL from current request (works on localhost and cPanel).
 */
function pdmsDetectAppUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projectRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');

    $basePath = '';

    if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
        $basePath = substr($projectRoot, strlen($docRoot));
    }

    if ($basePath === '' || $basePath === '/') {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = dirname($script);

        if (preg_match('#/(admin|rider|api)$#i', $dir)) {
            $dir = dirname($dir);
        }

        if ($dir !== '/' && $dir !== '\\' && $dir !== '.') {
            $basePath = $dir;
        }
    }

    $basePath = pdmsNormalizeUrlPath($basePath);

    return $scheme . '://' . $host . $basePath;
}

// Optional server overrides — create config.local.php on cPanel (see config.local.php.example)
$localConfigFile = __DIR__ . '/config.local.php';
if (is_readable($localConfigFile)) {
    require $localConfigFile;
}

define('APP_NAME', 'Parcel Delivery Management System');
define('APP_VERSION', '1.0.0');

if (!defined('APP_URL')) {
    define('APP_URL', pdmsDetectAppUrl());
}

define('SESSION_NAME', 'PDMS_SESSION');
define('SESSION_LIFETIME', 3600);
define('REMEMBER_ME_DAYS', 30);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('RIDER_PARCEL_MANAGE_MINUTES', 5);

define('UPLOAD_DIR', __DIR__ . '/../uploads/delivery_proofs/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

define('GPS_UPDATE_MIN_INTERVAL', 10);
define('GPS_UPDATE_MAX_INTERVAL', 30);
define('GPS_ROUTE_MIN_METERS', 10);
define('MAP_REFRESH_INTERVAL', 5000);
define('TRACKING_PREFIX', 'PD');
define('TRACKING_YEAR', date('Y'));

date_default_timezone_set('Asia/Manila');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    $cookiePath = parse_url(APP_URL, PHP_URL_PATH) ?: '/';
    if ($cookiePath === '' || $cookiePath === false) {
        $cookiePath = '/';
    }

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => $cookiePath,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => strpos(APP_URL, 'https://') === 0,
    ]);
    session_start();
}
