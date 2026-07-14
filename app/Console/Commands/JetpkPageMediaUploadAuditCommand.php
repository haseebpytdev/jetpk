<?php

namespace App\Console\Commands;

use App\Services\Client\ClientPageAssetService;
use App\Models\ClientProfile;
use App\Services\Client\ClientProfileResolver;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Verifies JetPK page media upload pipeline (storage disk, metadata, URL).
 */
class JetpkPageMediaUploadAuditCommand extends Command
{
    protected $signature = 'jetpk:page-media-upload-audit {--client=jetpk : Client profile slug}';

    protected $description = 'Audit JetPK page media upload storage pipeline (read-only probe)';

    public function handle(ClientPageAssetService $assetService, ClientProfileResolver $profileResolver): int
    {
        $this->line('Classification: READ-ONLY JetPK page media upload audit (probe file written then removed).');
        $this->newLine();

        if (! Schema::hasTable('client_page_assets')) {
            $this->error('client_page_assets table missing');

            return self::FAILURE;
        }

        $slug = (string) $this->option('client');
        $profile = $profileResolver->resolveBySlug($slug);
        if (! $profile instanceof ClientProfile) {
            $this->error("Client profile not found: {$slug}");

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $probeDir = 'client-assets/_audit-probes';
        $probeName = 'upload-probe-'.now()->format('YmdHis').'.txt';
        $probePath = $probeDir.'/'.$probeName;

        $fail = 0;

        try {
            $disk->put($probePath, 'jetpk-page-media-upload-audit');
            if (! $disk->exists($probePath)) {
                $this->error('Storage probe write failed');
                $fail++;
            } else {
                $this->line('storage_write=pass path='.$probePath);
                $this->line('public_url='.$disk->url($probePath));
            }
        } finally {
            $disk->delete($probePath);
        }

        $tempFile = UploadedFile::fake()->image('audit-probe.jpg', 64, 64);
        $asset = null;
        try {
            $asset = $assetService->store($profile, 'home', '_audit_probe', $tempFile, null, 'audit');
            if (! $disk->exists($asset->path)) {
                $this->error('Uploaded asset not found on public disk: '.$asset->path);
                $fail++;
            } else {
                $this->line('service_store=pass path='.$asset->path);
                $this->line('meta_mime='.($asset->meta_json['mime'] ?? 'missing'));
            }
        } catch (\Throwable $e) {
            $this->error('service_store=fail '.$e->getMessage());
            $fail++;
        } finally {
            if ($asset !== null) {
                $assetService->destroy($asset);
            }
        }

        $symlink = public_path('storage');
        $this->line('storage_symlink='.(is_link($symlink) || is_dir($symlink) ? 'present' : 'missing'));

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
