<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Services\Developer\DevCpMonitoringSnapshotService;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Support\Platform\PlatformModuleRegistry;
use Illuminate\Contracts\View\View;

/**
 * Dev CP overview dashboard.
 */
class DevCpOverviewController extends Controller
{
    public function __construct(
        protected DevCpMonitoringSnapshotService $monitoring,
        protected PlatformModuleSettingsService $moduleSettings,
    ) {}

    public function index(): View
    {
        $stats = $this->monitoring->overviewStats();
        $moduleEnabled = 0;
        $moduleDisabled = 0;

        foreach (PlatformModuleRegistry::all() as $module) {
            if ($this->moduleSettings->stateFor($module->key)) {
                $moduleEnabled++;
            } else {
                $moduleDisabled++;
            }
        }

        $recentEvents = SecurityEvent::query()
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('developer.control-panel.index', [
            'stats' => $stats,
            'moduleEnabled' => $moduleEnabled,
            'moduleDisabled' => $moduleDisabled,
            'recentEvents' => $recentEvents,
        ]);
    }
}
