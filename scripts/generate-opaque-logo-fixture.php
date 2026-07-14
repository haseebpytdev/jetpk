<?php

$img = imagecreatetruecolor(160, 80);
$blue = imagecolorallocate($img, 0, 82, 204);
$white = imagecolorallocate($img, 255, 255, 255);
imagefill($img, 0, 0, $blue);
imagestring($img, 5, 20, 30, 'QA LOGO', $white);
imagepng($img, __DIR__.'/../tests/fixtures/branding/opaque-logo.png');
imagedestroy($img);

echo "opaque fixture written\n";
