<?php

namespace Tests\Unit\Services\TravelData;

use App\Services\TravelData\AirlineArchiveAuditService;
use App\Services\TravelData\AirlineAssetManifestService;
use App\Services\TravelData\AirlineAssetPromotionService;
use App\Services\TravelData\AirlineLogoIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AirlineAssetStagingAndAccountingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function archive_audit_rejects_traversal_paths_in_archive(): void
    {
        $service = app(AirlineArchiveAuditService::class);
        $result = $service->validateEntries([
            ['name' => '../escape.png', 'type' => 'file', 'size' => 10],
        ]);
        $types = collect($result['issues'])->pluck('type')->all();
        $this->assertContains('traversal', $types);
    }

    #[Test]
    public function archive_audit_rejects_paths_outside_allowed_roots(): void
    {
        $service = app(AirlineArchiveAuditService::class);
        $result = $service->validateEntries([
            ['name' => 'evil/logo.png', 'type' => 'file', 'size' => 10],
        ]);
        $types = collect($result['issues'])->pluck('type')->all();
        $this->assertContains('outside_allowed_roots', $types);
    }

    #[Test]
    public function archive_audit_rejects_symlink_entries(): void
    {
        $service = app(AirlineArchiveAuditService::class);
        $result = $service->validateEntries([
            ['name' => 'airline-logos/PA.png', 'type' => 'symlink', 'size' => 10],
        ]);
        $types = collect($result['issues'])->pluck('type')->all();
        $this->assertContains('symlink_or_hardlink', $types);
    }

    #[Test]
    public function archive_command_rejects_absolute_path_option(): void
    {
        $exit = Artisan::call('jetpk:airline-archive-audit', ['--archive' => '/tmp/evil.tgz']);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Absolute archive paths are rejected', Artisan::output());
    }

    #[Test]
    public function staging_manifest_mismatch_blocks_promotion(): void
    {
        $staging = storage_path('app/audits/test-staging-'.uniqid());
        $backup = storage_path('app/audits/test-backup-'.uniqid());
        File::ensureDirectoryExists($staging.'/airline-logos');
        File::put($staging.'/airline-logos/PA.png', $this->pngBytes());

        $manifests = app(AirlineAssetManifestService::class);
        $actual = $manifests->buildFromRoot($staging, false);
        $expected = $actual;
        $expected['entries'][0]['sha256'] = str_repeat('0', 64);

        $promotion = app(AirlineAssetPromotionService::class);
        $result = $promotion->promote($staging, storage_path('app/public'), $backup, ['airline-logos'], $expected);

        $this->assertFalse($result['promoted']);
        $this->assertSame('staging_manifest_mismatch', $result['reason']);
        File::deleteDirectory($staging);
        File::deleteDirectory($backup);
    }

    #[Test]
    public function atomic_promotion_rollback_restores_checksums(): void
    {
        $active = storage_path('app/audits/test-active-'.uniqid());
        $staging = storage_path('app/audits/test-stage-'.uniqid());
        $backup = storage_path('app/audits/test-bak-'.uniqid());
        $relative = 'airline-logos';
        $oldBytes = $this->pngBytes();
        $newBytes = $this->pngBytes(0xFF);

        File::ensureDirectoryExists($active.'/'.$relative);
        File::put($active.'/'.$relative.'/PA.png', $oldBytes);
        File::ensureDirectoryExists($staging.'/'.$relative);
        File::put($staging.'/'.$relative.'/PA.png', $newBytes);

        $manifests = app(AirlineAssetManifestService::class);
        $before = $manifests->buildFromRoot($active, false);
        $stagingManifest = $manifests->buildFromRoot($staging, false);

        $promotion = app(AirlineAssetPromotionService::class);
        $promoted = $promotion->promote($staging, $active, $backup, [$relative], $stagingManifest);
        $this->assertTrue($promoted['promoted']);

        $rolled = $promotion->rollback($active, $backup, [$relative]);
        $after = $manifests->buildFromRoot($active, false);
        $this->assertSame($before['entries'][0]['sha256'], $after['entries'][0]['sha256']);

        File::deleteDirectory($active);
        File::deleteDirectory($backup);
    }

    #[Test]
    public function logo_identity_proves_pa_pf_and_9p_canonical_names(): void
    {
        Storage::fake('public');
        foreach (['PA', 'PF', '9P'] as $code) {
            Storage::disk('public')->put('airline-logos/'.$code.'.png', $this->pngBytes());
        }

        $result = app(AirlineLogoIdentityService::class)->audit(['PA', 'PF', '9P']);
        $byCode = collect($result['carriers'])->keyBy('iata');
        $this->assertSame('Airblue', $byCode['PA']['canonical_name']);
        $this->assertSame('AirSial', $byCode['PF']['canonical_name']);
        $this->assertSame('Fly Jinnah', $byCode['9P']['canonical_name']);
        $this->assertTrue($byCode['PA']['pass']);
        $this->assertTrue($byCode['PF']['pass']);
        $this->assertTrue($byCode['9P']['pass']);
    }

  private function fixtureArchivePath(string $name): string
    {
        $dir = storage_path('app/audits/test-archives');
        File::ensureDirectoryExists($dir);

        return $dir.DIRECTORY_SEPARATOR.$name;
    }

    /**
     * @param  array<string, string>  $members  tar member path => contents
     */
    private function createTarGzWithMembers(string $archivePath, array $members): void
    {
        if (is_file($archivePath)) {
            unlink($archivePath);
        }

        if (! class_exists(\PharData::class)) {
            $this->markTestSkipped('PharData required for archive fixture tests');
        }

        $base = preg_replace('/\.tgz$/i', '', $archivePath) ?: $archivePath;
        $tarPath = $base.'.tar';
        if (is_file($tarPath)) {
            unlink($tarPath);
        }
        $phar = new \PharData($tarPath);
        foreach ($members as $name => $contents) {
            $phar->addFromString($name, $contents);
        }
        $phar->compress(\Phar::GZ);
        unlink($tarPath);
        $gzPath = $base.'.tar.gz';
        if (is_file($gzPath)) {
            rename($gzPath, $archivePath);
        }
    }

    /**
     * @param  array<string, string>  $files  relative path => contents
     */
    private function createTarGz(string $archivePath, array $files): void
    {
        $tmp = storage_path('app/audits/tar-build-'.uniqid());
        File::ensureDirectoryExists($tmp);
        foreach ($files as $relative => $contents) {
            $path = $tmp.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, ltrim($relative, '/'));
            File::ensureDirectoryExists(dirname($path));
            file_put_contents($path, $contents);
        }

        $base = preg_replace('/\.tgz$/i', '', $archivePath) ?: $archivePath;
        $tarPath = $base.'.tar';
        if (is_file($tarPath)) {
            unlink($tarPath);
        }

        if (class_exists(\PharData::class)) {
            $phar = new \PharData($tarPath);
            $phar->buildFromDirectory($tmp);
            $phar->compress(\Phar::GZ);
            unlink($tarPath);
            $gzPath = $base.'.tar.gz';
            if (is_file($gzPath)) {
                rename($gzPath, $archivePath);
            }
        } else {
            $code = 0;
            exec('tar -czf '.escapeshellarg($archivePath).' -C '.escapeshellarg($tmp).' .', result_code: $code);
            $this->assertSame(0, $code);
        }

        File::deleteDirectory($tmp);
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
}
