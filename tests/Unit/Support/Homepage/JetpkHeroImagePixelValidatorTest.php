<?php

namespace Tests\Unit\Support\Homepage;

use App\Support\Homepage\JetpkHeroImagePixelValidator;
use Tests\TestCase;

class JetpkHeroImagePixelValidatorTest extends TestCase
{
    private JetpkHeroImagePixelValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(JetpkHeroImagePixelValidator::class);
    }

    public function test_rejects_near_solid_black_image(): void
    {
        $path = $this->writeCanvas(800, 450, static function ($img): void {
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefilledrectangle($img, 0, 0, 799, 449, $black);
        }, 'jpg');

        $result = $this->validator->validateFile($path, 800, 450);

        $this->assertFalse($result['valid']);
        $this->assertMatchesRegularExpression(
            '/black|implausibly small|variance/i',
            $result['reason'],
        );
    }

    public function test_rejects_near_zero_variance_image(): void
    {
        $path = $this->writeCanvas(120, 80, static function ($img): void {
            $gray = imagecolorallocate($img, 40, 40, 40);
            imagefilledrectangle($img, 0, 0, 119, 79, $gray);
        }, 'jpg');

        $result = $this->validator->validateFile($path, 120, 80);

        $this->assertFalse($result['valid']);
    }

    public function test_accepts_photographic_fixture(): void
    {
        $reference = 'c:/Users/khadi/Downloads/jetpakistan.png';
        if (! is_file($reference)) {
            $path = $this->writeCanvas(320, 180, static function ($img): void {
                for ($x = 0; $x < 320; $x++) {
                    for ($y = 0; $y < 180; $y++) {
                        $color = imagecolorallocate($img, ($x + 20) % 255, ($y * 2) % 255, 120);
                        imagesetpixel($img, $x, $y, $color);
                    }
                }
            }, 'jpg');
        } else {
            $path = $this->convertReferenceToJpeg($reference);
        }

        $result = $this->validator->validateSourceFile($path);

        $this->assertTrue($result['valid'], $result['reason'] ?? 'invalid');
    }

    private function convertReferenceToJpeg(string $reference): string
    {
        $dir = storage_path('app/testing/hero-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $target = $dir.'/photographic-source.jpg';
        $png = imagecreatefrompng($reference);
        imagejpeg($png, $target, 90);
        imagedestroy($png);

        return $target;
    }

    private function writeCanvas(int $width, int $height, callable $draw, string $format): string
    {
        $dir = storage_path('app/testing/hero-fixtures');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/validator-'.md5((string) microtime(true)).'.'.$format;
        $img = imagecreatetruecolor($width, $height);
        $draw($img);

        match ($format) {
            'jpg' => imagejpeg($img, $path, 90),
            'webp' => imagewebp($img, $path, 90),
            default => imagepng($img, $path),
        };

        imagedestroy($img);

        return $path;
    }
}
