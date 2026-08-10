<?php
/**
 * Helper Functions
 */

function baseUrl(string $path = ''): string
{
    $base = rtrim(APP_URL, '/');
    $path = ltrim($path, '/');
    return $path ? $base . '/' . $path : $base;
}

function assetUrl(string $path): string
{
    $relative = ltrim(str_replace('\\', '/', $path), '/');
    $url = baseUrl('assets/' . $relative);

    $fullPath = dirname(__DIR__) . '/assets/' . $relative;
    if (is_file($fullPath)) {
        $url .= '?v=' . filemtime($fullPath);
    }

    return $url;
}

/**
 * Ensure logistics login background exists (auto-copy on local dev if missing).
 */
function ensureLoginBackgroundImage(): bool
{
    $dest = dirname(__DIR__) . '/assets/images/login-logistics.png';

    if (is_file($dest) && filesize($dest) > 2048) {
        return true;
    }

    $dir = dirname($dest);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $sources = [
        dirname(__DIR__) . '/assets/login-logistics.png',
        'C:/Users/Acer/.cursor/projects/c-xampp-htdocs-GPS-System/assets/login-logistics.png',
    ];

    foreach ($sources as $src) {
        if (!is_readable($src)) {
            continue;
        }

        if (@copy($src, $dest) && is_file($dest) && filesize($dest) > 2048) {
            return true;
        }
    }

    return is_file($dest) && filesize($dest) > 2048;
}

function loginBackgroundAssetUrl(): string
{
    if (ensureLoginBackgroundImage()) {
        return assetUrl('images/login-logistics.png');
    }

    return assetUrl('images/login-winter.png');
}

/**
 * Stylesheets loaded directly (avoids @import issues on some cPanel hosts).
 */
function stylesheetUrls(): array
{
    return [
        'css/variables.css',
        'css/base.css',
        'css/components.css',
        'css/layout.css',
        'css/login.css',
        'css/dashboard.css',
        'css/map.css',
        'css/responsive.css',
    ];
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Cookie path for the app (matches session cookie path on subfolder installs).
 */
function appCookiePath(): string
{
    $cookiePath = parse_url(APP_URL, PHP_URL_PATH) ?: '/';
    if ($cookiePath === '' || $cookiePath === false) {
        return '/';
    }

    return $cookiePath;
}

function appCookieParams(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => appCookiePath(),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => strpos(APP_URL, 'https://') === 0,
    ];
}

/**
 * Safe internal back-link target from ?return= query param.
 */
function backUrl(string $defaultPath): string
{
    $return = trim((string) ($_GET['return'] ?? ''));
    if ($return === '') {
        return baseUrl($defaultPath);
    }

    if (preg_match('#^(https?://|//|javascript:)#i', $return) || strpos($return, '..') !== false) {
        return baseUrl($defaultPath);
    }

    $return = rawurldecode($return);
    $basePath = parse_url(APP_URL, PHP_URL_PATH) ?: '';

    if ($return[0] === '/') {
        if ($basePath !== '' && $basePath !== '/' && strpos($return, $basePath) === 0) {
            return $return;
        }
        return baseUrl(ltrim($return, '/'));
    }

    return baseUrl(ltrim($return, '/'));
}

/**
 * Build a link that preserves the current page as the return target.
 */
function linkWithReturn(string $path, array $params = []): string
{
    $current = $_SERVER['REQUEST_URI'] ?? '';
    $basePath = parse_url(APP_URL, PHP_URL_PATH) ?: '';

    if ($basePath !== '' && $basePath !== '/' && strpos($current, $basePath) === 0) {
        $current = substr($current, strlen($basePath));
    }

    $params['return'] = ltrim($current, '/');
    $query = http_build_query($params);

    return baseUrl($path) . ($query !== '' ? '?' . $query : '');
}

