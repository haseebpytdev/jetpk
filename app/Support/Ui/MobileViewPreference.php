<?php

namespace App\Support\Ui;

use Illuminate\Http\Request;

/**
 * Legacy mobile-shell toggle removed; stub keeps stale Blade includes from fatal errors.
 */
final class MobileViewPreference
{
    public const MODE_MOBILE = 'mobile';

    public const MODE_DESKTOP = 'desktop';

    public function currentMode(Request $request): string
    {
        return self::MODE_MOBILE;
    }
}
