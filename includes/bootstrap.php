<?php
/**
 * Application Bootstrap - Load all dependencies
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/security.php';
require_once __DIR__ . '/../functions/activity.php';
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/parcel.php';
require_once __DIR__ . '/../functions/rider.php';
require_once __DIR__ . '/../functions/upload.php';
require_once __DIR__ . '/../functions/report.php';

checkRememberMe();
logPageAccessIfNeeded();
