<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\RedirectResponse;

/**
 * Legacy Dev CP company routes — agencies are managed in OTA Admin Panel, not Dev CP.
 */
class DevCpPlatformController extends Controller
{
    private const LEGACY_MESSAGE = 'Agencies are managed by Platform Admin inside the OTA Admin Panel.';

    public function companies(): RedirectResponse
    {
        return redirect()
            ->route('dev.cp.users.index')
            ->with('status', self::LEGACY_MESSAGE);
    }

    public function assignPackage(Agency $agency): RedirectResponse
    {
        return redirect()
            ->route('dev.cp.users.index')
            ->with('status', self::LEGACY_MESSAGE);
    }

    public function updateModules(Agency $agency): RedirectResponse
    {
        return redirect()
            ->route('dev.cp.users.index')
            ->with('status', self::LEGACY_MESSAGE);
    }
}
