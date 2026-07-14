<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class OtaBackupCheckCommand extends Command
{
    protected $signature = 'ota:backup-check';

    protected $description = 'Checklist-style backup readiness checks for OTA deployment';

    public function handle(): int
    {
        $dbConnectionOk = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $dbConnectionOk = false;
        }

        $backupDisk = (string) config('ota.backup.disk', 'local');
        $backupPath = trim((string) config('ota.backup.path', 'backups'), '/');
        $backupRootPath = Storage::disk($backupDisk)->path('/');
        $backupDirectory = rtrim($backupRootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$backupPath;
        File::ensureDirectoryExists($backupDirectory);

        $checks = [
            'database_connection_ok' => $dbConnectionOk,
            'private_storage_exists' => is_dir(storage_path((string) config('ota.private_documents_directory', 'app/private'))),
            'backup_disk_configured' => config('filesystems.disks.'.$backupDisk) !== null,
            'backup_directory_writable' => is_writable($backupDirectory),
        ];

        foreach ($checks as $name => $ok) {
            $this->line(sprintf('- [%s] %s', $ok ? 'x' : ' ', $name));
        }

        return self::SUCCESS;
    }
}
