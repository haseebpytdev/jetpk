<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\Developer\DevCpMonitoringSnapshotService;
use Illuminate\Contracts\View\View;

/**
 * Dev CP monitoring panels (health, Sabre, group ticketing, dashboards, deployment).
 */
class DevCpMonitoringController extends Controller
{
    public function __construct(
        protected DevCpMonitoringSnapshotService $monitoring,
    ) {}

    public function health(): View
    {
        return view('developer.monitoring.health', [
            'snapshot' => $this->monitoring->systemHealth(),
        ]);
    }

    public function sabre(): View
    {
        return view('developer.monitoring.sabre', [
            'snapshot' => $this->monitoring->sabreStatus(),
        ]);
    }

    public function groupTicketing(): View
    {
        return view('developer.monitoring.group-ticketing', [
            'snapshot' => $this->monitoring->groupTicketingStatus(),
        ]);
    }

    public function dashboards(): View
    {
        return view('developer.monitoring.dashboards', [
            'snapshot' => $this->monitoring->dashboardsStatus(),
        ]);
    }

    public function deployment(): View
    {
        return view('developer.monitoring.deployment', [
            'snapshot' => $this->monitoring->deploymentStatus(),
        ]);
    }
}
