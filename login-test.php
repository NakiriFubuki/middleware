<?php
/**
 * Quick login bootstrap test — visit: yoursite.com/GPS_System/login-test.php
 * Delete after fixing production issues.
 */
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
echo "bootstrap: ok\n";
echo 'app_url: ' . APP_URL . "\n";
echo 'logged_in: ' . (isLoggedIn() ? 'yes' : 'no') . "\n";
