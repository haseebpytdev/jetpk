<?php

$img = imagecreatetruecolor(96, 48);
imagesavealpha($img, true);
imagealphablending($img, false);
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $transparent);
$orange = imagecolorallocatealpha($img, 234, 122, 30, 0);
imagefilledellipse($img, 48, 24, 58, 34, $orange);
imagepng($img, __DIR__.'/../tests/fixtures/branding/transparent-logo.png');
imagedestroy($img);

echo "fixture written\n";
