<?php
require_once __DIR__ . '/bootstrap.php';

requireLogin();
requireAdmin();

$data = getLiveTrackingData();
jsonResponse(['success' => true, 'data' => $data]);
