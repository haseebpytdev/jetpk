<?php

namespace App\Services\Fx\Providers;

use App\Contracts\FxRateProviderInterface;
use Illuminate\Support\Facades\Http;

class FrankfurterFxProvider implements FxRateProviderInterface
{
    public function fetchRate(string $from, string $to): array
    {
        $response = Http::timeout((int) config('services.fx.timeout_seconds', 5))
            ->get((string) config('services.fx.endpoint', 'https://api.frankfurter.app/latest'), [
                'from' => $from,
                'to' => $to,
            ]);
        if (! $response->successful()) {
            return ['status' => 'conversion_missing', 'from' => $from, 'to' => $to, 'rate' => null];
        }
        $rate = (float) data_get($response->json(), 'rates.'.$to, 0);

        return ['status' => $rate > 0 ? 'converted' : 'conversion_missing', 'from' => $from, 'to' => $to, 'rate' => $rate > 0 ? $rate : null];
    }
}
