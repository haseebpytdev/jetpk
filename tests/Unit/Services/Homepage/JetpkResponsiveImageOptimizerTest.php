<?php

namespace Tests\Unit\Services\Homepage;

use App\Services\Homepage\JetpkResponsiveImageOptimizer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JetpkResponsiveImageOptimizerTest extends TestCase
{
    public function test_card_profile_generates_manifest_without_upscaling(): void
    {
        Storage::fake('public');
        $source = $this->gradientJpeg('card-source.jpg', 500, 375);
        $optimizer = app(JetpkResponsiveImageOptimizer::class);

        $result = $optimizer->optimize(
            $source,
            'jetpk-assets',
            JetpkResponsiveImageOptimizer::PROFILE_CARD,
            'home',
            'jetpk/homepage/popular-destinations/jetpk/card-source.jpg',
        );

        $this->assertTrue($result['activated'], $result['warning'] ?? 'not activated');
        $this->assertIsArray($result['manifest']);
        $this->assertSame('card', $result['manifest']['profile']);
        $this->assertSame(500, $result['manifest']['source_width']);

        $widths = [];
        foreach ($result['manifest']['variants'] as $breakpoint => $formats) {
            $this->assertArrayHasKey('jpg', $formats);
            $width = (int) $formats['jpg']['width'];
            $widths[] = $width;
            $this->assertLessThanOrEqual(500, $width, 'derivative must not upscale past source width');
        }

        $this->assertContains(320, $widths);
        $this->assertContains(480, $widths);
        // 640/768 targets clamp to source width 500 — never emit a wider bitmap.
        $this->assertSame(500, max($widths));

        $formats = $result['manifest']['formats'];
        $this->assertContains('jpg', $formats);
        // WebP/AVIF are environment-dependent; only assert when the optimizer reported them.
        if (in_array('webp', $formats, true)) {
            $this->assertTrue($this->hasFormat($result['manifest']['variants'], 'webp'));
        }
        if (in_array('avif', $formats, true)) {
            $this->assertTrue($this->hasFormat($result['manifest']['variants'], 'avif'));
        }
    }

    public function test_fingerprint_changes_with_source_content(): void
    {
        Storage::fake('public');
        $optimizer = app(JetpkResponsiveImageOptimizer::class);
        $first = $optimizer->optimize(
            $this->gradientJpeg('a.jpg', 800, 600, 20),
            'jetpk-assets',
            JetpkResponsiveImageOptimizer::PROFILE_SUPPORT_BACKGROUND,
        );
        $second = $optimizer->optimize(
            $this->gradientJpeg('b.jpg', 800, 600, 140),
            'jetpk-assets',
            JetpkResponsiveImageOptimizer::PROFILE_SUPPORT_BACKGROUND,
        );

        $this->assertTrue($first['activated']);
        $this->assertTrue($second['activated']);
        $this->assertNotSame($first['fingerprint'], $second['fingerprint']);
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $variants
     */
    private function hasFormat(array $variants, string $format): bool
    {
        foreach ($variants as $formats) {
            if (isset($formats[$format])) {
                return true;
            }
        }

        return false;
    }

    private function gradientJpeg(string $filename, int $width, int $height, int $offset = 40): string
    {
        $dir = storage_path('app/testing/responsive-media-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/'.$filename;
        $img = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorallocate(
                $img,
                ($x + $offset) % 255,
                (120 + $offset) % 255,
                (200 - ($x % 80) + $offset) % 255,
            );
            imageline($img, $x, 0, $x, $height - 1, $color);
        }
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }
}
