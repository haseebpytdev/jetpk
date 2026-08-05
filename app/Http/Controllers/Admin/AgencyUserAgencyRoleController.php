<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgencyRole;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAgencyUserAgencyRoleRequest;
use App\Models\Agency;
use App\Models\User;
use App\Support\Agencies\AgencyRoleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AgencyUserAgencyRoleController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function update(UpdateAgencyUserAgencyRoleRequest $request, Agency $agency, User $user): RedirectResponse|JsonResponse
    {
        $role = AgencyRole::from($request->string('agency_role')->toString());

        AgencyRoleAssignment::assign($user, (int) $agency->id, $role, $request->user());

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'user_id' => (string) $user->id,
                'agency_id' => (string) $agency->id,
                'agency_role' => $role->value,
            ]);
        }

        return redirect()
            ->back()
            ->with('status', 'Agency role updated to '.$role->label().'. Permissions were not changed.');
    }
}
