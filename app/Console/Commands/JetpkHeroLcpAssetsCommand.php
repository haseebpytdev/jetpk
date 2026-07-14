<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generates responsive AVIF/WebP/JPEG hero variants for homepage LCP.
 */
class JetpkHeroLcpAssetsCommand extends Command
{
    protected $signature = 'jetpk:hero-lcp-assets
        {--source= : Absolute or public-relative path to the source hero JPEG}
        {--profile=jetpk-assets : Client asset profile slug}';

    protected $description = 'Generate responsive AVIF/WebP/JPEG homepage hero LCP variants';

  /** @var array<string, int> */
    private const TARGET_WIDTHS = [
        'desktop' => 1200,
        'tablet' => 1024,
        'mobile' => 768,
    ];

    public function handle(): int
    {
        $source = $this->resolveSourcePath();
        if ($source === null) {
            $this->error('Could not locate a source hero image. Pass --source= or upload hero_background to pages/home.');

            return self::FAILURE;
        }

        if (! extension_loaded('gd')) {
            $this->error('GD extension is required to generate hero LCP variants.');

            return self::FAILURE;
        }

        $size = @getimagesize($source);
        if (! is_array($size)) {
            $this->error('Source file is not a readable image: '.$source);

            return self::FAILURE;
        }

        $profile = trim((string) $this->option('profile'));
        $outputDir = public_path('client-assets/'.$profile.'/pages/home/lcp');
        File::ensureDirectoryExists($outputDir);

        $beforeBytes = (int) filesize($source);
        $rows = [];

        foreach (self::TARGET_WIDTHS as $label => $targetWidth) {
            $targetHeight = (int) max(1, round($size[1] * ($targetWidth / $size[0])));

            foreach (['jpg' => 82, 'webp' => 82, 'avif' => 60] as $format => $quality) {
                $dest = $outputDir.'/hero-'.$label.'.'.$format;
                $this->writeVariant($source, $dest, $targetWidth, $targetHeight, $format, $quality);
                $rows[] = [
                    $label,
                    $format,
                    $targetWidth.'x'.$targetHeight,
                    $this->formatBytes((int) filesize($dest)),
                ];
            }
        }

        $this->table(['Breakpoint', 'Format', 'Dimensions', 'Bytes'], $rows);
        $this->line('Source: '.$source.' ('.$this->formatBytes($beforeBytes).', '.$size[0].'x'.$size[1].')');
        $this->line('Output: '.$outputDir);

        return self::SUCCESS;
    }

    private function resolveSourcePath(): ?string
    {
        $explicit = trim((string) $this->option('source'));
        if ($explicit !== '') {
            if (is_file($explicit)) {
                return $explicit;
            }

            $publicCandidate = public_path(ltrim($explicit, '/'));
            if (is_file($publicCandidate)) {
                return $publicCandidate;
            }
        }

        $profile = trim((string) $this->option('profile'));
        $homeDir = public_path('client-assets/'.$profile.'/pages/home');
        if (! is_dir($homeDir)) {
            return null;
        }

        $matches = glob($homeDir.'/hero_background*.jpg') ?: [];
        if ($matches === []) {
            $matches = glob($homeDir.'/hero_background*.jpeg') ?: [];
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $matches[0];
    }

    private function writeVariant(
        string $source,
        string $dest,
        int $targetWidth,
        int $targetHeight,
        string $format,
        int $quality,
    ): void {
        $image = $this->loadImage($source);
        if ($image === null) {
            throw new \RuntimeException('Unable to load source image.');
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($image),
            imagesy($image),
        );

        match ($format) {
            'jpg' => imagejpeg($canvas, $dest, $quality),
            'webp' => imagewebp($canvas, $dest, $quality),
            'avif' => imageavif($canvas, $dest, $quality),
            default => throw new \InvalidArgumentException('Unsupported format: '.$format),
        };

        imagedestroy($image);
        imagedestroy($canvas);
    }

    private function loadImage(string $source): ?\GdImage
    {
        $type = (int) (@getimagesize($source)[2] ?? 0);

        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source) ?: null,
            IMAGETYPE_PNG => @imagecreatefrompng($source) ?: null,
            IMAGETYPE_WEBP => @imagecreatefromwebp($source) ?: null,
            default => null,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        return number_format($bytes / 1024, 1).' KB';
    }
}