function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function isValidIpAddress(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function isPublicIpAddress(string $ip): bool
{
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

function normalizeIpAddress(string $ip): string
{
    $ip = trim($ip, " \t[]\"");

    if (stripos($ip, '::ffff:') === 0) {
        $ipv4 = substr($ip, 7);
        if (isValidIpAddress($ipv4)) {
            return $ipv4;
        }
    }

    if ($ip === '::1') {
        return '127.0.0.1';
    }

    return $ip;
}

/**
 * Collect possible client IPs from proxy headers and REMOTE_ADDR.
 */
function collectClientIpCandidates(): array
{
    $candidates = [];

    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_TRUE_CLIENT_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'HTTP_CLIENT_IP',
    ];

    foreach ($headers as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        $value = trim((string) $_SERVER[$header]);

        if ($header === 'HTTP_FORWARDED') {
            if (preg_match_all('/for=(?:"\[?)([a-fA-F0-9:.]+)(?:"\]?)/i', $value, $matches)) {
                foreach ($matches[1] as $ip) {
                    $candidates[] = $ip;
                }
            }
            continue;
        }

        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $candidates[] = $part;
            }
        }
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = trim((string) $_SERVER['REMOTE_ADDR']);
    }

    $seen = [];
    $unique = [];

    foreach ($candidates as $ip) {
        $ip = normalizeIpAddress($ip);
        if ($ip === '' || isset($seen[$ip])) {
            continue;
        }
        $seen[$ip] = true;
        $unique[] = $ip;
    }

    return $unique;
}

/**
 * Resolve the visitor's IP (public IP preferred; supports Cloudflare / reverse proxy).
 */
function getClientIp(): string
{
    $candidates = collectClientIpCandidates();

    foreach ($candidates as $ip) {
        if (isPublicIpAddress($ip)) {
            return $ip;
        }
    }

    foreach ($candidates as $ip) {
        if (isValidIpAddress($ip)) {
            return $ip;
        }
    }

    return '';
}

function formatIpAddress(?string $ip): string
{
    $ip = trim((string) $ip);

    if ($ip === '') {
        return '—';
    }

    if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true)) {
        return $ip . ' (Local)';
    }

    return $ip;
}

function getUserAgent(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
}

function formatDate(?string $date, string $format = 'M d, Y h:i A'): string
{
    if (!$date) {
        return '—';
    }
    return date($format, strtotime($date));
}

function formatStatus(string $status): string
{
    $labels = [
        'pending' => 'Pending',
        'out_for_delivery' => 'Out For Delivery',
        'delivered' => 'Delivered',
        'failed' => 'Failed Delivery',
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function statusBadgeClass(string $status): string
{
    $classes = [
        'pending' => 'badge-warning',
        'out_for_delivery' => 'badge-info',
        'delivered' => 'badge-success',
        'failed' => 'badge-danger',
    ];
    return $classes[$status] ?? 'badge-secondary';
}

function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isPlaceholderParcelField(?string $value): bool
{
    $value = trim($value ?? '');
    return $value === '' || $value === '-';
}

function generateTrackingNumber(): string
{
    $pdo = db();
    $year = date('Y');
    $prefix = TRACKING_PREFIX . $year;

    $stmt = $pdo->prepare(
        "SELECT tracking_number FROM parcels 
         WHERE tracking_number LIKE ? 
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $num = (int) substr($last, strlen($prefix)) + 1;
    } else {
        $num = 1;
    }

    return $prefix . str_pad((string) $num, 6, '0', STR_PAD_LEFT);
}

function paginate(int $total, int $page, int $perPage = 15): array
{
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

function buildPaginationHtml(int $currentPage, int $totalPages, string $baseUrl): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<nav class="pagination-nav"><ul class="pagination">';

    if ($currentPage > 1) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($currentPage - 1) . '" class="page-link">&laquo; Prev</a></li>';
    }

    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<li><a href="' . $baseUrl . '&page=' . $i . '" class="page-link' . $active . '">' . $i . '</a></li>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($currentPage + 1) . '" class="page-link">Next &raquo;</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

function exportCsv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $headers);

    foreach ($rows as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

function exportExcel(string $filename, array $headers, array $rows): void
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';

    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
        }
        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;
}
