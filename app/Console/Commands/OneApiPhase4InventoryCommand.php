<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates Phase 4 isolation package manifests, v4 backup/rollback/deploy scripts (no git staging).
 */
class OneApiPhase4InventoryCommand extends Command
{
    protected $signature = 'ota:one-api-phase-4-inventory';

    protected $description = 'Generate phase-4 One API runtime manifests, v4 backup/rollback/deploy artifacts.';

    public function handle(): int
    {
        $runtimePath = storage_path('app/one-api-phase-4-runtime-files.txt');
        if (! is_file($runtimePath)) {
            $this->error('Missing '.$runtimePath);

            return self::FAILURE;
        }

        $runtime = $this->readLines($runtimePath);
        $shared = $this->readLines(storage_path('app/one-api-phase-3-shared-files.txt'));
        $deploy = array_values(array_unique(array_merge($runtime, $shared)));
        sort($deploy);

        $this->write(storage_path('app/one-api-deploy-files-v4.txt'), $deploy);
        $this->writeSftpV4($deploy);
        $this->writeBackupV4($deploy);
        $this->writeRollbackV4($deploy);
        $this->writePostDeployV4();
        $this->writeRequiredConfigV4();
        $this->writeExcludedRuntime($runtime, $deploy);
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
    private function writeSftpV4(array $deploy): void
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
        $this->write(storage_path('app/one-api-sftp-upload-v4.txt'), $lines);
    }

