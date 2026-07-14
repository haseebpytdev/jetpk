<?php

namespace App\Services\Fx\Providers;

use App\Contracts\FxRateProviderInterface;
use Illuminate\Support\Facades\Http;

class OpenErApiFxProvider implements FxRateProviderInterface
{
    public function fetchRate(string $from, string $to): array
    {
        $endpoint = rtrim((string) config('services.fx.secondary_endpoint', 'https://open.er-api.com/v6/latest'), '/');
        $response = Http::timeout((int) config('services.fx.timeout_seconds', 5))
            ->get($endpoint.'/'.$from);
        if (! $response->successful()) {
            return ['status' => 'conversion_missing', 'from' => $from, 'to' => $to, 'rate' => null];
        }
        $rate = (float) data_get($response->json(), 'rates.'.$to, 0);

        return ['status' => $rate > 0 ? 'converted' : 'conversion_missing', 'from' => $from, 'to' => $to, 'rate' => $rate > 0 ? $rate : null];
    }
}
