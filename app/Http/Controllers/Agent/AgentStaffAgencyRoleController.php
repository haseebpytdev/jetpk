<?php

namespace App\Http\Controllers\Agent;

use App\Enums\AgencyRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateAgentStaffAgencyRoleRequest;
use App\Models\Agent;
use App\Models\User;
use App\Support\Agencies\AgencyRoleAssignment;
use Illuminate\Http\RedirectResponse;

class AgentStaffAgencyRoleController extends Controller
{
    public function update(UpdateAgentStaffAgencyRoleRequest $request, User $staff): RedirectResponse
    {
        $ownerAgent = $this->ownerAgent();
        $role = AgencyRole::from($request->string('agency_role')->toString());

        AgencyRoleAssignment::assign($staff, (int) $ownerAgent->agency_id, $role, $request->user());

        return redirect()
            ->route('agent.staff.index')
            ->with('status', 'agency-role-updated');
    }

    protected function ownerAgent(): Agent
    {
        $agent = auth()->user()?->agent();
        abort_if($agent === null, 403);

        return $agent;
    }
}
