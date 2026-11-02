<?php

namespace App\Services\Suppliers\EjmiralAerost;

use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\EjmiralAerost\Exceptions\EjmiralAerostValidationException;

/**
 * Creates exactly one AEROST UAT booking when gates pass. No blind retry.
 */
class EjmiralAerostBookingService
{
    public function __construct(
        private readonly EjmiralAerostConfigResolver $configResolver,
        private readonly EjmiralAerostClient $client,
        private readonly EjmiralAerostOfferTokenSigner $offerTokenSigner,
        private readonly EjmiralAerostResponseNormalizer $normalizer,
    ) {}

    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData
    {
        unset($actor);
        $config = $this->configResolver->resolve($connection);
        if (! $config['live_booking_enabled'] && ! app()->runningUnitTests()) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::EjmiralAerost->value,
                error_code: 'live_booking_disabled',
                error_message: 'EJMIRAL AEROST live booking is disabled.',
            );
        }

        $payload = $this->buildBookingPayload($booking);
        $response = $this->client->post($connection, 'res/booking', $payload, ['operation' => 'booking']);
        $body = $response['body'];

        $pnr = trim((string) ($body['PNR'] ?? $body['pnr'] ?? $body['pnrn'] ?? ''));
        $ticketRef = trim((string) ($body['ticket_ref'] ?? $body['ticketRef'] ?? ''));
        $status = (string) ($body['booking_status'] ?? $body['status'] ?? ($pnr !== '' ? 'reserved' : 'failed'));

        if ($pnr === '' && $ticketRef === '') {
            return new SupplierBookingResultData(
                success: false,
                status: 'ambiguous',
                provider: SupplierProvider::EjmiralAerost->value,
                error_code: 'booking_ambiguous',
                error_message: 'AEROST booking response ambiguous; reconcile via PNR read only.',
                safe_summary: [
                    'booking_status' => $status,
                    'pnr_present' => false,
                    'ticket_ref_present' => false,
                ],
            );
        }

        return new SupplierBookingResultData(
            success: true,
            status: 'success',
            provider: SupplierProvider::EjmiralAerost->value,
            supplier_reference: $pnr !== '' ? $pnr : $ticketRef,
            pnr: $pnr !== '' ? $pnr : null,
            safe_summary: [
                'booking_status' => $status,
                'pnr_present' => $pnr !== '',
                'ticket_ref_present' => $ticketRef !== '',
                'time_limit' => (string) ($body['time_limit'] ?? ''),
                'pax_count' => (int) ($body['pax_count'] ?? count($payload['passengers'] ?? [])),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildBookingPayload(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $token = (string) data_get($meta, 'ejmiral_aerost.offer_token')
            ?: (string) data_get($meta, 'provider_context.ejmiral_aerost.offer_token')
            ?: (string) data_get($meta, 'offer_token', '');

        if ($token === '') {
            throw new EjmiralAerostValidationException('validation_error', 422, 'AEROST offer token required for booking.');
        }

        $signed = $this->offerTokenSigner->verify($token);
        $opaque = $this->normalizer->opaqueFromSignedPayload($signed);

        $passengers = $this->mapPassengers($booking);
        $contact = [
            'email' => (string) ($booking->contact_email ?? data_get($meta, 'contact.email') ?? 'uat.passenger@example.test'),
            'phone' => (string) ($booking->contact_phone ?? data_get($meta, 'contact.phone') ?? '+920000000000'),
        ];

        return [
            'NLD' => $opaque['nld'],
            'acceptance' => $opaque['acceptance'],
            'contact' => $contact,
            'passengers' => $passengers,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mapPassengers(Booking $booking): array
    {
        $rows = is_array($booking->passengers) ? $booking->passengers : [];
        if ($rows === [] && method_exists($booking, 'passengersRelation')) {
            $rows = [];
        }

        $mapped = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $idType = strtoupper((string) ($row['id_type'] ?? $row['document_type'] ?? 'PSPT'));
            if (! in_array($idType, ['PSPT', 'NATI'], true)) {
                // AEROST contract only — do not import AirBlue document assumptions.
                $idType = str_contains(strtolower((string) ($row['document_type'] ?? '')), 'national') ? 'NATI' : 'PSPT';
            }

            $mapped[] = [
                'first_name' => (string) ($row['first_name'] ?? $row['given_name'] ?? 'TEST'),
                'last_name' => (string) ($row['last_name'] ?? $row['surname'] ?? 'PASSENGER'),
                'dob' => (string) ($row['dob'] ?? $row['date_of_birth'] ?? '1990-01-01'),
                'gender' => strtoupper((string) ($row['gender'] ?? 'M')),
                'id_type' => $idType,
                'document' => (string) ($row['document'] ?? $row['document_number'] ?? $row['passport_number'] ?? 'UAT000000'),
            ];
        }

        if ($mapped === []) {
            $mapped[] = [
                'first_name' => 'TEST',
                'last_name' => 'PASSENGER',
                'dob' => '1990-01-01',
                'gender' => 'M',
                'id_type' => 'PSPT',
                'document' => 'UAT000000',
            ];
        }

        return $mapped;
    }
}
