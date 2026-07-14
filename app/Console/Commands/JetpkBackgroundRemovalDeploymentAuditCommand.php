<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class JetpkBackgroundRemovalDeploymentAuditCommand extends Command
{
    protected $signature = 'jetpk:background-removal-deployment-audit';

    protected $description = 'Report background-removal deployment suitability on this server (read-only)';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY deployment suitability report.');
        $this->newLine();

        $phpVersion = PHP_VERSION;
        $gd = extension_loaded('gd');
        $imagick = extension_loaded('imagick');
        $procOpen = function_exists('proc_open') && ! in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
        $symfonyProcess = class_exists(\Symfony\Component\Process\Process::class);

        $python = trim((string) @shell_exec('python --version 2>&1'));
        $python3 = trim((string) @shell_exec('python3 --version 2>&1'));
        $pythonVersion = $python !== '' ? $python : ($python3 !== '' ? $python3 : 'not detected');

        $venvSupport = $procOpen && ($python !== '' || $python3 !== '');

        $memoryLimit = (string) ini_get('memory_limit');
        $maxExecution = (string) ini_get('max_execution_time');
        $uploadMax = (string) ini_get('upload_max_filesize');
        $postMax = (string) ini_get('post_max_size');

        $ramMb = $this->availableRamMb();
        $diskFreeGb = $this->availableDiskGb();

        $outboundHttp = false;
        try {
            $outboundHttp = Http::timeout(8)->get('https://api.remove.bg/')->status() < 500;
        } catch (\Throwable) {
            $outboundHttp = false;
        }

        $queueConnection = (string) config('queue.default', 'sync');
        $workerSupervision = $this->detectWorkerSupervision();

        $privateWritable = is_writable(storage_path('app/private'));
        $publicWritable = is_writable(storage_path('app/public'));
        $symlinkValid = is_link(public_path('storage')) || is_dir(public_path('storage'));

        $rembgEnabled = (bool) config('background-removal.rembg_http.enabled', false);
        $rembgSuitable = $venvSupport && $gd && $ramMb >= 2048 && $diskFreeGb >= 2.0;

        $recommendation = match (true) {
            $rembgEnabled && $rembgSuitable => 'self-hosted rembg suitable',
            $outboundHttp => 'external remove.bg-compatible provider',
            default => 'background removal disabled',
        };

        $rows = [
            ['PHP version', $phpVersion],
            ['GD availability', $gd ? 'yes' : 'no'],
            ['Imagick availability', $imagick ? 'yes' : 'no'],
            ['proc_open status', $procOpen ? 'enabled' : 'disabled'],
            ['Symfony Process', $symfonyProcess ? 'available' : 'missing'],
            ['Python executable', $pythonVersion],
            ['venv support', $venvSupport ? 'likely' : 'no'],
            ['available RAM (MB)', $ramMb !== null ? (string) $ramMb : 'unknown'],
            ['available disk (GB)', $diskFreeGb !== null ? (string) $diskFreeGb : 'unknown'],
            ['max_execution_time', $maxExecution],
            ['memory_limit', $memoryLimit],
            ['upload_max_filesize', $uploadMax],
            ['post_max_size', $postMax],
            ['outbound HTTPS', $outboundHttp ? 'reachable' : 'blocked/failed'],
            ['queue connection', $queueConnection],
            ['persistent worker supervision', $workerSupervision],
            ['storage/app/private writable', $privateWritable ? 'yes' : 'no'],
            ['storage/app/public writable', $publicWritable ? 'yes' : 'no'],
            ['storage symlink valid', $symlinkValid ? 'yes' : 'no'],
            ['rembg enabled in config', $rembgEnabled ? 'yes (review)' : 'no'],
            ['final recommendation', $recommendation],
        ];

        $this->table(['Check', 'Value'], $rows);

        $reportDir = storage_path('app/audits/jetpk-9h-c2');
        File::ensureDirectoryExists($reportDir);
        $reportPath = $reportDir.'/deployment-audit-'.now()->format('Ymd-His').'.json';
        File::put($reportPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'hostname' => gethostname() ?: 'unknown',
            'checks' => collect($rows)->mapWithKeys(fn (array $row): array => [$row[0] => $row[1]])->all(),
            'recommendation' => $recommendation,
            'rembg_policy' => 'do not install rembg until explicitly approved',
        ], JSON_PRETTY_PRINT));

        $this->newLine();
        $this->line('report='.$reportPath);
        $this->line('Sync queue note: provider timeout must stay bounded; true async requires a queue worker later.');

        return self::SUCCESS;
    }

    private function availableRamMb(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        $meminfo = @file_get_contents('/proc/meminfo');
        if (! is_string($meminfo)) {
            return null;
        }

        if (preg_match('/MemAvailable:\s+(\d+)\s+kB/i', $meminfo, $matches) === 1) {
            return (int) round(((int) $matches[1]) / 1024);
        }

        return null;
    }

    private function availableDiskGb(): ?float
    {
        $free = @disk_free_space(storage_path());
        if ($free === false) {
            return null;
        }

        return round($free / 1024 / 1024 / 1024, 2);
    }

    private function detectWorkerSupervision(): string
    {
        if ((string) config('queue.default') === 'sync') {
            return 'none (sync queue)';
        }

        if (file_exists(base_path('supervisor.conf')) || file_exists('/etc/supervisord.conf')) {
            return 'supervisor config detected';
        }

        return 'unknown — verify cron/horizon/supervisor manually';
    }
}
