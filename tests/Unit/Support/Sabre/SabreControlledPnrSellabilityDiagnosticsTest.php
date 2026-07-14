<?php

namespace Tests\Unit\Support\Sabre;

use App\Support\Sabre\SabreControlledPnrSellabilityDiagnostics;
use Tests\TestCase;

class SabreControlledPnrSellabilityDiagnosticsTest extends TestCase
{
    public function test_same_normalized_string_list_true_when_rbd_lists_match_after_normalization(): void
    {
        $this->assertTrue(SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList(['O', 'O'], ['O', 'O']));
        $this->assertTrue(SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList([' o ', 'O'], ['O', 'O']));
        $this->assertTrue(SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList(['OJPKP1RI', 'OJPKP1RI'], ['ojpkp1ri', 'OJPKP1RI']));
    }

    public function test_same_normalized_string_list_false_when_lists_differ(): void
    {
        $this->assertFalse(SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList(['O', 'Y'], ['O', 'O']));
        $this->assertFalse(SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList([], ['O']));
        $this->assertFalse(SabreControlledPnrSellabilityDiagnostics::sameNormalizedStringList(['O'], []));
    }
}
