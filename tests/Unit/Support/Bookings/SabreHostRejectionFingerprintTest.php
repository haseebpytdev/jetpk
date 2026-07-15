<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Bookings\SabreHostRejectionFingerprint;
use Tests\TestCase;

class SabreHostRejectionFingerprintTest extends TestCase
{
    public function test_should_persist_only_real_host_rejection_families(): void
    {
        $this->assertTrue(SabreHostRejectionFingerprint::shouldPersistFingerprint([
            'host_error_family' => SabreHostErrorClassifier::HOST_ERROR_FAMILY_HOST_SEGMENT_STATUS,
            'safe_reason_code' => SabreHostErrorClassifier::REASON_HOST_SEGMENT_STATUS_UNCONFIRMED,
        ]));
        $this->assertFalse(SabreHostRejectionFingerprint::shouldPersistFingerprint([
            'host_error_family' => SabreHostErrorClassifier::HOST_ERROR_FAMILY_UNKNOWN,
            'safe_reason_code' => SabreHostErrorClassifier::REASON_UNKNOWN,
        ]));
    }

    public function test_fingerprint_hash_is_stable_for_same_offer_pattern(): void
    {
        $offer = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'validating_carrier' => 'EK',
            'segments' => [[
                'origin' => 'LHE',
                'destination' => 'DXB',
                'departure_at' => '2026-10-01T10:00:00',
                'carrier' => 'EK',
                'booking_class' => 'T',
                'fare_basis_code' => 'TAAOPPK1',
            ]],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'booking_classes_by_segment' => ['T'],
                    'fare_basis_codes_by_segment' => ['TAAOPPK1'],
                ],
            ],
        ];

        $a = SabreHostRejectionFingerprint::extractMatchFieldsFromOffer($offer);
        $b = SabreHostRejectionFingerprint::extractMatchFieldsFromOffer($offer);

        $this->assertSame(
            SabreHostRejectionFingerprint::computeFingerprintHash($a),
            SabreHostRejectionFingerprint::computeFingerprintHash($b)
        );
    }
}