    /**
     * @param  list<string>  $deploy
     */
    private function writeBackupV4(array $deploy): void
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
            'BACKUP_DIR="/home/pkjetp/backups/one-api-$(date +%Y%m%d%H%M%S)"',
            'MANIFEST="$BACKUP_DIR/manifest.tsv"',
            'mkdir -p "$BACKUP_DIR"',
            'echo "original_path\tbackup_path\tstatus\tsize_bytes\tsha256\tmode" > "$MANIFEST"',
        ];

        foreach ($paths as $row) {
            $rel = $row['rel'];
            $abs = $row['abs'];
            $mirror = $row['mirror'];
            $safeRel = str_replace('/', '_', $rel);
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

        file_put_contents(storage_path('app/one-api-predeploy-backup-v4.sh'), implode(PHP_EOL, $script).PHP_EOL);
    }

    /**
     * @param  list<string>  $deploy
     */
    private function writeRollbackV4(array $deploy): void
    {
        $appRoot = '/home/pkjetp/jetpk_app';
        $php = '/opt/alt/php-fpm83/usr/bin/php';
        $script = [
            '#!/bin/sh',
            'set -eu',
            'BACKUP_DIR="${1:-}"',
            'if [ -z "$BACKUP_DIR" ] || [ ! -d "$BACKUP_DIR" ]; then',
            '  echo "Usage: $0 /home/pkjetp/backups/one-api-YYYYMMDDHHMMSS" >&2',
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
            'echo "Verify homepage and admin login manually; run connection audit without live supplier calls."',
        ];

        file_put_contents(storage_path('app/one-api-rollback-v4.sh'), implode(PHP_EOL, $script).PHP_EOL);
    }

    private function writePostDeployV4(): void
    {
        $php = '/opt/alt/php-fpm83/usr/bin/php';
        file_put_contents(storage_path('app/one-api-post-deploy-v4.sh'), implode(PHP_EOL, [
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
            '# Replace <CONNECTION_ID> with the One API SupplierConnection id',
            $php.' artisan ota:one-api-connection-audit --connection=<CONNECTION_ID>',
            'echo "Verify GET / returns 200; verify admin api-settings loads; no live supplier probes in this script."',
        ]).PHP_EOL);
    }

    private function writeRequiredConfigV4(): void
    {
        file_put_contents(storage_path('app/one-api-required-config-v4.md'), implode(PHP_EOL, [
            '# One API required configuration (v4)',
            '',
            '- Enable platform module `one_api_supplier`.',
            '- Create/update SupplierConnection provider `one_api` with REST + SOAP endpoints.',
            '- `soap_url` required for live price/book/read (vendor endpoint currently unavailable in cert).',
            '- `agent_code` used for DirectBill CompanyName Code on paid book and hold payment.',
            '- Do not set fixture paths in credentials or settings.',
            '- Production must not set diagnostic fixture env vars.',
            '',
        ]).PHP_EOL);
    }

    /**
     * @param  list<string>  $runtime
     * @param  list<string>  $deploy
     */
    private function writeExcludedRuntime(array $runtime, array $deploy): void
    {
        $excluded = [
            'tests/ — PHPUnit and fixtures (not deployed)',
            'docs/integrations/one-api/ — integration docs (not deployed)',
            'storage/app/one-api-phase-*.txt — local manifests and scripts (copy to ops runbook, not app runtime)',
            'storage/app/one-api-*.sh — ops scripts (run on server from secure copy, not via public web)',
            'tests/Fixtures/Suppliers/OneApi/ — sanitized fixtures for CI/matrix only',
        ];
        $this->write(storage_path('app/one-api-phase-4-excluded-runtime-files.txt'), $excluded);
    }

    private function writeIsolationPackage(): void
    {
        $root = base_path();
        $status = trim((string) shell_exec('cd '.escapeshellarg($root).' && git status --short 2>nul'));
        $untracked = trim((string) shell_exec('cd '.escapeshellarg($root).' && git ls-files --others --exclude-standard 2>nul'));
        $diff = trim((string) shell_exec('cd '.escapeshellarg($root).' && git diff --name-only HEAD 2>nul'));

        $all = array_unique(array_filter(array_merge(
            preg_split('/\R/', $diff) ?: [],
            preg_split('/\R/', $untracked) ?: [],
        )));

        $new = [];
        $tests = [];
        $docs = [];
        $generated = [];
        foreach ($all as $path) {
            $path = trim(str_replace('\\', '/', $path));
            if ($path === '' || ! preg_match('#one.?api|OneApi|one_api#i', $path)) {
                continue;
            }
            if (str_starts_with($path, 'tests/')) {
                $tests[] = $path;

                continue;
            }
            if (str_starts_with($path, 'docs/integrations/one-api/')) {
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

        $this->write(storage_path('app/one-api-phase-4-new-files.txt'), $new);
        $this->write(storage_path('app/one-api-phase-4-shared-files.txt'), $shared);
        $this->write(storage_path('app/one-api-phase-4-tests.txt'), $tests);
        $this->write(storage_path('app/one-api-phase-4-docs.txt'), $docs);
        $this->write(storage_path('app/one-api-phase-4-generated-excluded.txt'), $generated);
        $this->write(storage_path('app/one-api-phase-4-mixed-files.txt'), ['bootstrap/app.php — One API JSON exception handler only; use git add -p']);

        $stage = ['# Review-only staging (do not run automatically)'];
        foreach ($new as $file) {
            $stage[] = 'git add -- '.$file;
        }
        file_put_contents(storage_path('app/one-api-phase-4-stage-new-files.ps1'), implode(PHP_EOL, $stage).PHP_EOL);

        $md = "# Phase 4 interactive staging\n\n";
        $md .= "## New dedicated files\n\n";
        foreach ($new as $file) {
            $md .= "- `git add -- {$file}`\n";
        }
        $md .= "\n## Shared / mixed (git add -p)\n\n";
        foreach ($shared as $file) {
            $md .= "- `{$file}`\n";
        }
        $md .= "- `bootstrap/app.php` — One API exception mapping only\n\n";
        $md .= "## Exclude from One API commit\n\n";
        $md .= "- Sabre, CMS, UI_test, unrelated `SupplierConnectionCrudTest` promotion\n";
        $md .= "- `storage/app/one-api-*` generated manifests unless ops policy requires\n";
        file_put_contents(storage_path('app/one-api-phase-4-interactive-stage.md'), $md);

        file_put_contents(storage_path('app/one-api-git-status-phase4.txt'), $status.PHP_EOL);
    }
}
