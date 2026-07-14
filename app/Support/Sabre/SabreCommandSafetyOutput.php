<?php

namespace App\Support\Sabre;

/**
 * Standard read-only / live-call safety lines for Sabre CLI output (F5).
 */
final class SabreCommandSafetyOutput
{
    /**
     * @return list<string>
     */
    public static function readOnlyBanner(): array
    {
        return [
            'Classification: READ-ONLY (no supplier mutation)',
            'live_supplier_call_attempted=false',
        ];
    }

    public static function liveSupplierCallAttempted(bool $attempted): string
    {
        return 'live_supplier_call_attempted='.($attempted ? 'true' : 'false');
    }
}
