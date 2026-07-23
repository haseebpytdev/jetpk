<?php

namespace App\Services\Suppliers\OneApi\Bundles;

use DOMDocument;

class OneApiBundleParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function parseFromPriceXml(string $xml): array
    {
        $document = new DOMDocument;
        if (@$document->loadXML($xml) === false) {
            return [];
        }

        $bundles = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            if ($node->localName !== 'bundledService') {
                continue;
            }
            $ondSequence = 1;
            $parent = $node->parentNode;
            if ($parent instanceof \DOMElement && $parent->localName === 'AABundledServiceExt') {
                $ondSequence = (int) ($parent->getAttribute('applicableOndSequence') ?: '1');
            }
            $bundles[] = [
                'bunldedServiceId' => $this->childText($node, 'bunldedServiceId'),
                'bundledServiceName' => $this->childText($node, 'bundledServiceName'),
                'includedServies' => $this->childText($node, 'includedServies'),
                'applicableOndSequence' => $ondSequence,
                'included_price' => false,
            ];
        }

        return $bundles;
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
