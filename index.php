<?php
require_once __DIR__ . '/includes/bootstrap-minimal.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? baseUrl('admin/dashboard.php') : baseUrl('rider/dashboard.php'));
}

redirect(baseUrl('login.php'));
