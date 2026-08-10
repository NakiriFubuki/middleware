<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    logoutUser();
}

redirect(baseUrl('login.php'));
