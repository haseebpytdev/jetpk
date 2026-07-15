<?php

namespace Tests\Unit\Support\Sabre;

use App\Support\Sabre\SabreReadinessReasonPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SabreReadinessReasonPresenterTest extends TestCase
{
    #[Test]
    public function message_for_code_returns_required_messages(): void
    {
        $presenter = new SabreReadinessReasonPresenter;

        $this->assertSame(
            'Auto-PNR is disabled by configuration.',
            $presenter->messageForCode('auto_pnr_flag_disabled'),
        );
        $this->assertSame(
            'Action blocked because the booking is cancelled.',
            $presenter->messageForCode('cancelled_booking_blocked'),
        );
    }

    #[Test]
    public function normalize_code_maps_aliases(): void
    {
        $presenter = new SabreReadinessReasonPresenter;

        $this->assertSame('missing_supplier_connection', $presenter->normalizeCode('blocked_no_supplier_connection'));
        $this->assertSame('not_sabre_booking', $presenter->normalizeCode('not_sabre'));
        $this->assertSame('missing_sabre_pnr', $presenter->normalizeCode('booking_missing_pnr'));
        $this->assertSame('ticketing_disabled', $presenter->normalizeCode('blocked_ticketing_enabled'));
    }

    #[Test]
    public function unknown_code_returns_safe_fallback(): void
    {
        $presenter = new SabreReadinessReasonPresenter;

        $this->assertSame(
            'Diagnostic reason: custom unknown reason',
            $presenter->messageForCode('custom_unknown_reason'),
        );
    }
}
