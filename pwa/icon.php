<?php
/**
 * PWA icon — PNG via GD, SVG fallback when GD is unavailable
 */
declare(strict_types=1);

$size = max(48, min(512, (int) ($_GET['size'] ?? 192)));
$svgPath = dirname(__DIR__) . '/assets/icons/pwa-icon.svg';

if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
    if (is_readable($svgPath)) {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=2592000');
        readfile($svgPath);
        exit;
    }

    http_response_code(503);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=2592000');

$img = imagecreatetruecolor($size, $size);
imagesavealpha($img, true);
imagealphablending($img, true);

for ($y = 0; $y < $size; $y++) {
    $ratio = $y / max(1, $size - 1);
    $r = (int) (37 + (29 - 37) * $ratio);
    $g = (int) (99 + (78 - 99) * $ratio);
    $b = (int) (235 + (216 - 235) * $ratio);
    $lineColor = imagecolorallocate($img, $r, $g, $b);
    imageline($img, 0, $y, $size, $y, $lineColor);
}

$margin = (int) round($size * 0.2);
$white = imagecolorallocate($img, 255, 255, 255);
$blue = imagecolorallocate($img, 37, 99, 235);
$lightBlue = imagecolorallocate($img, 59, 130, 246);

imagefilledrectangle($img, $margin, $margin, $size - $margin, $size - $margin, $white);

$tapeTop = (int) round($size * 0.36);
$tapeHeight = (int) round($size * 0.11);
imagefilledrectangle($img, $margin, $tapeTop, $size - $margin, $tapeTop + $tapeHeight, $lightBlue);

$font = 5;
$label = 'PD';
$textWidth = imagefontwidth($font) * strlen($label);
$textX = (int) (($size - $textWidth) / 2);
$textY = (int) round($size * 0.56);
imagestring($img, $font, $textX, $textY, $label, $blue);

imagepng($img);
imagedestroy($img);
