<?php

namespace App\Services\Suppliers\OneApi\Support;

/**
 * Interprets One API book/modify/read SOAP payloads for persistence and communication policy.
 */
class OneApiBookResponseInterpreter
{
    public function __construct(
        public readonly string $pnr,
        public readonly string $transactionIdentifier,
        public readonly string $ticketingStatus,
        public readonly ?string $ticketTimeLimit,
        public readonly bool $isOnHold,
        public readonly bool $isTicketed,
        public readonly bool $isAmbiguous,
    ) {}

    /**
     * @param  array<string, mixed>  $parsed
     */
    public static function fromParsed(array $parsed): self
    {
        $xml = (string) ($parsed['raw_xml'] ?? '');
        $pnr = self::matchAttribute($xml, 'BookingReferenceID', 'ID') ?? '';
        $tid = (string) ($parsed['transaction_identifier'] ?? '');
        if ($tid === '') {
            $tid = self::matchAttribute($xml, 'OTA_AirBookRS', 'TransactionIdentifier')
                ?? self::matchAttribute($xml, 'OTA_AirBookModifyRS', 'TransactionIdentifier')
                ?? self::matchAttribute($xml, 'OTA_ReadRS', 'TransactionIdentifier')
                ?? '';
        }

        $ticketingStatus = self::elementText($xml, 'TicketingStatus') ?? '';
        $ticketAdvisory = strtolower((string) (self::elementText($xml, 'TicketAdvisory') ?? ''));
        $ticketTimeLimit = self::elementText($xml, 'TicketTimeLimit');

        $isOnHold = str_contains(strtolower($ticketingStatus), 'hold')
            || str_contains($ticketAdvisory, 'onhold')
            || str_contains($ticketAdvisory, 'on hold');
        $isTicketed = str_contains(strtolower($ticketingStatus), 'ticket');

        $errors = $parsed['errors'] ?? [];
        $isAmbiguous = is_array($errors) && $errors !== [] && $pnr === '';

        return new self(
            pnr: $pnr,
            transactionIdentifier: $tid,
            ticketingStatus: $ticketingStatus,
            ticketTimeLimit: $ticketTimeLimit,
            isOnHold: $isOnHold && ! $isTicketed,
            isTicketed: $isTicketed,
            isAmbiguous: $isAmbiguous,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeSummary(): array
    {
        return [
            'pnr' => $this->pnr !== '' ? '***'.substr($this->pnr, -3) : null,
            'transaction_identifier' => $this->transactionIdentifier !== '' ? '***'.substr($this->transactionIdentifier, -4) : null,
            'supplier_booking_status' => $this->isOnHold ? 'on_hold' : ($this->isTicketed ? 'ticketed' : 'pending_ticketing'),
            'suppress_supplier_booking_created' => $this->isOnHold,
            'hold_deadline' => $this->ticketTimeLimit,
            'ticketing_status' => $this->ticketingStatus,
        ];
    }

    private static function matchAttribute(string $xml, string $elementLocal, string $attribute): ?string
    {
        if ($xml === '') {
            return null;
        }
        $pattern = '/<'.$elementLocal.'[^>]*\s'.$attribute.'="([^"]+)"/';
        if (preg_match($pattern, $xml, $m)) {
            return $m[1];
        }
        if (preg_match('/<'.$elementLocal.'[^>]*'.$attribute.'="([^"]+)"/', $xml, $m)) {
            return $m[1];
        }

        return null;
    }

    private static function elementText(string $xml, string $localName): ?string
    {
        if ($xml === '') {
            return null;
        }
        if (preg_match('/<'.$localName.'[^>]*>([^<]+)</', $xml, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
