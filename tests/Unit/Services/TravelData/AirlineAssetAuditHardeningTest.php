<?php

namespace Tests\Unit\Services\TravelData;

use App\Services\TravelData\AirlineArchiveAuditService;
use App\Services\TravelData\AirlineAssetManifestService;
use App\Services\TravelData\AirlineImageContentValidator;
use App\Support\Audits\JetpkAirportParityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AirlineAssetAuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fake_text_in_png_is_rejected(): void
    {
        $result = app(AirlineImageContentValidator::class)->validateBytes(
            'fake-image',
            'png',
            'airline-logos/EK.png',
        );

        $this->assertFalse($result['valid_content']);
        $this->assertContains('text_plain_under_image_extension', $result['validation_errors']);
        $this->assertContains('invalid_image_signature', $result['validation_errors']);
    }

    #[Test]
    public function fake_text_in_webp_is_rejected(): void
    {
        $result = app(AirlineImageContentValidator::class)->validateBytes(
            'webp-bytes',
            'webp',
            'travel-assets/airlines/logos/QR.webp',
        );

        $this->assertFalse($result['valid_content']);
        $this->assertContains('text_plain_under_image_extension', $result['validation_errors']);
    }

    #[Test]
    public function extension_mime_mismatch_is_rejected(): void
    {
        $jpeg = $this->jpegBytes();
        $result = app(AirlineImageContentValidator::class)->validateBytes(
            $jpeg,
            'png',
            'travel-assets/airlines/logos/SV.png',
        );

        $this->assertFalse($result['valid_content']);
        $this->assertContains('extension_mime_mismatch', $result['validation_errors']);
    }

    #[Test]
    public function missing_raster_dimensions_is_rejected(): void
    {
        $result = app(AirlineImageContentValidator::class)->validateBytes(
            "\x89PNG\r\n\x1a\nnot-a-real-png",
            'png',
            'airline-logos/ZZ.png',
        );

        $this->assertFalse($result['valid_content']);
        $this->assertContains('missing_raster_dimensions', $result['validation_errors']);
    }

    #[Test]
    public function malformed_svg_is_rejected(): void
    {
        $result = app(AirlineImageContentValidator::class)->validateBytes(
            '<html><body>nope</body></html>',
            'svg',
            'images/airline-generic.svg',
        );

        $this->assertFalse($result['valid_content']);
        $this->assertContains('malformed_svg', $result['validation_errors']);
    }

    #[Test]
    public function path_audit_never_prints_blank_public_path(): void
    {
        $dir = storage_path('app/public/airline-logos');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/PA.png', $this->pngBytes());

        $exit = Artisan::call('jetpk:airline-asset-path-audit');
        $output = Artisan::output();

        $this->assertStringNotContainsString('PASS   size=', $output);
        $this->assertStringNotContainsString('FAIL   size=', $output);
        $this->assertStringContainsString('/storage/airline-logos/PA.png', $output);
        $this->assertGreaterThanOrEqual(0, $exit);
    }

    #[Test]
    public function missing_optional_logo_is_not_a_path_audit_failure(): void
    {
        $travel = storage_path('app/public/travel-assets/airlines/logos');
        File::ensureDirectoryExists($travel);
        File::put($travel.'/QR.png', $this->pngBytes());

        $result = app(JetpkAirportParityAuditService::class)->airlineAssetPathAudit();
        $qr = collect($result['assets'])->first(
            static fn (array $asset): bool => ($asset['public_path'] ?? '') === '/storage/travel-assets/airlines/logos/QR.png',
        );

        $this->assertNotNull($qr);
        $this->assertSame('PASS', $qr['status']);
        $this->assertFalse(
            collect($result['assets'])->contains(
                static fn (array $asset): bool => ($asset['public_path'] ?? '') === '/storage/travel-assets/airlines/logos/QR.webp',
            ),
        );
    }

    #[Test]
    public function invalid_canonical_manifest_is_rejected(): void
    {
        $manifests = app(AirlineAssetManifestService::class);
        $expected = [
            'entry_count' => 1,
            'entries' => [[
                'path' => 'airline-logos/EK.png',
                'size' => 10,
                'sha256' => str_repeat('a', 64),
                'detected_mime' => 'text/plain',
                'valid_content' => false,
                'validation_errors' => ['text_plain_under_image_extension'],
            ]],
        ];
        $actual = [
            'entry_count' => 1,
            'entries' => [[
                'path' => 'airline-logos/EK.png',
                'size' => 10,
                'sha256' => str_repeat('a', 64),
                'detected_mime' => 'text/plain',
                'valid_content' => true,
                'validation_errors' => [],
            ]],
        ];

        $compare = $manifests->compareManifests($expected, $actual);
        $this->assertFalse($compare['pass']);
        $this->assertSame('invalid_canonical_manifest_entry', $compare['mismatches'][0]['issue']);
    }

    #[Test]
    public function clean_archive_passes_content_audit(): void
    {
        $tmp = storage_path('app/audits/test-clean-archive-'.uniqid());
        File::ensureDirectoryExists($tmp.'/airline-logos');
        File::put($tmp.'/airline-logos/PA.png', $this->pngBytes());

        $archive = storage_path('app/audits/test-clean.tgz');
        if (is_file($archive)) {
            unlink($archive);
        }
        $code = 0;
        exec('tar -czf '.escapeshellarg($archive).' -C '.escapeshellarg($tmp).' airline-logos', result_code: $code);
        $this->assertSame(0, $code);

        $result = app(AirlineArchiveAuditService::class)->audit($archive);
        $this->assertTrue($result['pass']);
        $this->assertSame(0, $result['fail_count']);

        unlink($archive);
        File::deleteDirectory($tmp);
    }

    #[Test]
    public function archive_audit_rejects_fake_png_member(): void
    {
        $tmp = storage_path('app/audits/test-fake-archive-'.uniqid());
        File::ensureDirectoryExists($tmp.'/travel-assets/airlines/logos');
        File::put($tmp.'/travel-assets/airlines/logos/EK.png', 'fake-image');

        $archive = storage_path('app/audits/test-fake.tgz');
        if (is_file($archive)) {
            unlink($archive);
        }
        exec('tar -czf '.escapeshellarg($archive).' -C '.escapeshellarg($tmp).' travel-assets', result_code: $code);
        $this->assertSame(0, $code);

        $result = app(AirlineArchiveAuditService::class)->audit($archive);
        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('travel-assets/airlines/logos/EK.png', (string) ($result['issues'][0]['path'] ?? ''));

        unlink($archive);
        File::deleteDirectory($tmp);
    }

    #[Test]
    public function manifest_entry_includes_validation_fields(): void
    {
        $root = storage_path('app/audits/test-manifest-'.uniqid());
        File::ensureDirectoryExists($root.'/airline-logos');
        File::put($root.'/airline-logos/PA.png', $this->pngBytes());

        $manifest = app(AirlineAssetManifestService::class)->buildFromRoot($root, false);
        $entry = $manifest['entries'][0];

        foreach (['path', 'size', 'sha256', 'detected_mime', 'extension', 'width', 'height', 'valid_content', 'validation_errors'] as $field) {
            $this->assertArrayHasKey($field, $entry);
        }
        $this->assertTrue($entry['valid_content']);
        $this->assertTrue($manifest['valid']);

        File::deleteDirectory($root);
    }

    #[Test]
    public function audits_do_not_write_to_database_or_call_network(): void
    {
        Http::fake();
        Mail::fake();

        $beforeAirlines = DB::table('airlines')->count();
        $beforeAirports = DB::table('airports')->count();

        Artisan::call('jetpk:airline-asset-path-audit');
        Artisan::call('jetpk:airline-logo-coverage-audit');

        $this->assertSame($beforeAirlines, DB::table('airlines')->count());
        $this->assertSame($beforeAirports, DB::table('airports')->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function pngBytes(int $blue = 0x00): string
    {
        $img = imagecreatetruecolor(2, 2);
        $color = imagecolorallocate($img, 0, 0, $blue);
        imagefill($img, 0, 0, $color);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    private function jpegBytes(): string
    {
        $img = imagecreatetruecolor(2, 2);
        $color = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $color);
        ob_start();
        imagejpeg($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }
}
