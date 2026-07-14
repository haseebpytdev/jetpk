<?php

declare(strict_types=1);

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD extension required\n");
    exit(1);
}

$dir = __DIR__.'/../public/client-assets/jetpk-assets/favicon';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

foreach ([16, 32, 48] as $size) {
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $trans);

    $greenDark = imagecolorallocate($im, 0, 132, 61);
    $greenLight = imagecolorallocate($im, 0, 166, 81);
    $ink = imagecolorallocate($im, 4, 20, 12);

    $pad = (int) max(1, round($size * 0.08));
    imagefilledrectangle($im, $pad, $pad, $size - $pad - 1, $size - $pad - 1, $greenDark);
    imagefilledrectangle($im, $pad, $pad, $size - $pad - 1, (int) ($size * 0.45), $greenLight);

    $plane = [
        (int) ($size * 0.62), (int) ($size * 0.38),
        (int) ($size * 0.52), (int) ($size * 0.52),
        (int) ($size * 0.34), (int) ($size * 0.56),
        (int) ($size * 0.28), (int) ($size * 0.62),
        (int) ($size * 0.38), (int) ($size * 0.64),
        (int) ($size * 0.48), (int) ($size * 0.58),
        (int) ($size * 0.44), (int) ($size * 0.72),
        (int) ($size * 0.56), (int) ($size * 0.68),
    ];
    imagefilledpolygon($im, $plane, $ink);

    $pngPath = $dir.'/favicon-'.$size.'.png';
    imagepng($im, $pngPath);
    imagedestroy($im);
}

$png32 = $dir.'/favicon-32.png';
$icoPath = $dir.'/favicon.ico';

// Minimal ICO wrapping PNG32 (Windows / browsers accept PNG-in-ICO).
$pngData = file_get_contents($png32);
if ($pngData === false) {
    exit(1);
}

$header = pack('vvv', 0, 1, 1);
$entry = pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($pngData), 22);
file_put_contents($icoPath, $header.$entry.$pngData);

echo "Wrote {$icoPath}\n";
