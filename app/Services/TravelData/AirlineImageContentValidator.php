<?php

namespace App\Services\TravelData;

use App\Support\Audits\JetpkSvgSafetyAuditor;

/**
 * Content-based validation for airline logo image files (no network calls).
 */
final class AirlineImageContentValidator
{
    private const MIN_RASTER_BYTES = 32;

    /** @var array<string, list<string>> */
    private const EXTENSION_MIMES = [
        'png' => ['image/png', 'image/x-png'],
        'jpg' => ['image/jpeg', 'image/jpg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/jpg', 'image/pjpeg'],
        'webp' => ['image/webp'],
        'svg' => ['image/svg+xml', 'text/xml', 'application/xml'],
    ];

    /**
     * @return array{
     *     path: string,
     *     size: int,
     *     sha256: ?string,
     *     detected_mime: ?string,
     *     extension: string,
     *     width: ?int,
     *     height: ?int,
     *     valid_content: bool,
     *     validation_errors: list<string>
     * }
     */
    public function validateFile(string $absolutePath, ?string $relativePath = null): array
    {
        $relativePath = $relativePath ?? basename($absolutePath);
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if (! is_file($absolutePath)) {
            return $this->buildResult($relativePath, $extension, 0, null, null, null, null, false, ['missing_file']);
        }

        $content = (string) file_get_contents($absolutePath);

        return $this->validateBytes($content, $extension, $relativePath);
    }

    /**
     * @return array{
     *     path: string,
     *     size: int,
     *     sha256: ?string,
     *     detected_mime: ?string,
     *     extension: string,
     *     width: ?int,
     *     height: ?int,
     *     valid_content: bool,
     *     validation_errors: list<string>
     * }
     */
    public function validateBytes(string $content, string $extension, string $relativePath): array
    {
        $extension = strtolower($extension);
        $size = strlen($content);
        $sha256 = $size > 0 ? (hash('sha256', $content) ?: null) : null;
        $detectedMime = $this->detectMime($content);
        $errors = [];

        if ($size === 0) {
            $errors[] = 'zero_byte';
        }

        if ($extension === 'svg') {
            if (! $this->isValidSvgStructure($content)) {
                $errors[] = 'malformed_svg';
            } else {
                $svgAudit = (new JetpkSvgSafetyAuditor)->auditContent($content);
                if (! $svgAudit['pass']) {
                    $errors[] = 'unsafe_svg_content';
                }
            }

            if ($detectedMime === 'text/plain' && ! in_array('malformed_svg', $errors, true)) {
                $detectedMime = 'image/svg+xml';
            }

            if ($detectedMime !== null && ! $this->extensionMatchesMime('svg', $detectedMime)) {
                $errors[] = 'extension_mime_mismatch';
            }

            return $this->buildResult(
                $relativePath,
                $extension,
                $size,
                $sha256,
                $detectedMime,
                null,
                null,
                $errors === [],
                array_values(array_unique($errors)),
            );
        }

        if ($detectedMime === 'text/plain' || $this->looksLikePlainText($content)) {
            $errors[] = 'text_plain_under_image_extension';
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true) && $size > 0 && $size < self::MIN_RASTER_BYTES) {
            $errors[] = 'tiny_placeholder_body';
        }

        if (! $this->signatureMatchesExtension($content, $extension)) {
            $errors[] = 'invalid_image_signature';
        }

        if ($detectedMime !== null && ! $this->extensionMatchesMime($extension, $detectedMime)) {
            $errors[] = 'extension_mime_mismatch';
        }

        $width = null;
        $height = null;

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $info = @getimagesizefromstring($content);
            if ($info === false) {
                $errors[] = 'missing_raster_dimensions';
            } else {
                $width = isset($info[0]) ? (int) $info[0] : null;
                $height = isset($info[1]) ? (int) $info[1] : null;
                if ($width === null || $width < 1 || $height === null || $height < 1) {
                    $errors[] = 'missing_raster_dimensions';
                }
            }
        } elseif ($extension !== '' && ! isset(self::EXTENSION_MIMES[$extension])) {
            $errors[] = 'unexpected_extension';
        }

        return $this->buildResult(
            $relativePath,
            $extension,
            $size,
            $sha256,
            $detectedMime,
            $width,
            $height,
            $errors === [],
            array_values(array_unique($errors)),
        );
    }

    private function detectMime(string $content): ?string
    {
        if ($content === '') {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_buffer($finfo, $content);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        return $this->mimeFromSignature($content);
    }

    private function mimeFromSignature(string $content): ?string
    {
        if (str_starts_with($content, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($content, "\xff\xd8\xff")) {
            return 'image/jpeg';
        }
        if (strlen($content) >= 12 && str_starts_with($content, 'RIFF') && substr($content, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        $trimmed = ltrim($content);
        if (str_starts_with($trimmed, '<') && str_contains(strtolower($trimmed), '<svg')) {
            return 'image/svg+xml';
        }
        if ($this->looksLikePlainText($content)) {
            return 'text/plain';
        }

        return null;
    }

    private function looksLikePlainText(string $content): bool
    {
        if ($content === '') {
            return false;
        }

        $sample = substr($content, 0, min(64, strlen($content)));
        if (! mb_check_encoding($sample, 'UTF-8')) {
            return false;
        }

        return preg_match('/^[\x09\x0A\x0D\x20-\x7E]+$/', $sample) === 1;
    }

    private function signatureMatchesExtension(string $content, string $extension): bool
    {
        return match ($extension) {
            'png' => str_starts_with($content, "\x89PNG\r\n\x1a\n"),
            'jpg', 'jpeg' => str_starts_with($content, "\xff\xd8\xff"),
            'webp' => strlen($content) >= 12 && str_starts_with($content, 'RIFF') && substr($content, 8, 4) === 'WEBP',
            'svg' => $this->isValidSvgStructure($content),
            default => false,
        };
    }

    private function isValidSvgStructure(string $content): bool
    {
        $trimmed = ltrim($content);
        if ($trimmed === '' || ! str_starts_with($trimmed, '<')) {
            return false;
        }

        return preg_match('/<svg\b/i', $trimmed) === 1;
    }

    private function extensionMatchesMime(string $extension, string $detectedMime): bool
    {
        $detectedMime = strtolower($detectedMime);
        $allowed = self::EXTENSION_MIMES[$extension] ?? [];

        return in_array($detectedMime, $allowed, true);
    }

    /**
     * @param  list<string>  $errors
     * @return array{
     *     path: string,
     *     size: int,
     *     sha256: ?string,
     *     detected_mime: ?string,
     *     extension: string,
     *     width: ?int,
     *     height: ?int,
     *     valid_content: bool,
     *     validation_errors: list<string>
     * }
     */
    private function buildResult(
        string $path,
        string $extension,
        int $size,
        ?string $sha256,
        ?string $detectedMime,
        ?int $width,
        ?int $height,
        bool $valid,
        array $errors,
    ): array {
        return [
            'path' => $path,
            'size' => $size,
            'sha256' => $sha256,
            'detected_mime' => $detectedMime,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
            'valid_content' => $valid,
            'validation_errors' => $errors,
        ];
    }
}
