<?php

$base = dirname(__DIR__, 3) . '/frontend/public/_evidence/pr139-responsive';
if (!is_dir($base) && !mkdir($base, 0777, true) && !is_dir($base)) {
    fwrite(STDERR, "FAILED_MKDIR\n");
    exit(1);
}

function make(string $path, int $w, int $h, string $fmt): void
{
    $im = imagecreatetruecolor($w, $h);
    $c = imagecolorallocate($im, 20, 80, 140);
    imagefilledrectangle($im, 0, 0, $w, $h, $c);
    $t = imagecolorallocate($im, 255, 255, 255);
    imagestring($im, 3, 8, 8, basename($path), $t);

    if ($fmt === 'jpg') {
        imagejpeg($im, $path, 70);
    } elseif ($fmt === 'webp' && function_exists('imagewebp')) {
        imagewebp($im, $path, 70);
    } elseif ($fmt === 'avif' && function_exists('imageavif')) {
        imageavif($im, $path, 50);
    } else {
        imagejpeg($im, $path, 70);
    }

    imagedestroy($im);
    echo $path, ' ', filesize($path), PHP_EOL;
}

$files = [
    ['hero-desktop.avif', 1200, 600, 'avif'],
    ['hero-desktop.webp', 1200, 600, 'webp'],
    ['hero-desktop.jpg', 1200, 600, 'jpg'],
    ['hero-tablet.avif', 1024, 512, 'avif'],
    ['hero-tablet.webp', 1024, 512, 'webp'],
    ['hero-mobile.avif', 768, 384, 'avif'],
    ['hero-mobile.webp', 768, 384, 'webp'],
    ['hero-original.jpg', 2400, 1200, 'jpg'],
    ['card-320.avif', 320, 200, 'avif'],
    ['card-320.webp', 320, 200, 'webp'],
    ['card-640.avif', 640, 400, 'avif'],
    ['card-640.webp', 640, 400, 'webp'],
    ['card-640.jpg', 640, 400, 'jpg'],
    ['card-original.jpg', 1600, 1000, 'jpg'],
    ['support-desktop.avif', 960, 540, 'avif'],
    ['support-desktop.webp', 960, 540, 'webp'],
    ['support-desktop.jpg', 960, 540, 'jpg'],
    ['support-mobile.avif', 640, 800, 'avif'],
    ['support-mobile.webp', 640, 800, 'webp'],
];

foreach ($files as [$n, $w, $h, $f]) {
    make($base . '/' . $n, $w, $h, $f);
}

echo 'AVIF=', function_exists('imageavif') ? 'YES' : 'NO', PHP_EOL;
echo 'WEBP=', function_exists('imagewebp') ? 'YES' : 'NO', PHP_EOL;
