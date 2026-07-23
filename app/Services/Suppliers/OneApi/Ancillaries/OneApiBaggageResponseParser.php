<?php

namespace App\Services\Suppliers\OneApi\Ancillaries;

use DOMDocument;

class OneApiBaggageResponseParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        $document = new DOMDocument;
        if (@$document->loadXML($xml) === false) {
            return [];
        }

        $items = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            if ($node->localName !== 'Baggage') {
                continue;
            }
            $items[] = [
                'code' => $this->attr($node, 'Code'),
                'passenger_ref' => $this->attr($node, 'PassengerRef') ?: 'A1',
                'segment_ref' => $this->attr($node, 'SegmentRef') ?: '1',
                'ond_sequence' => (int) ($this->attr($node, 'OndSequence') ?: '1'),
                'description' => $this->childText($node, 'Description'),
                'amount' => $this->childText($node, 'Amount'),
                'currency' => $this->childText($node, 'CurrencyCode'),
            ];
        }

        return $items;
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

    private function attr(\DOMNode $node, string $name): string
    {
        if (! $node instanceof \DOMElement) {
            return '';
        }

        return trim($node->getAttribute($name));
    }
}
