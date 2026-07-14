<?php

namespace App\Support\Audits;

use App\Support\Bookings\SabreVerifiedAutoPnrReadiness;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Read-only production operations audit checks (F7).
 *
 * No supplier HTTP, no DB writes, no secret output.
 */
final class ProductionReadinessAuditService
{
    /** @var list<string> */
    private const REQUIRED_COMMANDS = [
        'ota:smoke-live-routes',
        'ota:route-page-health-audit',
        'ota:audit-sabre-status',
        'devcp:seed-default-packages',
    ];

    /** @var list<string> */
    private const WRITABLE_PATHS = [
        'storage/logs',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'bootstrap/cache',
    ];

    /**
     * @return array{
     *     findings: list<array{status: string, label: string, detail: string, section: string}>,
     *     recommendations: list<string>,
     *     counts: array{pass: int, warn: int, fail: int}
     * }
     */
    public function run(): array
    {
        $findings = [];

        $this->addEnvChecks($findings);
        $this->addInfrastructureChecks($findings);
        $this->addStorageChecks($findings);
        $this->addLogChecks($findings);
        $this->addDeploymentChecks($findings);
        $this->addCommandAvailabilityChecks($findings);
        $this->addSabreMutationChecks($findings);
        $this->addSchedulerChecks($findings);
        $this->addQueueChecks($findings);
        $this->addFailedJobsChecks($findings);
        $this->addBackupChecks($findings);

        $counts = ['pass' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($findings as $finding) {
            $status = strtolower($finding['status']);
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return [
            'findings' => $findings,
            'recommendations' => $this->buildRecommendations($findings),
            'counts' => $counts,
        ];
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addEnvChecks(array &$findings): void
    {
        $env = strtolower(trim((string) config('app.env')));
        $envCategory = $this->categorizeAppEnv($env);
        $findings[] = [
            'section' => 'Environment',
            'status' => $envCategory === 'production' ? 'pass' : 'warn',
            'label' => 'APP_ENV',
            'detail' => 'category='.$envCategory,
        ];

        $debug = (bool) config('app.debug');
        $debugLabel = $debug ? 'unsafe' : 'safe';
        if ($debug) {
            $debugStatus = $envCategory === 'production' ? 'fail' : 'warn';
        } else {
            $debugStatus = 'pass';
        }
        $findings[] = [
            'section' => 'Environment',
            'status' => $debugStatus,
            'label' => 'APP_DEBUG',
            'detail' => $debugLabel,
        ];

        $appUrl = trim((string) config('app.url'));
        $urlPresent = $appUrl !== '';
        $hostCategory = $this->categorizeAppUrlHost($appUrl);
        $urlStatus = 'pass';
        if (! $urlPresent) {
            $urlStatus = 'warn';
        } elseif ($hostCategory === 'localhost' && $envCategory === 'production') {
            $urlStatus = 'warn';
        }
        $findings[] = [
            'section' => 'Environment',
            'status' => $urlStatus,
            'label' => 'APP_URL',
            'detail' => 'present='.($urlPresent ? 'yes' : 'no').', host_category='.$hostCategory,
        ];

        $maintenanceActive = App::isDownForMaintenance();
        $findings[] = [
            'section' => 'Environment',
            'status' => $maintenanceActive ? 'warn' : 'pass',
            'label' => 'Maintenance mode',
            'detail' => $maintenanceActive ? 'active' : 'inactive',
        ];
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addInfrastructureChecks(array &$findings): void
    {
        $cacheDriver = trim((string) config('cache.default'));
        $findings[] = [
            'section' => 'Infrastructure',
            'status' => $cacheDriver !== '' ? 'pass' : 'fail',
            'label' => 'Cache driver',
            'detail' => 'category='.($cacheDriver !== '' ? $cacheDriver : 'unknown'),
        ];

        $sessionDriver = trim((string) config('session.driver'));
        $findings[] = [
            'section' => 'Infrastructure',
            'status' => $sessionDriver !== '' ? 'pass' : 'warn',
            'label' => 'Session driver',
            'detail' => 'category='.($sessionDriver !== '' ? $sessionDriver : 'unknown'),
        ];

        $queueConnection = trim((string) config('queue.default'));
        $findings[] = [
            'section' => 'Infrastructure',
            'status' => $queueConnection !== '' ? 'pass' : 'fail',
            'label' => 'Queue connection',
            'detail' => 'category='.($queueConnection !== '' ? $queueConnection : 'unknown'),
        ];

        $mailMailer = trim((string) config('mail.default'));
        $fromAddress = trim((string) config('mail.from.address'));
        $fromPresent = $fromAddress !== '';
        $mailStatus = 'pass';
        if ($mailMailer === '') {
            $mailStatus = 'fail';
        } elseif (! $fromPresent) {
            $mailStatus = 'warn';
        }
        $findings[] = [
            'section' => 'Infrastructure',
            'status' => $mailStatus,
            'label' => 'Mail',
            'detail' => 'mailer_category='.($mailMailer !== '' ? $mailMailer : 'unknown')
                .', from_address_present='.($fromPresent ? 'yes' : 'no'),
        ];
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addStorageChecks(array &$findings): void
    {
        $storageLinkExists = is_link(public_path('storage')) || is_dir(public_path('storage'));
        $findings[] = [
            'section' => 'Storage',
            'status' => $storageLinkExists ? 'pass' : 'warn',
            'label' => 'Storage link',
            'detail' => $storageLinkExists ? 'yes' : 'no',
        ];

        foreach (self::WRITABLE_PATHS as $relativePath) {
            $absolutePath = base_path($relativePath);
            File::ensureDirectoryExists($absolutePath);
            $writable = $this->isPathWritable($absolutePath);
            $findings[] = [
                'section' => 'Storage',
                'status' => $writable ? 'pass' : 'fail',
                'label' => 'Writable: '.$relativePath,
                'detail' => $writable ? 'yes' : 'no',
            ];
        }
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addLogChecks(array &$findings): void
    {
        $logPath = storage_path('logs/laravel.log');
        $sizeSummary = $this->logFileSizeSummary($logPath);
        $logStatus = 'pass';
        if ($sizeSummary === 'unreadable') {
            $logStatus = is_file($logPath) ? 'fail' : 'pass';
        } elseif ($sizeSummary === 'over_50mb') {
            $logStatus = 'warn';
        } elseif ($sizeSummary === '10-50mb') {
            $logStatus = 'warn';
        }
        $findings[] = [
            'section' => 'Logs',
            'status' => $logStatus,
            'label' => 'Laravel log size',
            'detail' => $sizeSummary,
        ];

        $errorCount = $this->countRecentProductionErrors();
        $errorStatus = 'pass';
        if ($errorCount > 5) {
            $errorStatus = 'fail';
        } elseif ($errorCount > 0) {
            $errorStatus = 'warn';
        }
        $findings[] = [
            'section' => 'Logs',
            'status' => $errorStatus,
            'label' => 'Recent production.ERROR count',
            'detail' => (string) $errorCount,
        ];
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addDeploymentChecks(array &$findings): void
    {
        $docsPresent = is_dir(base_path('docs'));
        $envCategory = $this->categorizeAppEnv(strtolower(trim((string) config('app.env'))));
        $docsStatus = ($docsPresent && $envCategory === 'production') ? 'warn' : 'pass';
        $findings[] = [
            'section' => 'Deployment',
            'status' => $docsStatus,
            'label' => 'Docs folder present',
            'detail' => $docsPresent ? 'yes' : 'no',
        ];

        $findings[] = [
            'section' => 'Deployment',
            'status' => 'warn',
            'label' => 'Public asset root',
            'detail' => 'info — on Hostinger verify assets served from separate public_html root (not app root)',
        ];

        $devCpEnabled = (bool) config('ota-developer.enabled');
        $findings[] = [
            'section' => 'Deployment',
            'status' => 'pass',
            'label' => 'Dev CP enabled',
            'detail' => $devCpEnabled ? 'yes' : 'no',
        ];
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addCommandAvailabilityChecks(array &$findings): void
    {
        foreach (self::REQUIRED_COMMANDS as $commandName) {
            $registered = $this->commandRegistered($commandName);
            $findings[] = [
                'section' => 'Commands',
                'status' => $registered ? 'pass' : 'fail',
                'label' => 'Command: '.$commandName,
                'detail' => 'registered='.($registered ? 'yes' : 'no'),
            ];
        }
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addSabreMutationChecks(array &$findings): void
    {
        $flags = $this->sabreMutationFlags();
        foreach ($flags as $label => $enabled) {
            $findings[] = [
                'section' => 'Sabre mutation flags',
                'status' => $enabled ? 'warn' : 'pass',
                'label' => $label,
                'detail' => $enabled ? 'yes' : 'no',
            ];
        }
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addSchedulerChecks(array &$findings): void
    {
        $runnable = $this->scheduleListRunnable();
        $taskCount = $this->scheduledTaskCount();
        $findings[] = [
            'section' => 'Scheduler',
            'status' => $runnable ? 'pass' : 'fail',
            'label' => 'schedule:list runnable',
            'detail' => ($runnable ? 'yes' : 'no').', registered_tasks='.$taskCount,
        ];
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addQueueChecks(array &$findings): void
    {
        $queueConnection = strtolower(trim((string) config('queue.default')));
        if ($queueConnection === 'sync' || $queueConnection === '') {
            $findings[] = [
                'section' => 'Queue',
                'status' => 'pass',
                'label' => 'Queue worker recommendation',
                'detail' => 'sync driver — no worker required',
            ];
        } else {
            $findings[] = [
                'section' => 'Queue',
                'status' => 'warn',
                'label' => 'Queue worker recommendation',
                'detail' => 'non-sync ('.$queueConnection.') — configure supervisor/queue worker',
            ];
        }
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addFailedJobsChecks(array &$findings): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            $findings[] = [
                'section' => 'Queue',
                'status' => 'warn',
                'label' => 'failed_jobs table',
                'detail' => 'available=no',
            ];

            return;
        }

        try {
            $count = (int) DB::table('failed_jobs')->count();
            $status = 'pass';
            if ($count > 10) {
                $status = 'warn';
            }
            $findings[] = [
                'section' => 'Queue',
                'status' => $status,
                'label' => 'failed_jobs table',
                'detail' => 'available=yes, count='.$count,
            ];
        } catch (\Throwable) {
            $findings[] = [
                'section' => 'Queue',
                'status' => 'warn',
                'label' => 'failed_jobs table',
                'detail' => 'available=yes, count=unknown',
            ];
        }
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     */
    private function addBackupChecks(array &$findings): void
    {
        $backup = $this->backupReadiness();
        $allOk = ($backup['database_connection_ok'] ?? false)
            && ($backup['backup_disk_configured'] ?? false)
            && ($backup['backup_directory_writable'] ?? false);

        $findings[] = [
            'section' => 'Backup',
            'status' => ($backup['database_connection_ok'] ?? false) ? ($allOk ? 'pass' : 'warn') : 'fail',
            'label' => 'Backup readiness',
            'detail' => 'database='.(($backup['database_connection_ok'] ?? false) ? 'ok' : 'fail')
                .', disk='.(($backup['backup_disk_configured'] ?? false) ? 'ok' : 'warn')
                .', directory_writable='.(($backup['backup_directory_writable'] ?? false) ? 'yes' : 'no'),
        ];
    }

    /**
     * @param  list<array{status: string, label: string, detail: string, section: string}>  $findings
     * @return list<string>
     */
    private function buildRecommendations(array $findings): array
    {
        $recommendations = [
            'After deployment run: php artisan optimize:clear (and view:clear / route:clear when Blade or routes changed).',
            'Scheduler cron should run every minute: * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1',
            'Run host-level backups periodically; verify with php artisan ota:backup-check (no backup created by this audit).',
        ];

        $debugUnsafe = false;
        $docsOnLive = false;
        $nonSyncQueue = false;

        foreach ($findings as $finding) {
            if ($finding['label'] === 'APP_DEBUG' && $finding['detail'] === 'unsafe') {
                $debugUnsafe = true;
            }
            if ($finding['label'] === 'Docs folder present' && $finding['detail'] === 'yes') {
                $docsOnLive = true;
            }
            if ($finding['label'] === 'Queue worker recommendation'
                && str_contains($finding['detail'], 'non-sync')) {
                $nonSyncQueue = true;
            }
        }

        if ($debugUnsafe) {
            $recommendations[] = 'Set APP_DEBUG=false in .env before client/live traffic (manual ops action — not changed by this command).';
        }
        if ($nonSyncQueue) {
            $recommendations[] = 'Configure a queue worker or supervisor for the active queue connection.';
        }
        if ($docsOnLive) {
            $recommendations[] = 'Remove /docs from live app root after deploy if docs_present=yes (manual — not deleted by this command).';
        }

        $recommendations[] = 'Post-deploy verification: php artisan ota:production-readiness-audit && php artisan ota:smoke-live-routes --guest-only';

        return $recommendations;
    }

    public function categorizeAppEnv(string $env): string
    {
        $env = strtolower(trim($env));
        if ($env === '') {
            return 'unknown';
        }
        if (in_array($env, ['production', 'prod'], true)) {
            return 'production';
        }
        if (in_array($env, ['local', 'testing', 'staging', 'development', 'dev'], true)) {
            return 'non-production';
        }

        return 'unknown';
    }

    public function categorizeAppUrlHost(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return 'unknown';
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return 'unknown';
        }

        $host = strtolower($host);
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true) || str_ends_with($host, '.local')) {
            return 'localhost';
        }
        if (str_contains($host, 'staging') || str_contains($host, 'stage') || str_contains($host, 'dev.')) {
            return 'staging';
        }

        return 'production-domain';
    }

    public function isPathWritable(string $path): bool
    {
        File::ensureDirectoryExists($path);

        return is_writable($path);
    }

    public function logFileSizeSummary(string $logPath): string
    {
        if (! is_file($logPath)) {
            return 'absent';
        }
        if (! is_readable($logPath)) {
            return 'unreadable';
        }

        $bytes = filesize($logPath);
        if ($bytes === false) {
            return 'unreadable';
        }

        $mb = $bytes / (1024 * 1024);
        if ($mb < 10) {
            return 'under_10mb';
        }
        if ($mb <= 50) {
            return '10-50mb';
        }

        return 'over_50mb';
    }

    public function countRecentProductionErrors(int $tailLines = 500): int
    {
        $path = storage_path('logs/laravel.log');
        if (! is_readable($path)) {
            return 0;
        }

        try {
            $content = File::get($path);
            $allLines = explode("\n", $content);
            $tail = array_slice($allLines, -$tailLines);
            $count = 0;
            foreach ($tail as $line) {
                if ($line !== '' && str_contains($line, 'production.ERROR')) {
                    $count++;
                }
            }

            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function commandRegistered(string $name): bool
    {
        return array_key_exists($name, Artisan::all());
    }

    public function scheduleListRunnable(): bool
    {
        try {
            Artisan::call('schedule:list', [], new NullOutput);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function scheduledTaskCount(): int
    {
        $consolePath = base_path('routes/console.php');
        if (! is_readable($consolePath)) {
            return 0;
        }

        $content = (string) file_get_contents($consolePath);
        preg_match_all('/Schedule::command\s*\(/', $content, $matches);

        return count($matches[0] ?? []);
    }

    /**
     * @return array<string, bool>
     */
    public function sabreMutationFlags(): array
    {
        return [
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
            'auto_pnr_enabled' => (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false),
            'public_auto_pnr_enabled' => SabreVerifiedAutoPnrReadiness::isPublicVerifiedAutoPnrEnabled(),
            'cancellation_enabled' => (bool) config('suppliers.sabre.cancel_enabled', false),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function backupReadiness(): array
    {
        $dbConnectionOk = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $dbConnectionOk = false;
        }

        $backupDisk = (string) config('ota.backup.disk', 'local');
        $backupPath = trim((string) config('ota.backup.path', 'backups'), '/');
        $backupDiskConfigured = config('filesystems.disks.'.$backupDisk) !== null;

        $backupDirectoryWritable = false;
        if ($backupDiskConfigured) {
            try {
                $backupRootPath = Storage::disk($backupDisk)->path('/');
                $backupDirectory = rtrim($backupRootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$backupPath;
                File::ensureDirectoryExists($backupDirectory);
                $backupDirectoryWritable = is_writable($backupDirectory);
            } catch (\Throwable) {
                $backupDirectoryWritable = false;
            }
        }

        return [
            'database_connection_ok' => $dbConnectionOk,
            'backup_disk_configured' => $backupDiskConfigured,
            'backup_directory_writable' => $backupDirectoryWritable,
        ];
    }

    /**
     * @return list<string>
     */
    public static function forbiddenOutputPatterns(): array
    {
        return array_merge(LiveRouteSmokeCatalog::forbiddenResponsePatterns(), [
            'APP_KEY=',
            'DB_PASSWORD',
            'MAIL_PASSWORD',
            'SABRE_',
            'client_id=',
        ]);
    }
}
