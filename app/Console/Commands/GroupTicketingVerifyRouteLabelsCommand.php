<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\GroupInventory;
use App\Support\TravelData\AirportDisplayLabelResolver;
use Illuminate\Console\Command;

class GroupTicketingVerifyRouteLabelsCommand extends Command
{
    protected $signature = 'group-ticketing:verify-route-labels';

    protected $description = 'Audit group inventory sector IATA codes against resolved display labels (display-only, no supplier payloads)';

    public function handle(): int
    {
        $sectors = GroupInventory::query()
            ->where('is_active', true)
            ->whereNotNull('sector')
            ->where('sector', '!=', '')
            ->distinct()
            ->orderBy('sector')
            ->pluck('sector')
            ->filter(fn ($sector): bool => trim((string) $sector) !== '')
            ->values();

        if ($sectors->isEmpty()) {
            $this->warn('No active group inventory sectors found.');

            return self::SUCCESS;
        }

        $codes = [];
        foreach ($sectors as $sector) {
            [$origin, $dest] = $this->parseSector((string) $sector);
            if ($origin !== null) {
                $codes[] = $origin;
            }
            if ($dest !== null) {
                $codes[] = $dest;
            }
        }

        $codes = array_values(array_unique($codes));
        $airports = Airport::query()
            ->whereIn('iata_code', $codes)
            ->get()
            ->keyBy(fn (Airport $airport): string => strtoupper((string) $airport->iata_code));

        $rows = [];
        foreach ($sectors as $sector) {
            $sector = (string) $sector;
            [$origin, $dest] = $this->parseSector($sector);

            foreach ([$origin, $dest] as $code) {
                if ($code === null) {
                    continue;
                }

                $airport = $airports->get($code);
                $dbCity = $airport !== null ? trim((string) ($airport->city ?? '')) : '';
                $overrideCity = AirportDisplayLabelResolver::overrideCity($code) ?? '';
                $resolved = AirportDisplayLabelResolver::resolve($code, $airport);

                $rows[] = [
                    $sector,
                    $code,
                    $dbCity !== '' ? $dbCity : '—',
                    $overrideCity !== '' ? $overrideCity : '—',
                    $resolved['label'] !== '' ? $resolved['label'] : '—',
                ];
            }
        }

        $this->table(
            ['sector', 'code', 'db_city', 'override_city', 'resolved_label'],
            $rows
        );

        $this->newLine();
        $this->comment('Resolved labels are display-only; sector codes used for search/booking are unchanged.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseSector(string $sector): array
    {
        $sector = trim($sector);
        if ($sector === '') {
            return [null, null];
        }

        $parts = preg_split('/\s*[-–→]\s*/u', $sector);
        if (! is_array($parts) || count($parts) < 2) {
            return [null, null];
        }

        $origin = $this->normalizeIata($parts[0]);
        $dest = $this->normalizeIata($parts[1]);

        return [$origin, $dest];
    }

    private function normalizeIata(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }
}
