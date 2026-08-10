<?php
/**
 * Lightweight bootstrap for login/register pages only.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/activity.php';
require_once __DIR__ . '/../functions/auth.php';

checkRememberMe();
logPageAccessIfNeeded();
