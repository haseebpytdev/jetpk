<?php

namespace App\Services\Suppliers\PiaNdc;

use DOMDocument;
use DOMNode;

/**
 * Structural summary of cancel request XML without passenger/contact PII.
 */
class PiaNdcCancelRequestShapeSummarizer
{
    /** @var list<string> */
    private const PII_LOCAL_NAMES = [
        'GivenName',
        'Surname',
        'Birthdate',
        'EmailAddressText',
        'PhoneNumber',
        'IdentityDocID',
        'IdentityDocNumber',
        'Username',
        'Password',
    ];

    /** @var list<string> */
    private const CANCEL_ROOT_NAMES = [
        'IATA_OrderChangeRQ',
        'IATA_OrderReshopRQ',
        'IATA_OrderCancelRQ',
    ];

    /**
     * @return array{
     *     message_type: ?string,
     *     request_paths: list<string>,
     *     key_fields: array<string, string>
     * }
     */
    public function summarize(string $requestXml): array
    {
        $xml = trim($requestXml);
        if ($xml === '') {
            return [
                'message_type' => null,
                'request_paths' => [],
                'key_fields' => [],
            ];
        }

        $dom = new DOMDocument;
        if (@$dom->loadXML($xml) !== true) {
            return [
                'message_type' => null,
                'request_paths' => [],
                'key_fields' => ['parse_error' => 'invalid_xml'],
            ];
        }

        $messageType = null;
        foreach (self::CANCEL_ROOT_NAMES as $rootName) {
            $nodes = $dom->getElementsByTagName($rootName);
            if ($nodes->length > 0) {
                $messageType = $rootName;
                break;
            }
        }

        $paths = [];
        $keyFields = [];
        $this->walkNode($dom->documentElement, '', $paths, $keyFields);

        return [
            'message_type' => $messageType,
            'request_paths' => array_values(array_unique($paths)),
            'key_fields' => $keyFields,
        ];
    }

    /**
     * @param  list<string>  $paths
     * @param  array<string, string>  $keyFields
     */
    private function walkNode(?DOMNode $node, string $prefix, array &$paths, array &$keyFields): void
    {
        if ($node === null) {
            return;
        }

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMNode || $child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $localName = $child->localName ?: $child->nodeName;
            $path = $prefix === '' ? $localName : $prefix.'.'.$localName;

            if ($this->isCancelRelevant($localName)) {
                $paths[] = $path;
            }

            if (in_array($localName, ['OrderID', 'OrderRefID', 'OwnerCode'], true)) {
                $value = trim($child->textContent ?? '');
                if ($value !== '') {
                    $keyFields[$localName] = $value;
                }
            }

            if (! in_array($localName, self::PII_LOCAL_NAMES, true)) {
                $this->walkNode($child, $path, $paths, $keyFields);
            }
        }
    }

    private function isCancelRelevant(string $localName): bool
    {
        static $relevant = [
            'IATA_OrderChangeRQ',
            'IATA_OrderReshopRQ',
            'IATA_OrderCancelRQ',
            'Request',
            'ChangeOrder',
            'CancelOrder',
            'UpdateOrder',
            'Order',
            'OrderID',
            'OrderRefID',
            'OwnerCode',
            'OrderChangeParameters',
            'ReshopParameters',
            'PaymentFunctions',
        ];

        return in_array($localName, $relevant, true);
    }
}
