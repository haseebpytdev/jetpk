<?php

namespace App\Services\Suppliers\OneApi\Ancillaries;

class OneApiSeatMapResponseParser
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

        $seats = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            if ($node->localName !== 'Seat') {
                continue;
            }
            if (! $node instanceof \DOMElement) {
                continue;
            }
            $seats[] = [
                'number' => trim($node->getAttribute('SeatNumber')),
                'status' => trim($node->getAttribute('SeatStatus')),
                'amount' => trim($node->getAttribute('Amount')),
                'currency' => trim($node->getAttribute('CurrencyCode')),
                'passenger_ref' => trim($node->getAttribute('PassengerRef')) ?: 'A1',
                'segment_ref' => trim($node->getAttribute('SegmentRef')) ?: '1',
            ];
        }

        return $seats;
    }
}
