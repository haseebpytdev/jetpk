<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplyAgencyUserAgentPermissionTemplateRequest;
use App\Http\Requests\Admin\UpdateAgencyUserAgentPermissionsRequest;
use App\Models\Agency;
use App\Models\User;
use App\Support\Agencies\AgencyStaffPermissionAssignment;
use Illuminate\Http\RedirectResponse;

class AgencyUserAgentPermissionController extends Controller
{
    public function update(UpdateAgencyUserAgentPermissionsRequest $request, Agency $agency, User $user): RedirectResponse
    {
        AgencyStaffPermissionAssignment::assignManual(
            $user,
            $request->input('permissions', []),
            $request->user(),
            (int) $agency->id,
        );

        return redirect()
            ->back()
            ->with('status', 'agent-permissions-updated');
    }

    public function applyTemplate(ApplyAgencyUserAgentPermissionTemplateRequest $request, Agency $agency, User $user): RedirectResponse
    {
        AgencyStaffPermissionAssignment::assignFromTemplate(
            $user,
            $request->user(),
            (int) $agency->id,
        );

        return redirect()
            ->back()
            ->with('status', 'agent-permissions-template-applied');
    }
}
