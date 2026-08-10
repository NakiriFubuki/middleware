<?php
$currentUser = null;
if (function_exists('currentUser')) {
    try {
        $currentUser = currentUser();
    } catch (Throwable $e) {
        error_log('Header currentUser failed: ' . $e->getMessage());
        $currentUser = null;
    }
}
$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$extraCss = $extraCss ?? [];
$extraJs = $extraJs ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <meta name="theme-color" content="#2563eb">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="PDMS">
    <link rel="manifest" href="<?= baseUrl('manifest.php') ?>">
    <link rel="apple-touch-icon" href="<?= baseUrl('assets/icons/pwa-icon.svg') ?>">
    <title><?= sanitize($pageTitle) ?> - <?= APP_NAME ?></title>
    <?php foreach (stylesheetUrls() as $css): ?>
        <link rel="stylesheet" href="<?= assetUrl($css) ?>">
    <?php endforeach; ?>
    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= assetUrl('css/' . $css) ?>">
    <?php endforeach; ?>
</head>
<body class="<?= sanitize($bodyClass) ?><?= isRider() ? ' rider-app' : '' ?>" data-base-url="<?= baseUrl() ?>"<?= isRider() ? ' data-rider-auto-gps="1"' : '' ?>>
