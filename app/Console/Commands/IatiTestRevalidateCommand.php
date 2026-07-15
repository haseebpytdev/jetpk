<?php

namespace App\Console\Commands;

use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiFareRevalidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IatiTestRevalidateCommand extends Command
{
    protected $signature = 'iati:test-revalidate
        {--connection= : Supplier connection ID}
        {--fixture= : Path to search fixture JSON with first_offer}';

    protected $description = 'Revalidate an IATI offer from fixture or last search fixture';

    public function handle(IatiFareRevalidationService $revalidationService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No IATI SupplierConnection found.');

            return self::FAILURE;
        }

        $fixturePath = (string) ($this->option('fixture') ?: base_path('tests/Fixtures/iati/search_lhe_dxb.json'));
        if (! is_file($fixturePath)) {
            $this->error('Fixture not found: '.$fixturePath);

            return self::FAILURE;
        }

        $fixture = json_decode((string) file_get_contents($fixturePath), true);
        $offerData = is_array($fixture['first_offer'] ?? null) ? $fixture['first_offer'] : null;
        if ($offerData === null) {
            $this->error('Fixture missing first_offer — run iati:test-search first.');

            return self::FAILURE;
        }

        $offer = NormalizedFlightOfferData::fromArray($offerData);
        $result = $revalidationService->revalidate($offer, $connection);

        $this->line('is_valid='.($result->is_valid ? 'true' : 'false'));
        $this->line('status='.$result->status);
        $this->line('price_changed='.($result->price_changed ? 'true' : 'false'));
        $this->line('old_total='.(string) $result->old_total);
        $this->line('new_total='.(string) $result->new_total);

        File::ensureDirectoryExists(base_path('tests/Fixtures/iati'));
        file_put_contents(
            base_path('tests/Fixtures/iati/revalidate_last.json'),
            json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        return $result->is_valid ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::Iati)->first();
        }

        return SupplierConnection::query()->where('provider', SupplierProvider::Iati)->orderByDesc('is_active')->first();
    }
}
