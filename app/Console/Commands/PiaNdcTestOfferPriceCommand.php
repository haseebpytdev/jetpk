<?php

namespace App\Console\Commands;

use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\PiaNdcOfferPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PiaNdcTestOfferPriceCommand extends Command
{
    protected $signature = 'pia-ndc:test-offer-price
        {--connection= : Supplier connection ID}
        {--fixture= : Path to search fixture JSON with first_offer}';

    protected $description = 'Revalidate a PIA NDC offer from fixture (DoOfferPrice is optional/no-op)';

    public function handle(PiaNdcOfferPriceService $offerPriceService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No PIA NDC SupplierConnection found.');

            return self::FAILURE;
        }

        $fixturePath = (string) ($this->option('fixture') ?: base_path('tests/Fixtures/pia-ndc/search_isb_dxb.json'));
        if (! is_file($fixturePath)) {
            $this->error('Fixture not found: '.$fixturePath);

            return self::FAILURE;
        }

        $fixture = json_decode((string) file_get_contents($fixturePath), true);
        $offerData = is_array($fixture['first_offer'] ?? null) ? $fixture['first_offer'] : null;
        if ($offerData === null) {
            $this->error('Fixture missing first_offer — run pia-ndc:test-search first.');

            return self::FAILURE;
        }

        $offer = NormalizedFlightOfferData::fromArray($offerData);
        $result = $offerPriceService->revalidate($offer, $connection);

        $this->line('is_valid='.($result->is_valid ? 'true' : 'false'));
        $this->line('status='.$result->status);
        $this->line('offer_price_supported='.(($result->meta['offer_price_supported'] ?? false) ? 'true' : 'false'));

        File::ensureDirectoryExists(base_path('tests/Fixtures/pia-ndc'));
        file_put_contents(
            base_path('tests/Fixtures/pia-ndc/offer_price_last.json'),
            json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        return $result->is_valid ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::PiaNdc)->first();
        }

        return SupplierConnection::query()->where('provider', SupplierProvider::PiaNdc)->orderByDesc('is_active')->first();
    }
}
