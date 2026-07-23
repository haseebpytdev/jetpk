<?php

namespace App\Services\Suppliers\OneApi\Pricing;

use App\Models\SupplierConnection;
use App\Services\Suppliers\OneApi\Money\OneApiMoney;
use App\Services\Suppliers\OneApi\Transport\OneApiSoapTransport;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContext;
use DOMDocument;

class OneApiAirPriceResponseParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(array $soapParsed): array
    {
        $xml = (string) ($soapParsed['raw_xml'] ?? '');
        if ($xml === '') {
            return [];
        }

        $document = new DOMDocument;
        if (@$document->loadXML($xml) === false) {
            return [];
        }

        $transactionId = (string) ($soapParsed['transaction_identifier'] ?? '');
        $totalFare = $this->moneyFromLocal($document, 'TotalFare');
        $equiBase = $this->moneyFromLocal($document, 'EquiBaseFare');
        $baseFare = $this->moneyFromLocal($document, 'BaseFare');

        $bundles = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            if ($node->localName === 'bundledService') {
                $bundles[] = [
                    'bunldedServiceId' => $this->childText($node, 'bunldedServiceId'),
                    'bundledServiceName' => $this->childText($node, 'bundledServiceName'),
                    'includedServies' => $this->childText($node, 'includedServies'),
                ];
            }
        }

        return [
            'transaction_identifier' => $transactionId,
            'base_fare' => $baseFare,
            'equi_base_fare' => $equiBase,
            'total_fare' => $totalFare,
            'bundles' => $bundles,
        ];
    }

    /**
     * @return array{amount: string, currency: string, decimal_places: int}|null
     */
    private function moneyFromLocal(DOMDocument $document, string $localName): ?array
    {
        foreach ($document->getElementsByTagName('*') as $node) {
            if ($node->localName !== $localName) {
                continue;
            }
            $amount = '';
            $currency = '';
            $decimals = 2;
            foreach ($node->attributes ?? [] as $attr) {
                if ($attr->localName === 'Amount') {
                    $amount = (string) $attr->nodeValue;
                }
                if ($attr->localName === 'CurrencyCode') {
                    $currency = (string) $attr->nodeValue;
                }
                if ($attr->localName === 'DecimalPlaces') {
                    $decimals = (int) $attr->nodeValue;
                }
            }

            return [
                'amount' => OneApiMoney::normalizeAmount($amount, $decimals),
                'currency' => strtoupper($currency),
                'decimal_places' => $decimals,
            ];
        }

        return null;
    }

    private function childText(\DOMNode $node, string $localName): string
    {
        foreach ($node->childNodes as $child) {
            if ($child->localName === $localName) {
                return trim($child->textContent ?? '');
            }
        }

        return '';
    }
}
