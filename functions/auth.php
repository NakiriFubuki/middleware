<?php
/**
 * Authentication Functions
 */

if (!function_exists('db')) {
    require_once __DIR__ . '/../config/database.php';
}
if (!function_exists('baseUrl')) {
    require_once __DIR__ . '/helpers.php';
}
if (!function_exists('generateCsrfToken')) {
    require_once __DIR__ . '/security.php';
}
if (!function_exists('logActivity')) {
    require_once __DIR__ . '/activity.php';
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

function isAdmin(): bool
{
    return currentRole() === 'admin';
}

function isRider(): bool
{
    return currentRole() === 'rider';
}

function getRiderProfile(bool $refresh = false): ?array
{
    if (!isRider()) {
        return null;
    }

    static $rider = null;
    if ($refresh) {
        $rider = null;
    }
    if ($rider === null) {
        $stmt = db()->prepare(
            'SELECT r.*, u.full_name, u.email, u.phone, u.profile_photo
             FROM riders r
             JOIN users u ON u.id = r.user_id
             WHERE r.user_id = ?'
        );
        $stmt->execute([currentUserId()]);
        $rider = $stmt->fetch() ?: null;
    }
    return $rider;
}

function getRiderId(): ?int
{
    $rider = getRiderProfile();
    return $rider ? (int) $rider['id'] : null;
}

function loginUser(array $user, bool $remember = false): void
{
    regenerateSession();
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['last_activity'] = time();

    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $stmt->execute([$user['id']]);

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
        $stmt->execute([hash('sha256', $token), $user['id']]);

        $expires = time() + (REMEMBER_ME_DAYS * 86400);
        setcookie('remember_token', $token, appCookieParams($expires));
        setcookie('remember_user', (string) $user['id'], appCookieParams($expires));
    }

    logActivity((int) $user['id'], 'login', 'User logged in');

    if (($user['role'] ?? '') === 'rider') {
        setRiderOnlineByUserId((int) $user['id'], true);
    }
}

function setRiderOnlineByUserId(int $userId, bool $isOnline): void
{
    $stmt = db()->prepare('SELECT id FROM riders WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $riderId = $stmt->fetchColumn();

    if ($riderId) {
        updateRiderOnlineStatus((int) $riderId, $isOnline, $userId);
    }
}

function ensureRiderOnline(): void
{
    if (!isRider()) {
        return;
    }

    $userId = currentUserId();
    if (!$userId) {
        return;
    }

    $stmt = db()->prepare('SELECT id, is_online FROM riders WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $rider = $stmt->fetch();

    if ($rider && !(int) $rider['is_online']) {
        updateRiderOnlineStatus((int) $rider['id'], true, $userId);
    }
}

function logoutUser(): void
{
    $userId = currentUserId();
    $role = $_SESSION['role'] ?? null;

    if ($userId) {
        if ($role === 'rider') {
            setRiderOnlineByUserId($userId, false);
        }

        logActivity($userId, 'logout', 'User logged out');
        $stmt = db()->prepare('UPDATE users SET remember_token = NULL WHERE id = ?');
        $stmt->execute([$userId]);
    }

    setcookie('remember_token', '', appCookieParams(time() - 3600));
    setcookie('remember_user', '', appCookieParams(time() - 3600));

    session_unset();
    session_destroy();
}

function attemptLogin(string $username, string $password): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1'
    );
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    if (!(int) $user['is_active']) {
        return [
            'success' => false,
            'message' => 'Your account is pending admin approval. Please wait for confirmation.',
            'pending' => true
        ];
    }

    return ['success' => true, 'user' => $user];
}

function checkRememberMe(): void
{
    if (isLoggedIn()) {
        return;
    }

    $userId = $_COOKIE['remember_user'] ?? null;
    $token = $_COOKIE['remember_token'] ?? null;

    if (!$userId || !$token) {
        return;
    }

    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && hash_equals((string) $user['remember_token'], hash('sha256', $token))) {
            loginUser($user, false);

            $expires = time() + (REMEMBER_ME_DAYS * 86400);
            setcookie('remember_token', $token, appCookieParams($expires));
            setcookie('remember_user', (string) $user['id'], appCookieParams($expires));
        }
    } catch (Throwable $e) {
        error_log('Remember-me check failed: ' . $e->getMessage());
    }
}

function requireLogin(): void
{
    checkRememberMe();
    checkSessionTimeout();

    if (!isLoggedIn()) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized', 'redirect' => baseUrl('login.php')], 401);
        }
        redirect(baseUrl('login.php'));
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
        }
        redirect(baseUrl('rider/dashboard.php'));
    }
}

function requireRider(): void
{
    requireLogin();
    if (!isRider()) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
        }
        redirect(baseUrl('admin/dashboard.php'));
    }

    ensureRiderOnline();
}

function changePassword(int $userId, string $currentPassword, string $newPassword): array
{
    $stmt = db()->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        return ['success' => false, 'message' => 'Current password is incorrect.'];
    }

    $errors = validatePassword($newPassword);
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(' ', $errors)];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hash, $userId]);

    logActivity($userId, 'password_change', 'Password changed');

    return ['success' => true, 'message' => 'Password updated successfully.'];
}
