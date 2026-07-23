<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates Phase 6 isolation package manifests and v7 backup/rollback/deploy scripts (no git staging).
 */
class OneApiPhase7InventoryCommand extends Command
{
    protected $signature = 'ota:one-api-phase-7-inventory';

    protected $description = 'Generate phase-7 One API runtime manifests, v7 backup/rollback/deploy artifacts.';

    public function handle(): int
    {
        $runtimePath = storage_path('app/one-api-phase-7-runtime-files.txt');
        if (! is_file($runtimePath)) {
            $this->error('Missing '.$runtimePath);

            return self::FAILURE;
        }

        $runtime = $this->readLines($runtimePath);
        $shared = $this->readLines(storage_path('app/one-api-phase-3-shared-files.txt'));
        $deploy = array_values(array_unique(array_merge($runtime, $shared)));
        sort($deploy);

        $this->write(storage_path('app/one-api-deploy-files-v7.txt'), $deploy);
        $this->writeSftpv7($deploy);
        $this->writeBackupv7($deploy);
        $this->writeRollbackv7($deploy);
        $this->writePostDeployv7();
        $this->writeRequiredConfigv7();
        $this->writeExcludedRuntime();
        $this->writeIsolationPackage();

        $this->info('Deploy paths: '.count($deploy));
        $this->info('Runtime-only paths: '.count($runtime));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function readLines(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', file($path) ?: [])));
    }

    /**
     * @param  list<string>  $lines
     */
    private function write(string $path, array $lines): void
    {
        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
    }

    /**
     * @param  list<string>  $deploy
     */
    private function writeSftpv7(array $deploy): void
    {
        $remote = '/home/pkjetp/jetpk_app';
        $publicMirror = [
            'public/js/ota-one-api-checkout.js' => '/home/pkjetp/public_html/js/ota-one-api-checkout.js',
        ];
        $lines = [
            'lcd C:/Users/khadi/ota-jetpk',
            'cd /home/pkjetp/jetpk_app',
        ];
        $dirs = [];
        foreach ($deploy as $file) {
            $file = str_replace('\\', '/', $file);
            $dir = dirname($file);
            if ($dir !== '.' && ! isset($dirs[$dir])) {
                $dirs[$dir] = true;
                $lines[] = '-mkdir '.$remote.'/'.$dir;
            }
            $lines[] = 'put '.$file.' '.$remote.'/'.$file;
        }
        foreach ($publicMirror as $local => $remotePath) {
            if (in_array($local, $deploy, true) || is_file(base_path($local))) {
                $lines[] = 'lcd C:/Users/khadi/ota-jetpk';
                $lines[] = 'put '.$local.' '.$remotePath;
            }
        }
        $this->write(storage_path('app/one-api-sftp-upload-v7.txt'), $lines);
    }

    /**
     * @param  list<string>  $deploy
     */
    private function writeBackupv7(array $deploy): void
    {
        $appRoot = '/home/pkjetp/jetpk_app';
        $publicMirror = [
            'public/js/ota-one-api-checkout.js' => '/home/pkjetp/public_html/js/ota-one-api-checkout.js',
        ];
        $paths = [];
        foreach ($deploy as $rel) {
            $paths[] = ['rel' => $rel, 'abs' => $appRoot.'/'.str_replace('\\', '/', $rel), 'mirror' => ''];
        }
        foreach ($publicMirror as $rel => $mirrorAbs) {
            $paths[] = ['rel' => $rel, 'abs' => $appRoot.'/'.str_replace('\\', '/', $rel), 'mirror' => $mirrorAbs];
        }

        $script = [
            '#!/bin/sh',
            'set -eu',
            'APP_ROOT="'.$appRoot.'"',
            'BACKUP_DIR="/home/pkjetp/backups/one-api-v7-$(date +%Y%m%d%H%M%S)"',
            'MANIFEST="$BACKUP_DIR/manifest.tsv"',
            'mkdir -p "$BACKUP_DIR"',
            'echo "original_path\tbackup_path\tstatus\tsize_bytes\tsha256\tmode" > "$MANIFEST"',
        ];

        foreach ($paths as $row) {
            $abs = $row['abs'];
            $mirror = $row['mirror'];
            $safeRel = str_replace('/', '_', $row['rel']);
            $script[] = 'if [ -f "'.$abs.'" ]; then';
            $script[] = '  BK="$BACKUP_DIR/'.$safeRel.'"';
            $script[] = '  mkdir -p "$(dirname "$BK")"';
            $script[] = '  cp -p "'.$abs.'" "$BK"';
            $script[] = '  SZ=$(wc -c < "$BK" | tr -d " ")';
            $script[] = '  SHA=$(sha256sum "$BK" | awk \'{print $1}\')';
            $script[] = '  MOD=$(stat -c %a "$BK" 2>/dev/null || stat -f %OLp "$BK")';
            $script[] = '  echo "'.$abs.'\t$BK\tEXISTING\t$SZ\t$SHA\t$MOD" >> "$MANIFEST"';
            $script[] = 'else';
            $script[] = '  echo "'.$abs.'\t\tNEW_ABSENT\t0\t-\t-" >> "$MANIFEST"';
            $script[] = 'fi';
            if ($mirror !== '') {
                $script[] = 'if [ -f "'.$mirror.'" ]; then';
                $script[] = '  MBK="$BACKUP_DIR/public_html_'.str_replace('/', '_', basename($mirror)).'"';
                $script[] = '  cp -p "'.$mirror.'" "$MBK"';
                $script[] = '  SZ=$(wc -c < "$MBK" | tr -d " ")';
                $script[] = '  SHA=$(sha256sum "$MBK" | awk \'{print $1}\')';
                $script[] = '  MOD=$(stat -c %a "$MBK" 2>/dev/null || stat -f %OLp "$MBK")';
                $script[] = '  echo "'.$mirror.'\t$MBK\tEXISTING\t$SZ\t$SHA\t$MOD" >> "$MANIFEST"';
                $script[] = 'else';
                $script[] = '  echo "'.$mirror.'\t\tNEW_ABSENT\t0\t-\t-" >> "$MANIFEST"';
                $script[] = 'fi';
            }
        }

        $script[] = 'echo "Backup complete: $BACKUP_DIR"';
        $script[] = 'echo "Manifest: $MANIFEST"';

        file_put_contents(storage_path('app/one-api-predeploy-backup-v7.sh'), implode(PHP_EOL, $script).PHP_EOL);
    }

    /**
     * @param  list<string>  $deploy
     */
    private function writeRollbackv7(array $deploy): void
    {
        $appRoot = '/home/pkjetp/jetpk_app';
        $php = '/opt/alt/php-fpm83/usr/bin/php';
        $script = [
            '#!/bin/sh',
            'set -eu',
            'BACKUP_DIR="${1:-}"',
            'if [ -z "$BACKUP_DIR" ] || [ ! -d "$BACKUP_DIR" ]; then',
            '  echo "Usage: $0 /home/pkjetp/backups/one-api-v7-YYYYMMDDHHMMSS" >&2',
            '  exit 1',
            'fi',
            'MANIFEST="$BACKUP_DIR/manifest.tsv"',
            'if [ ! -f "$MANIFEST" ]; then',
            '  echo "Manifest missing: $MANIFEST" >&2',
            '  exit 1',
            'fi',
            'APP_ROOT="'.$appRoot.'"',
            'RESTORED=0',
            'REMOVED=0',
            'SKIPPED=0',
            'while IFS="$(printf \'\\t\')" read -r orig backup status size sha mode; do',
            '  [ "$orig" = "original_path" ] && continue',
            '  if [ "$status" = "EXISTING" ] && [ -n "$backup" ] && [ -f "$backup" ]; then',
            '    mkdir -p "$(dirname "$orig")"',
            '    cp -p "$backup" "$orig"',
            '    RESTORED=$((RESTORED+1))',
            '  elif [ "$status" = "NEW_ABSENT" ] && [ -f "$orig" ]; then',
            '    rm -f "$orig"',
            '    REMOVED=$((REMOVED+1))',
            '  else',
            '    SKIPPED=$((SKIPPED+1))',
            '  fi',
            'done < "$MANIFEST"',
            'cd "$APP_ROOT"',
            $php.' artisan config:clear',
            $php.' artisan route:clear',
            $php.' artisan view:clear',
            $php.' artisan config:cache',
            $php.' artisan route:cache',
            $php.' artisan view:cache',
            'echo "Rollback restored=$RESTORED removed=$REMOVED skipped=$SKIPPED"',
        ];

        file_put_contents(storage_path('app/one-api-rollback-v7.sh'), implode(PHP_EOL, $script).PHP_EOL);
    }

    private function writePostDeployv7(): void
    {
        $php = '/opt/alt/php-fpm83/usr/bin/php';
        file_put_contents(storage_path('app/one-api-post-deploy-v7.sh'), implode(PHP_EOL, [
            '#!/bin/sh',
            'set -eu',
            'cd /home/pkjetp/jetpk_app',
            $php.' artisan package:discover --ansi || true',
            $php.' artisan migrate --force --no-interaction || true',
            $php.' artisan config:clear',
            $php.' artisan route:clear',
            $php.' artisan view:clear',
            $php.' artisan config:cache',
            $php.' artisan route:cache',
            $php.' artisan view:cache',
            '# Replace CONNECTION_ID_PLACEHOLDER with the One API SupplierConnection id',
            $php.' artisan ota:one-api-connection-audit --connection=CONNECTION_ID_PLACEHOLDER',
            'echo "Verify GET / returns 200; verify admin api-settings loads; no live supplier probes in this script."',
        ]).PHP_EOL);
    }

    private function writeRequiredConfigv7(): void
    {
        file_put_contents(storage_path('app/one-api-required-config-v7.md'), implode(PHP_EOL, [
            '# One API required configuration (v7)',
            '',
            '- Enable platform module `one_api_supplier`.',
            '- `bootstrap/providers.php` must register `App\\Providers\\OneApiServiceProvider`.',
            '- Production binds `LiveOneApiSoapTransport`; fixture transport is never HTTP-selectable.',
            '- Create/update SupplierConnection provider `one_api` with REST + SOAP endpoints.',
            '- `soap_url` required for live price/book/read (vendor cert endpoint availability is a live blocker).',
            '- Do not set fixture paths in credentials, query params, or env.',
            '',
        ]).PHP_EOL);
    }

    private function writeExcludedRuntime(): void
    {
        $excluded = [
            'tests/ — PHPUnit and fixtures (not deployed)',
            'docs/integrations/one-api/ — integration docs (not deployed unless ops policy)',
            'storage/app/one-api-phase-*.txt — local manifests (ops runbook only)',
            'storage/app/one-api-*.sh — ops scripts (secure copy, not public web)',
            'tests/Fixtures/Suppliers/OneApi/ — CI/matrix only',
        ];
        $this->write(storage_path('app/one-api-phase-7-excluded-files.txt'), $excluded);
    }

    private function writeIsolationPackage(): void
    {
        $root = base_path();
        $diff = trim((string) shell_exec('cd '.escapeshellarg($root).' && git diff --name-only HEAD 2>nul'));
        $untracked = trim((string) shell_exec('cd '.escapeshellarg($root).' && git ls-files --others --exclude-standard 2>nul'));

        $all = array_unique(array_filter(array_merge(
            preg_split('/\R/', $diff) ?: [],
            preg_split('/\R/', $untracked) ?: [],
        )));

        $new = [];
        $tests = [];
        $docs = [];
        $generated = [];
        $runtime = $this->readLines(storage_path('app/one-api-phase-7-runtime-files.txt'));
        foreach ($all as $path) {
            $path = trim(str_replace('\\', '/', $path));
            if ($path === '' || ! preg_match('#one.?api|OneApi|one_api#i', $path)) {
                continue;
            }
            if (str_starts_with($path, 'tests/')) {
                $tests[] = $path;

                continue;
            }
            if (str_starts_with($path, 'docs/integrations/one-api/') || str_starts_with($path, 'docs/phases/ONE-API')) {
                $docs[] = $path;

                continue;
            }
            if (str_starts_with($path, 'storage/app/one-api')) {
                $generated[] = $path;

                continue;
            }
            if (str_starts_with($path, 'app/') && is_file(base_path($path))) {
                $tracked = trim((string) shell_exec('cd '.escapeshellarg($root).' && git ls-files -- '.escapeshellarg($path).' 2>nul'));
                if ($tracked === '') {
                    $new[] = $path;
                }
            }
        }

        sort($new);
        sort($tests);
        sort($docs);
        sort($generated);

        $shared = $this->readLines(storage_path('app/one-api-phase-3-shared-files.txt'));
        $mixed = [
            'bootstrap/app.php — One API JSON exception handler only; git add -p',
            'bootstrap/providers.php — OneApiServiceProvider registration only; git add -p',
            'routes/web.php — One API checkout route group + auth only; git add -p',
        ];

        $this->write(storage_path('app/one-api-phase-7-tests.txt'), $tests);
        $this->write(storage_path('app/one-api-phase-7-docs.txt'), $docs);
        $this->write(storage_path('app/one-api-phase-7-shared-files.txt'), $shared);
        $this->write(storage_path('app/one-api-phase-7-mixed-files.txt'), $mixed);
        $this->write(storage_path('app/one-api-phase-7-public-mirrors.txt'), ['public/js/ota-one-api-checkout.js => /home/pkjetp/public_html/js/ota-one-api-checkout.js']);

        $dedicated = array_values(array_filter($runtime, fn (string $p) => ! in_array($p, $shared, true) && $p !== 'routes/web.php' && ! str_starts_with($p, 'bootstrap/')));
        $this->write(storage_path('app/one-api-phase-7-runtime-files.txt'), $runtime);

        $stageNew = ['# Review-only staging (do not run automatically)'];
        foreach ($new as $file) {
            $stageNew[] = 'git add -- '.$file;
        }
        file_put_contents(storage_path('app/one-api-phase-7-stage-new-files.ps1'), implode(PHP_EOL, $stageNew).PHP_EOL);

        $stageShared = ['# Shared files — review hunks only'];
        foreach ($shared as $file) {
            $stageShared[] = 'git add -p -- '.$file;
        }
        foreach ($mixed as $note) {
            $stageShared[] = '# '.$note;
        }
        file_put_contents(storage_path('app/one-api-phase-7-stage-shared-files.ps1'), implode(PHP_EOL, $stageShared).PHP_EOL);

        $md = "# Phase 6 interactive staging\n\n";
        $md .= "Generated at inventory run. Do not `git add .`.\n\n";
        $md .= "## New untracked app files\n\n";
        foreach ($new as $file) {
            $md .= "- `git add -- {$file}`\n";
        }
        $md .= "\n## Mixed\n\n";
        foreach ($mixed as $m) {
            $md .= "- {$m}\n";
        }
        file_put_contents(storage_path('app/one-api-phase-7-interactive-stage.md'), $md);

        $patch = trim((string) shell_exec('cd '.escapeshellarg($root).' && git diff HEAD -- '.implode(' ', array_map('escapeshellarg', array_slice($runtime, 0, 40))).' 2>nul'));
        file_put_contents(storage_path('app/one-api-phase-7-review.patch'), ($patch !== '' ? $patch : "# No indexed diff for runtime slice; use git diff on working tree before commit.\n").PHP_EOL);
    }
}
