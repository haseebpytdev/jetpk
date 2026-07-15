<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ApplyAgentStaffPermissionTemplateRequest;
use App\Http\Requests\Agent\UpdateAgentStaffPermissionsRequest;
use App\Models\Agent;
use App\Models\User;
use App\Support\Agencies\AgencyStaffPermissionAssignment;
use Illuminate\Http\RedirectResponse;

class AgentStaffPermissionController extends Controller
{
    public function update(UpdateAgentStaffPermissionsRequest $request, User $staff): RedirectResponse
    {
        $ownerAgent = $this->ownerAgent();

        AgencyStaffPermissionAssignment::assignManual(
            $staff,
            $request->input('permissions', []),
            $request->user(),
            (int) $ownerAgent->agency_id,
        );

        return redirect()
            ->back()
            ->with('status', 'staff-permissions-updated');
    }

    public function applyTemplate(ApplyAgentStaffPermissionTemplateRequest $request, User $staff): RedirectResponse
    {
        $ownerAgent = $this->ownerAgent();

        AgencyStaffPermissionAssignment::assignFromTemplate(
            $staff,
            $request->user(),
            (int) $ownerAgent->agency_id,
        );

        return redirect()
            ->back()
            ->with('status', 'staff-permissions-template-applied');
    }

    protected function ownerAgent(): Agent
    {
        $agent = auth()->user()?->agent() ?? auth()->user()?->employerAgent();
        abort_if($agent === null, 403);

        return $agent;
    }
}
