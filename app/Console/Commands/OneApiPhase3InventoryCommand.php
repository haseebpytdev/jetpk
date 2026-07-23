<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OneApiPhase3InventoryCommand extends Command
{
    protected $signature = 'ota:one-api-phase-3-inventory';

    protected $description = 'Generate phase-3 One API inventory, isolation package, and v3 deployment manifests.';

    public function handle(): int
    {
        $root = base_path();
        $status = trim((string) shell_exec('cd '.escapeshellarg($root).' && git status --short 2>nul'));
        $diffNames = trim((string) shell_exec('cd '.escapeshellarg($root).' && git diff --name-only HEAD 2>nul'));
        $untracked = trim((string) shell_exec('cd '.escapeshellarg($root).' && git ls-files --others --exclude-standard 2>nul'));

        file_put_contents(storage_path('app/one-api-git-status-phase3.txt'), $status.PHP_EOL);

        $all = array_unique(array_filter(array_merge(
            preg_split('/\R/', $diffNames) ?: [],
            preg_split('/\R/', $untracked) ?: [],
        )));

        $runtime = [];
        $tests = [];
        $docs = [];
        $generated = [];
        $excluded = [];
        $shared = [];

        foreach ($all as $path) {
            $path = trim(str_replace('\\', '/', $path));
            if ($path === '') {
                continue;
            }
            $bucket = $this->bucket($path);
            match ($bucket) {
                'runtime' => $runtime[] = $path,
                'test' => $tests[] = $path,
                'doc' => $docs[] = $path,
                'generated' => $generated[] = $path,
                'shared' => $shared[] = $path,
                default => $excluded[] = $path.':'.$bucket,
            };
        }

        sort($runtime);
        sort($tests);
        sort($docs);
        sort($generated);
        sort($excluded);
        sort($shared);

        $this->write(storage_path('app/one-api-phase-3-clean-runtime-files.txt'), $runtime);
        $this->write(storage_path('app/one-api-phase-3-test-files.txt'), $tests);
        $this->write(storage_path('app/one-api-phase-3-doc-files.txt'), $docs);
        $this->write(storage_path('app/one-api-phase-3-generated-files.txt'), $generated);
        $this->write(storage_path('app/one-api-phase-3-excluded-files.txt'), $excluded);
        $this->write(storage_path('app/one-api-phase-3-shared-files.txt'), $shared);

        $this->writeDeployV3($runtime);
        $this->writeStageScript($runtime, $shared);

        $this->info('Runtime: '.count($runtime));
        $this->info('Tests: '.count($tests));
        $this->info('Shared tracked: '.count($shared));

        return self::SUCCESS;
    }

    private function bucket(string $path): string
    {
        if (str_starts_with($path, 'tests/') || str_contains($path, '/tests/Fixtures/Suppliers/OneApi')) {
            return 'test';
        }
        if (str_starts_with($path, 'docs/integrations/one-api/')) {
            return 'doc';
        }
        if (str_starts_with($path, 'storage/app/one-api') || str_starts_with($path, 'UI_test/') || str_starts_with($path, 'JETPK_')) {
            return 'generated';
        }
        if (preg_match('#app/Services/Suppliers/OneApi|OneApi|one-api|one_api#i', $path)) {
            if (str_ends_with($path, '.php') && str_starts_with($path, 'app/')) {
                return 'runtime';
            }
            if (str_starts_with($path, 'public/js/ota-one-api')) {
                return 'runtime';
            }
            if (str_contains($path, 'resources/views') && str_contains($path, 'one-api')) {
                return 'runtime';
            }
        }
        $sharedTracked = [
            'app/Enums/SupplierProvider.php',
            'app/Services/Suppliers/SupplierAdapterResolver.php',
            'app/Services/Booking/BookingProviderRouter.php',
            'app/Services/Suppliers/SupplierBookingService.php',
            'app/Services/Suppliers/TicketingService.php',
            'app/Services/Suppliers/SupplierConnectionService.php',
            'app/Http/Controllers/Admin/SupplierConnectionController.php',
            'app/Support/Suppliers/OneApiSupplierConnectionNormalizer.php',
            'app/Support/OneApi/OneApiMutationCommandGate.php',
            'app/Support/Platform/PlatformModuleEnforcer.php',
            'app/Support/Platform/PlatformModuleGate.php',
            'app/Support/Platform/PlatformModuleRegistry.php',
            'app/Support/Bookings/SupplierLifecycleContextResolver.php',
            'app/Support/Suppliers/SupplierLifecycleCapabilities.php',
            'config/supplier_credentials.php',
            'config/suppliers.php',
            'config/ota-suppliers.php',
            'config/logging.php',
            'routes/web.php',
            'bootstrap/app.php',
            'resources/views/dashboard/admin/api-settings/form.blade.php',
            'resources/views/dashboard/admin/api-settings/partials/supplier-panels/one_api.blade.php',
            'resources/views/frontend/booking/partials/passenger-details-body.blade.php',
        ];
        if (in_array($path, $sharedTracked, true)) {
            return 'shared';
        }
        if (str_starts_with($path, 'app/Services/Suppliers/Sabre') || str_starts_with($path, 'app/Support/Sabre')) {
            return 'unrelated_sabre';
        }

        return 'uncertain';
    }

    /**
     * @param  list<string>  $lines
     */
    private function write(string $path, array $lines): void
    {
        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
    }

    /**
     * @param  list<string>  $runtime
     */
    private function writeDeployV3(array $runtime): void
    {
        $remote = '/home/pkjetp/jetpk_app';
        $sftp = ['lcd C:/Users/khadi/ota-jetpk'];
        foreach ($runtime as $file) {
            $sftp[] = 'put '.str_replace('\\', '/', $file).' '.$remote.'/'.str_replace('\\', '/', $file);
        }
        $this->write(storage_path('app/one-api-deploy-files-v3.txt'), $runtime);
        $this->write(storage_path('app/one-api-sftp-upload-v3.txt'), $sftp);
        file_put_contents(storage_path('app/one-api-post-deploy-v3.sh'), implode(PHP_EOL, [
            '#!/bin/sh',
            '/opt/alt/php-fpm83/usr/bin/php artisan config:clear',
            '/opt/alt/php-fpm83/usr/bin/php artisan route:clear',
            '/opt/alt/php-fpm83/usr/bin/php artisan view:clear',
            '/opt/alt/php-fpm83/usr/bin/php artisan config:cache',
            '/opt/alt/php-fpm83/usr/bin/php artisan route:cache',
            '/opt/alt/php-fpm83/usr/bin/php artisan view:cache',
            '/opt/alt/php-fpm83/usr/bin/php artisan ota:one-api-connection-audit --connection=<ID>',
        ]).PHP_EOL);
        file_put_contents(storage_path('app/one-api-predeploy-backup-v3.sh'), implode(PHP_EOL, [
            '#!/bin/sh',
            'BACKUP_DIR="/home/pkjetp/backups/one-api-$(date +%Y%m%d%H%M%S)"',
            'mkdir -p "$BACKUP_DIR"',
            '# Copy each file listed in one-api-deploy-files-v3.txt before upload',
        ]).PHP_EOL);
        file_put_contents(storage_path('app/one-api-rollback-v3.sh'), implode(PHP_EOL, [
            '#!/bin/sh',
            '# Restore from BACKUP_DIR; remove new-only One API files listed in one-api-phase-3-clean-runtime-files.txt',
            '/opt/alt/php-fpm83/usr/bin/php artisan config:clear',
            '/opt/alt/php-fpm83/usr/bin/php artisan route:clear',
            '/opt/alt/php-fpm83/usr/bin/php artisan view:clear',
        ]).PHP_EOL);
        file_put_contents(storage_path('app/one-api-required-config-v3.md'), "soap_url required for live SOAP.\nEnable one_api_supplier module and connection in admin.\n");
    }

    /**
     * @param  list<string>  $runtime
     * @param  list<string>  $shared
     */
    private function writeStageScript(array $runtime, array $shared): void
    {
        $newOnly = array_values(array_filter($runtime, static fn (string $p): bool => str_contains($p, 'OneApi') || str_contains($p, 'one-api') || str_contains($p, 'one_api')));
        $lines = ['# Explicit git add for new One API files only (do not run automatically)'];
        foreach ($newOnly as $file) {
            $lines[] = 'git add -- '.str_replace('\\', '/', $file);
        }
        file_put_contents(storage_path('app/one-api-phase-3-stage-new-files.ps1'), implode(PHP_EOL, $lines).PHP_EOL);

        $md = "# Shared files — use `git add -p`\n\n";
        foreach ($shared as $file) {
            $md .= "- `{$file}` — review hunks; may include non–One API changes (especially `bootstrap/app.php`, Sabre-adjacent configs).\n";
        }
        file_put_contents(storage_path('app/one-api-phase-3-shared-file-hunks.md'), $md);
        file_put_contents(storage_path('app/one-api-phase-3-stage-shared-files.md'), $md);
    }
}
