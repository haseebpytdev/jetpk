<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplyAgencyUserAgentPermissionTemplateRequest;
use App\Http\Requests\Admin\UpdateAgencyUserAgentPermissionsRequest;
use App\Models\Agency;
use App\Models\User;
use App\Support\Agencies\AgencyStaffPermissionAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AgencyUserAgentPermissionController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function update(UpdateAgencyUserAgentPermissionsRequest $request, Agency $agency, User $user): RedirectResponse|JsonResponse
    {
        AgencyStaffPermissionAssignment::assignManual(
            $user,
            $request->input('permissions', []),
            $request->user(),
            (int) $agency->id,
        );

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'user_id' => (string) $user->id,
                'agency_id' => (string) $agency->id,
                'permissions' => $request->input('permissions', []),
            ]);
        }

        return redirect()
            ->back()
            ->with('status', 'agent-permissions-updated');
    }

    public function applyTemplate(ApplyAgencyUserAgentPermissionTemplateRequest $request, Agency $agency, User $user): RedirectResponse|JsonResponse
    {
        AgencyStaffPermissionAssignment::assignFromTemplate(
            $user,
            $request->user(),
            (int) $agency->id,
        );

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'user_id' => (string) $user->id,
                'agency_id' => (string) $agency->id,
                'template_applied' => true,
            ]);
        }

        return redirect()
            ->back()
            ->with('status', 'agent-permissions-template-applied');
    }
}
