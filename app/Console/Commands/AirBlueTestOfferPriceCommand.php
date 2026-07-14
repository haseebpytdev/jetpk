<?php

namespace App\Console\Commands;

use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueOfferPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AirBlueTestOfferPriceCommand extends Command
{
    protected $signature = 'airblue:test-offer-price
        {--connection= : Supplier connection ID}
        {--fixture= : Path to search fixture JSON with first_offer}';

    protected $description = 'Revalidate a AirBlue offer from fixture (DoOfferPrice is optional/no-op)';

    public function handle(AirBlueOfferPriceService $offerPriceService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No AirBlue SupplierConnection found.');

            return self::FAILURE;
        }

        $fixturePath = (string) ($this->option('fixture') ?: base_path('tests/Fixtures/airblue/search_isb_dxb.json'));
        if (! is_file($fixturePath)) {
            $this->error('Fixture not found: '.$fixturePath);

            return self::FAILURE;
        }

        $fixture = json_decode((string) file_get_contents($fixturePath), true);
        $offerData = is_array($fixture['first_offer'] ?? null) ? $fixture['first_offer'] : null;
        if ($offerData === null) {
            $this->error('Fixture missing first_offer — run airblue:test-search first.');

            return self::FAILURE;
        }

        $offer = NormalizedFlightOfferData::fromArray($offerData);
        $result = $offerPriceService->revalidate($offer, $connection);

        $this->line('is_valid='.($result->is_valid ? 'true' : 'false'));
        $this->line('status='.$result->status);
        $this->line('offer_price_supported='.(($result->meta['offer_price_supported'] ?? false) ? 'true' : 'false'));

        File::ensureDirectoryExists(base_path('tests/Fixtures/airblue'));
        file_put_contents(
            base_path('tests/Fixtures/airblue/offer_price_last.json'),
            json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        return $result->is_valid ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::Airblue)->first();
        }

        return SupplierConnection::query()->where('provider', SupplierProvider::Airblue)->orderByDesc('is_active')->first();
    }
}
