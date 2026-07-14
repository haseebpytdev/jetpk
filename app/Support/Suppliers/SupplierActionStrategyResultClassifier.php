<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyResultClassifier;

/**
 * Provider-scoped result classification for supplier mutations (delegates to supplier classifiers).
 */
final class SupplierActionStrategyResultClassifier
{
    public function __construct(
        protected SabreGdsPnrCreateStrategyResultClassifier $sabreGdsClassifier,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function classify(string $provider, string $action, array $result): array
    {
        if ($provider === SupplierProvider::Sabre->value && $action === SupplierActionCode::CREATE_PNR) {
            return $this->sabreGdsClassifier->classify($result);
        }

        return [
            'safe_reason_code' => 'supplier_result_unclassified',
            'host_error_family' => null,
            'retry_policy' => 'admin_confirmed_fallback_only',
        ];
    }
}
