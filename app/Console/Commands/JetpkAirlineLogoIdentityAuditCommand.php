<?php

namespace App\Console\Commands;

use App\Services\TravelData\AirlineCanonicalResolver;
use App\Services\TravelData\AirlineLogoIdentityService;
use Illuminate\Console\Command;

class JetpkAirlineLogoIdentityAuditCommand extends Command
{
    protected $signature = 'jetpk:airline-logo-identity-audit {--root= : Staging public root (pre-promotion proof)}';

    protected $description = 'Prove canonical airline logo identity for required JetPK carrier codes.';

    public function handle(AirlineLogoIdentityService $service, AirlineCanonicalResolver $canonical): int
    {
        $root = (string) ($this->option('root') ?: '');
        $result = $service->audit($canonical->requiredJetpkCodes(), $root !== '' ? $root : null);
        foreach ($result['carriers'] ?? [] as $carrier) {
            $this->line(json_encode($carrier, JSON_UNESCAPED_SLASHES));
        }
        $this->line('fail_count='.($result['fail_count'] ?? 0));

        return ($result['pass'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
