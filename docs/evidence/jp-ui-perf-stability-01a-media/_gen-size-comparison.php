<?php

/**
 * Local-only size comparison for PR #139 review evidence.
 * Usage: php docs/evidence/jp-ui-perf-stability-01a-media/_gen-size-comparison.php
 */

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Homepage\JetpkResponsiveImageOptimizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

Storage::fake('public');

$optimizer = app(JetpkResponsiveImageOptimizer::class);
$outDir = storage_path('app/testing/responsive-media-review-fixtures');
File::ensureDirectoryExists($outDir);

function noisyJpeg(string $path, int $width, int $height, int $offset = 40): void
{
    $img = imagecreatetruecolor($width, $height);
    for ($y = 0; $y < $height; $y += 2) {
        for ($x = 0; $x < $width; $x += 2) {
            $noise = (($x * 37) + ($y * 53) + $offset) % 255;
            $color = imagecolorallocate(
                $img,
                ($noise + $x) % 255,
                ($noise + $y + 40) % 255,
                (220 - ($noise % 90)) % 255,
            );
            imagefilledrectangle($img, $x, $y, $x + 1, $y + 1, $color);
        }
    }
    imagejpeg($img, $path, 92);
    imagedestroy($img);
}

$cases = [
    'hero' => [1920, 1080, JetpkResponsiveImageOptimizer::PROFILE_HERO],
    'card' => [1200, 800, JetpkResponsiveImageOptimizer::PROFILE_CARD],
    'support' => [1600, 900, JetpkResponsiveImageOptimizer::PROFILE_SUPPORT_BACKGROUND],
];

$rows = [];
foreach ($cases as $name => [$w, $h, $profile]) {
    $source = $outDir.'/'.$name.'-source.jpg';
    noisyJpeg($source, $w, $h, strlen($name) * 11);
    $result = $optimizer->optimize($source, 'jetpk-assets', $profile, 'home', 'review/'.$name.'.jpg');
    $manifest = $result['manifest'] ?? [];
    $best = ['avif' => null, 'webp' => null, 'jpg' => null];
    foreach ((array) ($manifest['variants'] ?? []) as $formats) {
        foreach (['avif', 'webp', 'jpg'] as $fmt) {
            $bytes = (int) ($formats[$fmt]['bytes'] ?? 0);
            if ($bytes > 0 && ($best[$fmt] === null || $bytes < $best[$fmt])) {
                $best[$fmt] = $bytes;
            }
        }
    }
    $sourceBytes = (int) ($manifest['source_bytes'] ?? filesize($source));
    $rows[] = [
        'category' => $name,
        'dims' => $w.'x'.$h,
        'source_bytes' => $sourceBytes,
        'best_avif' => $best['avif'],
        'best_webp' => $best['webp'],
        'best_jpg' => $best['jpg'],
        'webp_reduction_pct' => $best['webp'] !== null && $sourceBytes > 0
            ? round((1 - ($best['webp'] / $sourceBytes)) * 100, 1)
            : null,
    ];
}

$payload = [
    'generated_at' => date('c'),
    'avif_encoder_available' => function_exists('imageavif'),
    'webp_encoder_available' => function_exists('imagewebp'),
    'rows' => $rows,
];

file_put_contents(__DIR__.'/06-SIZE-COMPARISON-LARGE.json', json_encode($payload, JSON_PRETTY_PRINT));
echo json_encode($payload, JSON_PRETTY_PRINT), PHP_EOL;
