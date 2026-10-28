<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueAmbiguousMutationClassifier;

class AirBlueAmbiguousMutationClassifierTest extends AirBlueOfflineTestCase
{
    public function test_ambiguous_codes_and_transmission_gate(): void
    {
        foreach (AirBlueAmbiguousMutationClassifier::AMBIGUOUS_CODES as $code) {
            $this->assertTrue(AirBlueAmbiguousMutationClassifier::isAmbiguousCode($code));
            $this->assertTrue(AirBlueAmbiguousMutationClassifier::isUncertainAfterTransmission(true, $code));
            $this->assertFalse(AirBlueAmbiguousMutationClassifier::isUncertainAfterTransmission(false, $code));
        }

        $this->assertFalse(AirBlueAmbiguousMutationClassifier::isAmbiguousCode('provider_error'));
        $this->assertFalse(AirBlueAmbiguousMutationClassifier::isUncertainAfterTransmission(true, 'provider_error'));
        $this->assertFalse(AirBlueAmbiguousMutationClassifier::isUncertainAfterTransmission(true, 'missing_order_context'));
    }
}
