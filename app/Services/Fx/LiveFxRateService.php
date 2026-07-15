<?php

namespace App\Services\Fx;

use App\Contracts\FxRateProviderInterface;
use App\Services\Fx\Providers\FrankfurterFxProvider;
use App\Services\Fx\Providers\OpenErApiFxProvider;
use Illuminate\Support\Facades\Cache;

class LiveFxRateService
{
    /**
     * @return array{status:string, from:string, to:string, rate:float|null, fetched_at:string|null}
     */
    public function getRate(string $from, string $to): array
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        if ($from === '' || $to === '') {
            return [
                'status' => 'conversion_missing',
                'from' => $from,
                'to' => $to,
                'rate' => null,
                'fetched_at' => null,
            ];
        }
        if ($from === $to) {
            return [
                'status' => 'same_currency',
                'from' => $from,
                'to' => $to,
                'rate' => 1.0,
                'fetched_at' => now()->toIso8601String(),
            ];
        }

        $cacheTtl = (int) config('services.fx.cache_ttl_seconds', 900);
        $cacheKey = 'fx_rate:'.$from.':'.$to;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($from, $to): array {
            $providers = $this->providers();
            foreach ($providers as $provider) {
                try {
                    $result = $provider->fetchRate($from, $to);
                    if (($result['status'] ?? '') === 'converted' && (float) ($result['rate'] ?? 0) > 0) {
                        return [
                            'status' => 'converted',
                            'from' => $from,
                            'to' => $to,
                            'rate' => (float) $result['rate'],
                            'fetched_at' => now()->toIso8601String(),
                        ];
                    }
                } catch (\Throwable) {
                }
            }

            return [
                'status' => 'conversion_missing',
                'from' => $from,
                'to' => $to,
                'rate' => null,
                'fetched_at' => null,
            ];
        });
    }

    /**
     * @return list<FxRateProviderInterface>
     */
    protected function providers(): array
    {
        $configured = strtolower((string) config('services.fx.provider', 'frankfurter'));
        $list = $configured === 'open_er_api'
            ? [new OpenErApiFxProvider, new FrankfurterFxProvider]
            : [new FrankfurterFxProvider, new OpenErApiFxProvider];

        return $list;
    }
}
