<?php

namespace App\Services\Suppliers\OneApi\Ancillaries;

class OneApiMealResponseParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $xml): array
    {
        $document = new \DOMDocument;
        if (@$document->loadXML($xml) === false) {
            return [];
        }

        $items = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            if ($node->localName !== 'Meal') {
                continue;
            }
            $items[] = [
                'code' => $node instanceof \DOMElement ? trim($node->getAttribute('Code')) : '',
                'passenger_ref' => $node instanceof \DOMElement ? trim($node->getAttribute('PassengerRef')) : 'A1',
                'segment_ref' => $node instanceof \DOMElement ? trim($node->getAttribute('SegmentRef')) : '1',
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
}
