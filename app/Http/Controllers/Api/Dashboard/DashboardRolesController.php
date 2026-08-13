<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\Api\DashboardRolesReadService;
use App\Services\Rbac\RbacWriteService;
use App\Support\Dashboard\DashboardReadOnlyEnvelope;
use App\Support\Rbac\RbacGuardException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardRolesController extends Controller
{
    public function __construct(
        protected DashboardRolesReadService $roles,
        protected RbacWriteService $writes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->roles->list($request->user(), $request);

        return DashboardReadOnlyEnvelope::success(
            [
                'roles' => $result['items'],
                'summary' => $result['summary'],
                'catalogPermissions' => $this->roles->catalog(),
            ],
            pagination: $result['pagination'],
            filters: $result['filters'],
            staleAfter: now()->addSeconds(300)->toIso8601String(),
            recordCount: count($result['items']),
        );
    }

    public function show(Request $request, string $role): JsonResponse
    {
        $detail = $this->roles->detail($request->user(), $role);
        if ($detail === null) {
            return DashboardReadOnlyEnvelope::error('not_found', 'The requested role was not found.', 404, 'ROL-NOT-FOUND');
        }

        return DashboardReadOnlyEnvelope::success($detail, staleAfter: now()->addSeconds(300)->toIso8601String(), recordCount: 1);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:64'],
                'slug' => ['nullable', 'string', 'max:64'],
                'description' => ['nullable', 'string', 'max:255'],
                'agency_id' => ['required', 'integer', 'min:1', 'exists:agencies,id'],
                'permission_keys' => ['nullable', 'array'],
                'permission_keys.*' => ['string', 'max:128'],
            ]);
            $role = $this->writes->createCustomRole(
                $request->user(),
                $validated['name'],
                (string) ($validated['slug'] ?? ''),
                (int) $validated['agency_id'],
                $validated['permission_keys'] ?? [],
                $validated['description'] ?? null,
            );

            return DashboardReadOnlyEnvelope::success(
                $this->roles->detail($request->user(), (string) $role->id),
                recordCount: 1,
            );
        } catch (RbacGuardException $e) {
            return $this->guardError($e);
        }
    }

    public function cloneRole(Request $request, string $role): JsonResponse
    {
        try {
            $source = $this->roles->findRole($role);
            if ($source === null) {
                return DashboardReadOnlyEnvelope::error('not_found', 'The requested role was not found.', 404, 'ROL-NOT-FOUND');
            }
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:64'],
                'slug' => ['nullable', 'string', 'max:64'],
                'agency_id' => ['nullable', 'integer', 'min:1', 'exists:agencies,id'],
            ]);
            $cloned = $this->writes->cloneRole(
                $request->user(),
                $source,
                $validated['name'],
                (string) ($validated['slug'] ?? ''),
                isset($validated['agency_id']) ? (int) $validated['agency_id'] : null,
            );

            return DashboardReadOnlyEnvelope::success(
                $this->roles->detail($request->user(), (string) $cloned->id),
                recordCount: 1,
            );
        } catch (RbacGuardException $e) {
            return $this->guardError($e);
        }
    }

    public function update(Request $request, string $role): JsonResponse
    {
        try {
            $model = $this->roles->findRole($role);
            if ($model === null) {
                return DashboardReadOnlyEnvelope::error('not_found', 'The requested role was not found.', 404, 'ROL-NOT-FOUND');
            }
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:64'],
                'slug' => ['sometimes', 'string', 'max:64'],
                'description' => ['sometimes', 'nullable', 'string', 'max:255'],
                'permission_keys' => ['sometimes', 'array'],
                'permission_keys.*' => ['string', 'max:128'],
            ]);
            if (array_key_exists('name', $validated) || array_key_exists('slug', $validated) || array_key_exists('description', $validated)) {
                $model = $this->writes->updateCustomRole($request->user(), $model, $validated);
            }
            if (array_key_exists('permission_keys', $validated)) {
                $model = $this->writes->syncRolePermissions($request->user(), $model, $validated['permission_keys']);
            }

            return DashboardReadOnlyEnvelope::success(
                $this->roles->detail($request->user(), (string) $model->id),
                recordCount: 1,
            );
        } catch (RbacGuardException $e) {
            return $this->guardError($e);
        }
    }

    public function destroy(Request $request, string $role): JsonResponse
    {
        try {
            $model = $this->roles->findRole($role);
            if ($model === null) {
                return DashboardReadOnlyEnvelope::error('not_found', 'The requested role was not found.', 404, 'ROL-NOT-FOUND');
            }
            $this->writes->deleteCustomRole($request->user(), $model);

            return DashboardReadOnlyEnvelope::success(['ok' => true], recordCount: 0);
        } catch (RbacGuardException $e) {
            return $this->guardError($e);
        }
    }

    public function assign(Request $request, string $role): JsonResponse
    {
        try {
            $model = $this->roles->findRole($role);
            if ($model === null) {
                return DashboardReadOnlyEnvelope::error('not_found', 'The requested role was not found.', 404, 'ROL-NOT-FOUND');
            }
            $validated = $request->validate([
                'user_id' => ['required', 'integer'],
            ]);
            $target = User::query()->findOrFail((int) $validated['user_id']);
            $this->writes->assignUser($request->user(), $model, $target);

            return DashboardReadOnlyEnvelope::success(
                $this->roles->detail($request->user(), (string) $model->id),
                recordCount: 1,
            );
        } catch (RbacGuardException $e) {
            return $this->guardError($e);
        }
    }

    public function unassign(Request $request, string $role): JsonResponse
    {
        try {
            $model = $this->roles->findRole($role);
            if ($model === null) {
                return DashboardReadOnlyEnvelope::error('not_found', 'The requested role was not found.', 404, 'ROL-NOT-FOUND');
            }
            $validated = $request->validate([
                'user_id' => ['required', 'integer'],
            ]);
            $target = User::query()->findOrFail((int) $validated['user_id']);
            $this->writes->unassignUser($request->user(), $model, $target);

            return DashboardReadOnlyEnvelope::success(
                $this->roles->detail($request->user(), (string) $model->id),
                recordCount: 1,
            );
        } catch (RbacGuardException $e) {
            return $this->guardError($e);
        }
    }

    private function guardError(RbacGuardException $e): JsonResponse
    {
        return DashboardReadOnlyEnvelope::error($e->codeKey, $e->getMessage(), $e->status, strtoupper($e->codeKey));
    }
}
