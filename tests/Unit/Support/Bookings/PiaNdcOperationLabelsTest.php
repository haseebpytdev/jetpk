<?php

namespace Tests\Unit\Support\Bookings;

use App\Support\Bookings\PiaNdcOperationLabels;
use Tests\TestCase;

class PiaNdcOperationLabelsTest extends TestCase
{
    public function test_display_for_config_key_uses_supplier_soap_action(): void
    {
        $this->assertSame('doOrderChange', PiaNdcOperationLabels::displayForConfigKey('order_change'));
        $this->assertSame('doVoidTicket', PiaNdcOperationLabels::displayForConfigKey('void_ticket'));
        $this->assertSame('doTicketPreview', PiaNdcOperationLabels::displayForConfigKey('ticket_preview'));
    }

    public function test_sanitize_display_operation_fixes_legacy_typos(): void
    {
        $this->assertSame('doOrderChange', PiaNdcOperationLabels::sanitizeDisplayOperation('doOrderChangee', 'order_change'));
        $this->assertSame('doVoidTicket', PiaNdcOperationLabels::sanitizeDisplayOperation('doVoidTickett', 'void_ticket'));
    }

    public function test_apply_to_summary_sets_canonical_operation(): void
    {
        $summary = PiaNdcOperationLabels::applyToSummary(['operation' => 'doOrderChangee'], 'order_change');

        $this->assertSame('doOrderChange', $summary['operation']);
    }
}
