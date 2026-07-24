<?php

namespace App\Services\Suppliers\OneApi\Transport;

use DOMDocument;
use DOMXPath;

class OneApiXmlParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $xml): array
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;
        if (@$document->loadXML($xml) === false) {
            throw new \RuntimeException('malformed_response');
        }

        $xpath = new DOMXPath($document);
        foreach ($document->documentElement?->attributes ?? [] as $attr) {
            $xpath->registerNamespace($attr->nodeName, $attr->nodeValue);
        }

        $fault = $this->firstLocalName($document, 'Fault');
        if ($fault !== null) {
            return ['soap_fault' => $this->nodeText($fault), 'raw_xml' => $xml];
        }

        $transactionId = $this->firstAttributeByLocalName($document, 'TransactionIdentifier');
        if ($transactionId === null || $transactionId === '') {
            foreach ($document->getElementsByTagName('*') as $node) {
                if ($node->hasAttribute('TransactionIdentifier')) {
                    $transactionId = $node->getAttribute('TransactionIdentifier');
                    break;
                }
            }
        }
        $errors = $this->collectLocalElements($document, 'Error');

        return [
            'transaction_identifier' => $transactionId,
            'errors' => $errors,
            'raw_xml' => $xml,
            'document' => $document,
        ];
    }

    private function firstLocalName(DOMDocument $document, string $localName): ?\DOMElement
    {
        foreach ($document->getElementsByTagName('*') as $node) {
            if ($node->localName === $localName) {
                return $node instanceof \DOMElement ? $node : null;
            }
        }

        return null;
    }

    private function firstAttributeByLocalName(DOMDocument $document, string $localName): ?string
    {
        foreach ($document->getElementsByTagName('*') as $node) {
            if ($node->localName === $localName && $node->hasAttribute('TransactionIdentifier')) {
                return $node->getAttribute('TransactionIdentifier');
            }
            if ($node->localName === $localName) {
                foreach ($node->attributes ?? [] as $attr) {
                    if ($attr->localName === 'TransactionIdentifier' || $attr->nodeName === 'TransactionIdentifier') {
                        return $attr->nodeValue;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return list<array<string, string>>
     */
    private function collectLocalElements(DOMDocument $document, string $localName): array
    {
        $out = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            if ($node->localName !== $localName) {
                continue;
            }
            $out[] = ['text' => trim($node->textContent ?? '')];
        }

        return $out;
    }

    private function nodeText(\DOMElement $element): string
    {
        return trim($element->textContent ?? '');
    }
}
