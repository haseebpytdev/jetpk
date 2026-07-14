<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class JetpkMasterPageParityAuditCommand extends Command
{
    protected $signature = 'jetpk:master-page-parity-audit {--master-path=C:\\Users\\khadi\\ota : Read-only OTA master path}';

    protected $description = 'Compare JetPK page coverage against OTA master (read-only master route list)';

    /** @var list<array{key:string,jetpk_uri:string,master_uri:string,portal:string}> */
    private array $equivalenceMap = [
        ['key' => 'home', 'jetpk_uri' => '/', 'master_uri' => '/', 'portal' => 'public'],
        ['key' => 'login', 'jetpk_uri' => '/login', 'master_uri' => '/login', 'portal' => 'public'],
        ['key' => 'register', 'jetpk_uri' => '/register', 'master_uri' => '/register', 'portal' => 'public'],
        ['key' => 'lookup-booking', 'jetpk_uri' => '/lookup-booking', 'master_uri' => '/lookup-booking', 'portal' => 'public'],
        ['key' => 'groups-search', 'jetpk_uri' => '/groups/search', 'master_uri' => '/groups/search', 'portal' => 'group_ticketing'],
        ['key' => 'flights-results', 'jetpk_uri' => '/flights/results', 'master_uri' => '/flights/results', 'portal' => 'public'],
        ['key' => 'admin-dashboard', 'jetpk_uri' => '/admin', 'master_uri' => '/admin', 'portal' => 'admin'],
        ['key' => 'staff-dashboard', 'jetpk_uri' => '/staff', 'master_uri' => '/staff', 'portal' => 'staff'],
        ['key' => 'agent-dashboard', 'jetpk_uri' => '/agent', 'master_uri' => '/agent', 'portal' => 'agent'],
        ['key' => 'customer-dashboard', 'jetpk_uri' => '/customer', 'master_uri' => '/customer', 'portal' => 'customer'],
        ['key' => 'devcp-login', 'jetpk_uri' => '/dev/cp/login', 'master_uri' => '/dev/cp/login', 'portal' => 'devcp'],
        ['key' => 'devcp-index', 'jetpk_uri' => '/dev/cp', 'master_uri' => '/dev/cp', 'portal' => 'devcp'],
        ['key' => 'jetpk-home-legacy', 'jetpk_uri' => '/', 'master_uri' => '/jetpk/home', 'portal' => 'jetpk_only_expected'],
        ['key' => 'client-preview', 'jetpk_uri' => '—', 'master_uri' => '/jetpk/login', 'portal' => 'master_only_intentionally_excluded'],
    ];

    public function handle(): int
    {
        $masterPath = rtrim((string) $this->option('master-path'), '\\/');
        $this->line('Classification: READ-ONLY master page parity audit.');
        $this->line('db_write_attempted=false');
        $this->line("master_path={$masterPath}");
        $this->newLine();

        if (! is_dir($masterPath)) {
            $this->error('Master path not found.');

            return self::FAILURE;
        }

        $jetpkUris = $this->collectUris(Route::getRoutes());
        $masterUris = $this->readMasterRouteUris($masterPath);

        $results = [];
        foreach ($this->equivalenceMap as $map) {
            $jetpkExists = $map['jetpk_uri'] === '—' ? false : isset($jetpkUris[$this->normalizeUri($map['jetpk_uri'])]);
            $masterExists = isset($masterUris[$this->normalizeUri($map['master_uri'])]);

            $bucket = 'equivalent_and_passed';
            if ($map['portal'] === 'master_only_intentionally_excluded') {
                $bucket = $masterExists && ! $jetpkExists ? 'master_only_intentionally_excluded' : ($jetpkExists ? 'jetpk_only_expected' : 'master_only_intentionally_excluded');
            } elseif ($map['portal'] === 'jetpk_only_expected') {
                $bucket = $jetpkExists ? 'jetpk_only_expected' : 'jetpk_missing';
            } elseif ($jetpkExists && $masterExists) {
                $bucket = 'equivalent_and_passed';
            } elseif ($jetpkExists && ! $masterExists) {
                $bucket = 'jetpk_only_expected';
            } elseif (! $jetpkExists && $masterExists) {
                $bucket = 'jetpk_missing';
            } else {
                $bucket = 'route_exists_but_broken';
            }

            $results[] = array_merge($map, [
                'jetpk_registered' => $jetpkExists,
                'master_registered' => $masterExists,
                'bucket' => $bucket,
            ]);
        }

        $dir = storage_path('app/audits');
        File::ensureDirectoryExists($dir);
        $jsonPath = $dir.'/jetpk-master-page-parity.json';
        $mdPath = $dir.'/jetpk-master-page-parity.md';

        File::put($jsonPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'master_path' => $masterPath,
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $md = "# JetPK master page parity\n\nMaster: `{$masterPath}`\n\n";
        $md .= "| Key | JetPK URI | Master URI | Bucket |\n|---|---|---|---|\n";
        foreach ($results as $row) {
            $md .= sprintf("| %s | %s | %s | %s |\n", $row['key'], $row['jetpk_uri'], $row['master_uri'], $row['bucket']);
        }
        File::put($mdPath, $md);

        $this->table(['key', 'jetpk', 'master', 'bucket'], array_map(
            fn (array $r) => [$r['key'], $r['jetpk_uri'], $r['master_uri'], $r['bucket']],
            $results,
        ));

        $this->line("JSON: {$jsonPath}");
        $this->line("MD: {$mdPath}");

        $missing = count(array_filter($results, fn (array $r) => $r['bucket'] === 'jetpk_missing'));

        return $missing === 0 ? self::SUCCESS : self::FAILURE;
    }

  /**
   * @return array<string, true>
   */
    private function collectUris($routes): array
    {
        $uris = [];
        foreach ($routes as $route) {
            $uris[$this->normalizeUri('/'.ltrim($route->uri(), '/'))] = true;
        }

        return $uris;
    }

    /**
     * @return array<string, true>
     */
    private function readMasterRouteUris(string $masterPath): array
    {
        $php = PHP_BINARY;
        $artisan = $masterPath.DIRECTORY_SEPARATOR.'artisan';
        if (! is_file($artisan)) {
            return [];
        }

        $command = escapeshellarg($php).' '.escapeshellarg($artisan).' route:list --json 2>nul';
        $output = shell_exec($command);
        if (! is_string($output) || trim($output) === '') {
            return [];
        }

        $decoded = json_decode($output, true);
        if (! is_array($decoded)) {
            return [];
        }

        $uris = [];
        foreach ($decoded as $row) {
            $uri = (string) ($row['uri'] ?? '');
            if ($uri !== '') {
                $uris[$this->normalizeUri('/'.ltrim($uri, '/'))] = true;
            }
        }

        return $uris;
    }

    private function normalizeUri(string $uri): string
    {
        if ($uri === '/' || $uri === '') {
            return '/';
        }

        return '/'.trim($uri, '/');
    }
}
