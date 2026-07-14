<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class OtaStorageCheckCommand extends Command
{
    protected $signature = 'ota:storage-check';

    protected $description = 'Check OTA storage and document path readiness';

    public function handle(): int
    {
        $publicDiskWritable = $this->isWritableDiskPath(Storage::disk('public')->path('/'));
        $privateDocumentsDir = storage_path((string) config('ota.private_documents_directory', 'app/private'));
        $pdfTempDir = storage_path((string) config('ota.pdf_temp_directory', 'app/private/tmp/pdf'));
        $storageLinkExists = is_link(public_path('storage')) || is_dir(public_path('storage'));

        File::ensureDirectoryExists($privateDocumentsDir);
        File::ensureDirectoryExists($pdfTempDir);

        $checks = [
            'public_disk_writable' => $publicDiskWritable,
            'private_documents_directory_writable' => is_writable($privateDocumentsDir),
            'pdf_temp_directory_writable' => is_writable($pdfTempDir),
            'storage_link_exists' => $storageLinkExists,
        ];

        foreach ($checks as $name => $ok) {
            $this->line(sprintf('[%s] %s', $ok ? 'OK' : 'FAIL', $name));
        }

        return self::SUCCESS;
    }

    protected function isWritableDiskPath(string $path): bool
    {
        File::ensureDirectoryExists($path);

        return is_writable($path);
    }
}
