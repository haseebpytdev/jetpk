<?php

namespace Tests\Unit\Services\Suppliers\AirBlue;

use App\Services\Suppliers\AirBlue\AirBlueAmbiguousMutationClassifier;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueProviderException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueValidationException;

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

    public function test_request_may_have_been_sent_respects_client_marker(): void
    {
        $pre = new AirBlueValidationException(
            'missing_soap_action',
            422,
            'SOAPAction missing.',
            AirBlueAmbiguousMutationClassifier::withTransmissionState(
                [],
                AirBlueAmbiguousMutationClassifier::TRANSMISSION_PRE_SEND,
            ),
        );
        $this->assertFalse(AirBlueAmbiguousMutationClassifier::requestMayHaveBeenSent($pre));
        $this->assertFalse(AirBlueAmbiguousMutationClassifier::isUncertainAfterSend($pre));

        $sent = new AirBlueProviderException(
            'supplier_http_error',
            502,
            'HTTP error.',
            AirBlueAmbiguousMutationClassifier::withTransmissionState(
                [],
                AirBlueAmbiguousMutationClassifier::TRANSMISSION_SENT_OR_UNCERTAIN,
            ),
        );
        $this->assertTrue(AirBlueAmbiguousMutationClassifier::requestMayHaveBeenSent($sent));
        $this->assertTrue(AirBlueAmbiguousMutationClassifier::isUncertainAfterSend($sent));
    }
}
