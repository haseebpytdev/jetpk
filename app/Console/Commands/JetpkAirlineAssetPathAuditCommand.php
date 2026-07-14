<?php

namespace App\Console\Commands;

use App\Support\Audits\JetpkAirportParityAuditService;
use Illuminate\Console\Command;

class JetpkAirlineAssetPathAuditCommand extends Command
{
    protected $signature = 'jetpk:airline-asset-path-audit';

    protected $description = 'Read-only JetPK airline asset path and symlink audit.';

    public function handle(JetpkAirportParityAuditService $service): int
    {
        $result = $service->airlineAssetPathAudit();
        $summary = $result['summary'] ?? [];

        $this->line('JetPK airline asset path audit');
        $this->line('==============================');
        $this->line('storage_symlink_ok='.(($result['storage_symlink_ok'] ?? false) ? '1' : '0'));
        $this->line('checked='.($summary['checked'] ?? 0));
        $this->line('valid='.($summary['valid'] ?? 0));
        $this->line('missing='.($summary['missing'] ?? 0));
        $this->line('fallback='.($summary['fallback'] ?? 0));
        $this->line('invalid='.($summary['invalid'] ?? 0));

        foreach ($result['assets'] as $asset) {
            $status = strtoupper((string) ($asset['status'] ?? 'UNKNOWN'));
            $publicPath = (string) ($asset['public_path'] ?? '/unknown-asset');
            $size = (int) ($asset['size'] ?? 0);
            $mime = (string) ($asset['detected_mime'] ?? '');
            $errors = $asset['validation_errors'] ?? [];
            $line = $status.'  '.$publicPath.' size='.$size;
            if ($mime !== '') {
                $line .= ' mime='.$mime;
            }
            if ($errors !== []) {
                $line .= ' errors='.json_encode($errors);
            }
            $this->line($line);
        }

        $this->line('fail_count='.$result['fail_count']);

        return $result['pass'] ? self::SUCCESS : self::FAILURE;
    }
}
