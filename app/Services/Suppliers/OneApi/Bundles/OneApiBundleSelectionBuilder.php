<?php

namespace App\Services\Suppliers\OneApi\Bundles;

/**
 * Build bundle selection blocks using vendor misspelled wire element names.
 */
final class OneApiBundleSelectionBuilder
{
    /**
     * @param  list<array{bunldedServiceId?: string, bundledServiceName?: string}>  $selectedBundles
     * @return list<array<string, string>>
     */
    public function buildSelections(array $selectedBundles): array
    {
        $rows = [];
        foreach ($selectedBundles as $bundle) {
            $id = trim((string) ($bundle['bunldedServiceId'] ?? $bundle['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $rows[] = [
                'bunldedServiceId' => $id,
                'bundledServiceName' => trim((string) ($bundle['bundledServiceName'] ?? $bundle['name'] ?? '')),
                'includedServies' => trim((string) ($bundle['includedServies'] ?? '')),
            ];
        }

        return $rows;
    }
}
