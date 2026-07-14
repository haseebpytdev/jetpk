<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Core\SabreEprEncodedCredentials;
use PHPUnit\Framework\TestCase;

class SabreEprEncodedCredentialsTest extends TestCase
{
    public function test_basic_authorization_payload_matches_triple_base64_algorithm(): void
    {
        $epr = 'MyEpr';
        $pcc = 'PCC9';
        $password = 'PassW';
        $domain = 'AA';

        $expectedUserId = 'V1:'.$epr.':'.$pcc.':'.$domain;
        $expected = base64_encode(
            base64_encode($expectedUserId).':'.base64_encode($password)
        );

        $actual = SabreEprEncodedCredentials::basicAuthorizationPayload($epr, $pcc, $password, $domain);

        $this->assertSame($expected, $actual);
        $this->assertNotSame($expectedUserId, $actual);
    }

    public function test_encoding_style_variants_match_expected_algorithms(): void
    {
        $epr = 'MyEpr';
        $pcc = 'PCC9';
        $password = 'PassW';
        $domain = 'AA';
        $userId = 'V1:'.$epr.':'.$pcc.':'.$domain;

        $this->assertSame(
            base64_encode(base64_encode($userId).':'.base64_encode($password)),
            SabreEprEncodedCredentials::basicAuthorizationPayloadForStyle(
                SabreEprEncodedCredentials::ENCODING_SABRE_EPR_ENCODED_CURRENT,
                $epr,
                $pcc,
                $password,
                $domain,
            ),
        );
        $this->assertSame(
            base64_encode($userId.':'.$password),
            SabreEprEncodedCredentials::basicAuthorizationPayloadForStyle(
                SabreEprEncodedCredentials::ENCODING_RAW_BASIC,
                $epr,
                $pcc,
                $password,
                $domain,
            ),
        );
        $this->assertSame(
            base64_encode(base64_encode($userId).':'.$password),
            SabreEprEncodedCredentials::basicAuthorizationPayloadForStyle(
                SabreEprEncodedCredentials::ENCODING_ENCODED_EPR_RAW_SECRET,
                $epr,
                $pcc,
                $password,
                $domain,
            ),
        );
        $this->assertSame(
            base64_encode($userId.':'.base64_encode($password)),
            SabreEprEncodedCredentials::basicAuthorizationPayloadForStyle(
                SabreEprEncodedCredentials::ENCODING_RAW_EPR_ENCODED_SECRET,
                $epr,
                $pcc,
                $password,
                $domain,
            ),
        );
    }
}
