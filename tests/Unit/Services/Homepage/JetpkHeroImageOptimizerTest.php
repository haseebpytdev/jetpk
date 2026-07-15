<?php

namespace Tests\Unit\Services\Homepage;

use App\Services\Homepage\JetpkHeroImageOptimizer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JetpkHeroImageOptimizerTest extends TestCase
{
    public function test_optimizer_generates_valid_manifest_for_photographic_source(): void
    {
        Storage::fake('public');
        $source = $this->photographicSourcePath();
        $optimizer = app(JetpkHeroImageOptimizer::class);

        $result = $optimizer->optimize($source, 'jetpk-assets', 'home');

        $this->assertTrue($result['activated'], $result['warning'] ?? 'not activated');
        $this->assertIsArray($result['manifest']);
        $this->assertSame(substr(hash_file('sha256', $source), 0, 16), $result['fingerprint']);
        $this->assertArrayHasKey('desktop', $result['manifest']['variants']);
        $this->assertArrayHasKey('jpg', $result['manifest']['variants']['desktop']);
    }

    public function test_optimizer_rejects_black_source_without_activating_manifest(): void
    {
        Storage::fake('public');
        $source = $this->blackSourcePath();
        $optimizer = app(JetpkHeroImageOptimizer::class);

        $result = $optimizer->optimize($source, 'jetpk-assets', 'home');

        $this->assertFalse($result['activated']);
        $this->assertNull($result['manifest']);
        $this->assertNotEmpty($result['warning']);
    }

    public function test_new_fingerprint_directory_does_not_reuse_stale_variants(): void
    {
        Storage::fake('public');
        $optimizer = app(JetpkHeroImageOptimizer::class);
        $first = $optimizer->optimize($this->photographicSourcePath(), 'jetpk-assets', 'home');
        $second = $optimizer->optimize($this->alternatePhotographicSourcePath(), 'jetpk-assets', 'home');

        $this->assertTrue($first['activated']);
        $this->assertTrue($second['activated']);
        $this->assertNotSame($first['fingerprint'], $second['fingerprint']);
    }

    private function photographicSourcePath(): string
    {
        $reference = 'c:/Users/khadi/Downloads/jetpakistan.png';
        if (is_file($reference)) {
            return $this->writeJpegFromPng($reference, 'photo-a.jpg');
        }

        return $this->gradientJpeg('photo-a.jpg');
    }

    private function alternatePhotographicSourcePath(): string
    {
        return $this->gradientJpeg('photo-b.jpg', 90, 170);
    }

    private function blackSourcePath(): string
    {
        $dir = storage_path('app/testing/hero-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/black-source.jpg';
        $img = imagecreatetruecolor(400, 200);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, 399, 199, $black);
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }

    private function writeJpegFromPng(string $pngPath, string $filename): string
    {
        $dir = storage_path('app/testing/hero-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $target = $dir.'/'.$filename;
        $png = imagecreatefrompng($pngPath);
        imagejpeg($png, $target, 90);
        imagedestroy($png);

        return $target;
    }

    private function gradientJpeg(string $filename, int $offsetA = 20, int $offsetB = 120): string
    {
        $dir = storage_path('app/testing/hero-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/'.$filename;
        $img = imagecreatetruecolor(640, 360);
        for ($x = 0; $x < 640; $x++) {
            for ($y = 0; $y < 360; $y++) {
                $color = imagecolorallocate($img, ($x + $offsetA) % 255, ($y * 2) % 255, $offsetB);
                imagesetpixel($img, $x, $y, $color);
            }
        }
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }
}
