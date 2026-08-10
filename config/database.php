<?php
/**
 * Database Connection
 * Compatible with PHP 7.0+ (cPanel shared hosting)
 */

require_once __DIR__ . '/config.php';

$localConfigFile = __DIR__ . '/config.local.php';
if (is_readable($localConfigFile)) {
    require_once $localConfigFile;
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'parcel_delivery_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

/**
 * Get PDO database connection (singleton)
 */
if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());

            if (function_exists('isApiRequest') && isApiRequest()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed. Check config/config.local.php on the server.',
                ]);
                exit;
            }

            throw new PDOException('Database connection failed. Please check server configuration.');
        }

        return $pdo;
    }
}

/**
 * True when the current request targets the /api/ folder.
 */
if (!function_exists('isApiRequest')) {
    function isApiRequest(): bool
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        return strpos($script, '/api/') !== false;
    }
}
