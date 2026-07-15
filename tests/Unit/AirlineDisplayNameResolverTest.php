<?php

namespace Tests\Unit;

use App\Support\FlightSearch\AirlineDisplayNameResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirlineDisplayNameResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_common_carrier_codes_from_config(): void
    {
        $map = AirlineDisplayNameResolver::mapForCodes(['SV', 'PK', 'EK']);

        $this->assertSame('Saudia', $map['SV']);
        $this->assertSame('Pakistan International Airlines', $map['PK']);
        $this->assertSame('Emirates', $map['EK']);
    }

    public function test_resolves_jetpk_canonical_codes_pf_and_9p(): void
    {
        $map = AirlineDisplayNameResolver::mapForCodes(['PF', '9P', 'PA', 'G9', 'WY']);

        $this->assertSame('AirSial', $map['PF']);
        $this->assertSame('Fly Jinnah', $map['9P']);
        $this->assertSame('Airblue', $map['PA']);
        $this->assertSame('Air Arabia', $map['G9']);
        $this->assertSame('Oman Air', $map['WY']);
    }

    public function test_supplier_alias_pia_resolves_to_canonical_pk_name(): void
    {
        $this->assertSame(
            'Pakistan International Airlines',
            AirlineDisplayNameResolver::resolve('PIA'),
        );
    }

    public function test_unknown_code_falls_back_to_code(): void
    {
        $this->assertSame('ZZ', AirlineDisplayNameResolver::resolve('ZZ'));
    }

    public function test_prefers_supplier_name_when_not_code_like(): void
    {
        $this->assertSame(
            'Emirates',
            AirlineDisplayNameResolver::resolve('EK', 'Emirates', ['EK' => 'Config Override'])
        );
    }

    public function test_treats_code_like_supplier_name_as_alias(): void
    {
        $map = ['SV' => 'Saudia'];

        $this->assertSame('Saudia', AirlineDisplayNameResolver::resolve('SV', 'SV', $map));
        $this->assertSame('Saudia', AirlineDisplayNameResolver::resolve('SV', 'SV + EK', $map));
    }

    public function test_resolve_for_offer_uses_primary_display_carrier(): void
    {
        $map = ['PK' => 'Pakistan International Airlines'];

        $name = AirlineDisplayNameResolver::resolveForOffer([
            'airline_code' => 'PK',
            'airline_name' => 'PK',
            'primary_display_carrier' => 'PK',
        ], $map);

        $this->assertSame('Pakistan International Airlines', $name);
    }

    public function test_is_code_like_name_detects_iata_and_chains(): void
    {
        $this->assertTrue(AirlineDisplayNameResolver::isCodeLikeName('SV', 'SV'));
        $this->assertTrue(AirlineDisplayNameResolver::isCodeLikeName('SV + EK'));
        $this->assertFalse(AirlineDisplayNameResolver::isCodeLikeName('Saudia', 'SV'));
    }
}
