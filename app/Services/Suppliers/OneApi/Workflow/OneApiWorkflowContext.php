<?php

namespace App\Services\Suppliers\OneApi\Workflow;

/**
 * Session state for REST search through SOAP book (TID, RPH, cookies, selections).
 */
class OneApiWorkflowContext
{
    /**
     * @param  list<array<string, mixed>>  $originDestinationGroups
     * @param  list<string>  $cookieJar
     * @param  array<string, mixed>  $moneySnapshot
     * @param  array<string, mixed>  $selectedBundles
     * @param  list<array<string, mixed>>  $selectedAncillaries
     */
    public function __construct(
        public string $contextId,
        public int $connectionId,
        public string $correlationId,
        public array $signedOfferPayload,
        public array $originDestinationGroups = [],
        public ?string $transactionIdentifier = null,
        public array $segmentRphByKey = [],
        public array $terminalsBySegmentKey = [],
        public array $cookieJar = [],
        public array $moneySnapshot = [],
        public array $selectedBundles = [],
        public array $selectedAncillaries = [],
        public bool $reconciliationRequired = false,
        public ?string $supplierPnr = null,
        public ?int $ownerUserId = null,
        public ?int $agencyId = null,
        public ?int $bookingId = null,
        public ?string $sessionFingerprint = null,
        public string $signedOfferFingerprint = '',
        public string $passengerProfileFingerprint = '',
        public string $lifecycleStatus = 'active',
        public ?string $createdAtIso = null,
        public ?string $expiresAtIso = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'context_id' => $this->contextId,
            'connection_id' => $this->connectionId,
            'correlation_id' => $this->correlationId,
            'signed_offer_payload' => $this->signedOfferPayload,
            'origin_destination_groups' => $this->originDestinationGroups,
            'transaction_identifier' => $this->transactionIdentifier,
            'segment_rph_by_key' => $this->segmentRphByKey,
            'terminals_by_segment_key' => $this->terminalsBySegmentKey,
            'cookie_jar' => $this->cookieJar,
            'money_snapshot' => $this->moneySnapshot,
            'selected_bundles' => $this->selectedBundles,
            'selected_ancillaries' => $this->selectedAncillaries,
            'reconciliation_required' => $this->reconciliationRequired,
            'supplier_pnr' => $this->supplierPnr,
            'owner_user_id' => $this->ownerUserId,
            'agency_id' => $this->agencyId,
            'booking_id' => $this->bookingId,
            'session_fingerprint' => $this->sessionFingerprint,
            'signed_offer_fingerprint' => $this->signedOfferFingerprint,
            'passenger_profile_fingerprint' => $this->passengerProfileFingerprint,
            'lifecycle_status' => $this->lifecycleStatus,
            'created_at_iso' => $this->createdAtIso,
            'expires_at_iso' => $this->expiresAtIso,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            contextId: (string) ($data['context_id'] ?? ''),
            connectionId: (int) ($data['connection_id'] ?? 0),
            correlationId: (string) ($data['correlation_id'] ?? ''),
            signedOfferPayload: is_array($data['signed_offer_payload'] ?? null) ? $data['signed_offer_payload'] : [],
            originDestinationGroups: is_array($data['origin_destination_groups'] ?? null) ? $data['origin_destination_groups'] : [],
            transactionIdentifier: isset($data['transaction_identifier']) ? (string) $data['transaction_identifier'] : null,
            segmentRphByKey: is_array($data['segment_rph_by_key'] ?? null) ? $data['segment_rph_by_key'] : [],
            terminalsBySegmentKey: is_array($data['terminals_by_segment_key'] ?? null) ? $data['terminals_by_segment_key'] : [],
            cookieJar: is_array($data['cookie_jar'] ?? null) ? $data['cookie_jar'] : [],
            moneySnapshot: is_array($data['money_snapshot'] ?? null) ? $data['money_snapshot'] : [],
            selectedBundles: is_array($data['selected_bundles'] ?? null) ? $data['selected_bundles'] : [],
            selectedAncillaries: is_array($data['selected_ancillaries'] ?? null) ? $data['selected_ancillaries'] : [],
            reconciliationRequired: (bool) ($data['reconciliation_required'] ?? false),
            supplierPnr: isset($data['supplier_pnr']) ? (string) $data['supplier_pnr'] : null,
            ownerUserId: isset($data['owner_user_id']) ? (int) $data['owner_user_id'] : null,
            agencyId: isset($data['agency_id']) ? (int) $data['agency_id'] : null,
            bookingId: isset($data['booking_id']) ? (int) $data['booking_id'] : null,
            sessionFingerprint: isset($data['session_fingerprint']) ? (string) $data['session_fingerprint'] : null,
            signedOfferFingerprint: (string) ($data['signed_offer_fingerprint'] ?? ''),
            passengerProfileFingerprint: (string) ($data['passenger_profile_fingerprint'] ?? ''),
            lifecycleStatus: (string) ($data['lifecycle_status'] ?? 'active'),
            createdAtIso: isset($data['created_at_iso']) ? (string) $data['created_at_iso'] : null,
            expiresAtIso: isset($data['expires_at_iso']) ? (string) $data['expires_at_iso'] : null,
        );
    }
}
