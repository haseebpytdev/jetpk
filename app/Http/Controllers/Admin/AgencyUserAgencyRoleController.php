<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgencyRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAgencyUserAgencyRoleRequest;
use App\Models\Agency;
use App\Models\User;
use App\Support\Agencies\AgencyRoleAssignment;
use Illuminate\Http\RedirectResponse;

class AgencyUserAgencyRoleController extends Controller
{
    public function update(UpdateAgencyUserAgencyRoleRequest $request, Agency $agency, User $user): RedirectResponse
    {
        $role = AgencyRole::from($request->string('agency_role')->toString());

        AgencyRoleAssignment::assign($user, (int) $agency->id, $role, $request->user());

        return redirect()
            ->back()
            ->with('status', 'Agency role updated to '.$role->label().'. Permissions were not changed.');
    }
}
