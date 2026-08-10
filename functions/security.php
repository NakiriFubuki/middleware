<?php
/**
 * Security Functions - CSRF, validation, sanitization
 */

function generateCsrfToken(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verifyCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'message' => 'Invalid security token. Please refresh the page.'], 403);
        }
        $_SESSION['flash_error'] = 'Security token expired. Please try again.';
        redirect($_SERVER['HTTP_REFERER'] ?? baseUrl('login.php'));
    }
}

function validateRequired(array $fields, array $data): array
{
    $errors = [];
    foreach ($fields as $field => $label) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $errors[$field] = $label . ' is required.';
        }
    }
    return $errors;
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone(string $phone): bool
{
    return preg_match('/^[0-9+\-\s()]{7,20}$/', $phone) === 1;
}

function validatePassword(string $password): array
{
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain letters and numbers.';
    }
    return $errors;
}

function sanitizeInput(array $data): array
{
    $clean = [];
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $clean[$key] = trim($value);
        } else {
            $clean[$key] = $value;
        }
    }
    return $clean;
}

function checkSessionTimeout(): void
{
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > SESSION_LIFETIME) {
            if (($_SESSION['role'] ?? '') === 'rider' && !empty($_SESSION['user_id'])) {
                $stmt = db()->prepare('UPDATE riders SET is_online = 0, updated_at = NOW() WHERE user_id = ?');
                $stmt->execute([(int) $_SESSION['user_id']]);
            }

            session_unset();
            session_destroy();
            if (isAjax()) {
                jsonResponse(['success' => false, 'message' => 'Session expired.', 'redirect' => baseUrl('login.php')], 401);
            }
            redirect(baseUrl('login.php?timeout=1'));
        }
    }
    $_SESSION['last_activity'] = time();
}

function regenerateSession(): void
{
    session_regenerate_id(true);
}
