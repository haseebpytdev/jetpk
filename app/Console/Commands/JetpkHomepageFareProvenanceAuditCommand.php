<?php

namespace App\Console\Commands;

use App\Models\ClientProfile;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Homepage\JetpkHomepageFareProvenanceAuditor;
use Illuminate\Console\Command;

/**
 * Audits JetPakistan homepage trending/destination fare provenance (read-only search).
 */
class JetpkHomepageFareProvenanceAuditCommand extends Command
{
    protected $signature = 'jetpk:homepage-fare-provenance-audit
                            {--profile= : Client profile slug}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Audit homepage trending and destination fare provenance against FlightSearchService.';

    public function handle(
        JetpkHomepageFareProvenanceAuditor $auditor,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        $profile = $this->resolveProfile($profileResolver, $clientContext);
        if ($profile === null) {
            $this->error('Client profile not found.');

            return self::FAILURE;
        }

        $report = $auditor->audit($profile);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($report['trending'] as $row) {
            $this->line(sprintf(
                'trending %s %s→%s min=%s displayed=%s match=%s',
                $row['route'] ?? '-',
                $row['origin'] ?? '-',
                $row['destination'] ?? '-',
                isset($row['min_eligible_customer_price']) ? (string) $row['min_eligible_customer_price'] : '-',
                isset($row['displayed_price']) ? (string) $row['displayed_price'] : $row['displayed_label'] ?? '-',
                ($row['price_match'] ?? false) ? 'yes' : 'no',
            ));
        }

        foreach ($report['destinations'] as $row) {
            $this->line(sprintf(
                'destination %s origin=%s min=%s displayed=%s match=%s',
                $row['destination'] ?? '-',
                $row['winning_origin'] ?? '-',
                isset($row['winning_price']) ? (string) $row['winning_price'] : '-',
                isset($row['displayed_price']) ? (string) $row['displayed_price'] : $row['displayed_label'] ?? '-',
                ($row['price_match'] ?? false) ? 'yes' : 'no',
            ));
        }

        return self::SUCCESS;
    }

    private function resolveProfile(ClientProfileResolver $resolver, CurrentClientContext $context): ?ClientProfile
    {
        $slug = trim((string) $this->option('profile'));
        if ($slug !== '') {
            return ClientProfile::query()->where('slug', $slug)->where('is_active', true)->first();
        }

        return $context->get() ?? $resolver->resolveDefault();
    }
}
