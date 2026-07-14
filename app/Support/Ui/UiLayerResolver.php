<?php

namespace App\Support\Ui;

use App\Services\Ui\UiLayerSettingsService;
use Illuminate\Http\Request;

/**
 * Resolves active UI override layers for the current request / layout context.
 */
class UiLayerResolver
{
    public function __construct(
        private readonly UiLayerSettingsService $settings,
    ) {}

    /**
     * @return list<string>
     */
    public function contextsForRequest(?Request $request = null): array
    {
        $request ??= request();
        $contexts = [];

        if ($request->routeIs('flights.results*', 'mobile.flights.*', 'mobile.flights.results*')) {
            $contexts[] = 'flight-results';
        }

        if ($request->is('admin*') || $request->routeIs('admin.*')) {
            $contexts[] = 'admin';
        } elseif ($request->is('staff*') || $request->routeIs('staff.*')) {
            $contexts[] = 'staff';
        } elseif ($request->is('agent*') || $request->routeIs('agent.*')) {
            $contexts[] = 'agent';
        } elseif ($request->is('customer*') || $request->routeIs('customer.*')) {
            $contexts[] = 'customer';
        } else {
            $contexts[] = 'public';
        }

        return array_values(array_unique($contexts));
    }

    /**
     * @param  list<string>  $contexts
     * @return list<UiLayer>
     */
    public function activeLayers(array $contexts, ?string $supplier = null): array
    {
        if (! UiLayerRegistry::isGloballyEnabled()) {
            return [];
        }

        $supplierKey = $supplier !== null ? strtolower(trim($supplier)) : null;
        $active = [];

        foreach (UiLayerRegistry::all() as $layer) {
            if (! $this->settings->isEnabled($layer->key)) {
                continue;
            }

            if ($layer->contexts === [] || array_intersect($contexts, $layer->contexts) === []) {
                continue;
            }

            if ($layer->suppliers !== []) {
                if ($supplierKey === null || $supplierKey === '' || ! in_array($supplierKey, $layer->suppliers, true)) {
                    continue;
                }
            }

            $active[] = $layer;
        }

        usort($active, static function (UiLayer $a, UiLayer $b): int {
            return $a->order <=> $b->order ?: strcmp($a->key, $b->key);
        });

        return $active;
    }

    /**
     * @param  list<string>  $contexts
     * @return list<string>
     */
    public function activeCssPaths(array $contexts, ?string $supplier = null): array
    {
        $paths = [];
        foreach ($this->activeLayers($contexts, $supplier) as $layer) {
            foreach ($layer->css as $path) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $contexts
     * @return list<string>
     */
    public function activeJsPaths(array $contexts, ?string $supplier = null): array
    {
        $paths = [];
        foreach ($this->activeLayers($contexts, $supplier) as $layer) {
            foreach ($layer->js as $path) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }
}
