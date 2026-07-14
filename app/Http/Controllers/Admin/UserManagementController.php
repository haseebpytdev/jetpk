<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\CommunicationLog;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Access\AccountTypeLabels;
use App\Support\Access\RolePermissionMatrix;
use App\Enums\OtaNotificationEvent;
use App\Services\Communication\OtaNotificationService;
use App\Support\Agencies\AgencyRoleAssignment;
use App\Support\Agencies\AgencyRolePermissionMatrix;
use App\Support\Agencies\AgencyRoleResolver;
use App\Support\Agencies\AgencyScopeResolver;
use App\Support\Agencies\AgencyStaffPermissionAssignment;
use App\Support\References\CompactReferenceGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    public function __construct(
        protected OtaNotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $actor = $request->user();
        $query = User::query()->with([
            'currentAgency.agencySetting',
            'agentProfiles.agency.agencySetting',
        ]);

        if (! $actor->isPlatformAdmin()) {
            $query->where('current_agency_id', $actor->current_agency_id)
                ->where('account_type', '!=', AccountType::PlatformAdmin->value);
        }

        if ($request->filled('account_type')) {
            $query->where('account_type', $request->string('account_type')->toString());
        }
        if ($request->filled('agency_id') && $actor->isPlatformAdmin()) {
            $query->where('current_agency_id', (int) $request->input('agency_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('username', 'like', '%'.$search.'%'));
        }

        $users = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $kpisQuery = User::query();
        if (! $actor->isPlatformAdmin()) {
            $kpisQuery->where('current_agency_id', $actor->current_agency_id)
                ->where('account_type', '!=', AccountType::PlatformAdmin->value);
        }

        return view(client_view('users.index', 'admin'), [
            'users' => $users,
            'filters' => $request->only(['account_type', 'status', 'search', 'agency_id']),
            'accountTypeLabels' => AccountTypeLabels::all(),
            'agencyOptions' => $actor->isPlatformAdmin()
                ? Agency::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'kpis' => [
                'total' => (clone $kpisQuery)->count(),
                'platform_admins' => (clone $kpisQuery)->where('account_type', AccountType::PlatformAdmin)->count(),
                'staff' => (clone $kpisQuery)->where('account_type', AccountType::Staff)->count(),
                'agency_owners' => (clone $kpisQuery)->where('account_type', AccountType::Agent)->count(),
                'agency_staff' => (clone $kpisQuery)->where('account_type', AccountType::AgentStaff)->count(),
                'customers' => (clone $kpisQuery)->where('account_type', AccountType::Customer)->count(),
                'legacy' => (clone $kpisQuery)->where('account_type', AccountType::AgencyAdmin)->count(),
                'suspended_or_invited' => (clone $kpisQuery)->whereIn('status', [UserAccountStatus::Suspended, UserAccountStatus::Invited])->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('dashboard.admin.users.create', array_merge(
            [
                'userModel' => new User,
                'isEdit' => false,
                'accountTypeOptions' => $this->accountTypeOptions($request->user()),
            ],
            $this->permissionFormData($request->user()),
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $actor = $request->user();
        $validated = $this->validatePayload($request, false, null);
        $agency = $this->resolveAgency($actor, $validated['agency_id'] ?? null);

        $user = DB::transaction(function () use ($validated, $agency, $actor): User {
            $meta = [
                'phone' => $validated['phone'] ?? null,
                'city' => $validated['city'] ?? null,
                'agency_name' => $validated['agency_name'] ?? null,
                'permission_group' => $validated['permission_group'] ?? null,
            ];
            $meta = $this->mergeAgentStaffMeta($meta, $validated);
            $meta = $this->mergeStaffMeta($meta, $validated);

            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'username' => $validated['account_type'] === AccountType::AgentStaff->value
                    ? $this->uniqueUsername($validated['email'])
                    : null,
                'password' => bcrypt(str()->random(32)),
                'account_type' => $validated['account_type'],
                'current_agency_id' => $agency?->id,
                'status' => $validated['status'],
                'invited_at' => $validated['status'] === UserAccountStatus::Invited->value ? now() : null,
                'meta' => $meta,
            ]);

            if ($agency !== null) {
                AgencyUser::query()->create([
                    'agency_id' => $agency->id,
                    'user_id' => $user->id,
                    'role' => $validated['account_type'],
                ]);
            }

            $this->syncProfile($user, $validated, $agency?->id);
            $this->writeAudit($actor, 'user.created', ['user_id' => $user->id, 'account_type' => $user->account_type?->value]);

            return $user;
        });

        if ($request->boolean('send_invite')) {
            $this->dispatchInvite($user, $actor);
        }

        $this->notifyUserLifecycleEmail($user, $agency, $actor, 'created');

        return redirect()->route('admin.users.show', $user)->with('status', 'user-created');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);
        $user->load(['staffProfile', 'agentProfile.agency.agencySetting', 'agentProfiles.agency.agencySetting', 'bookings', 'socialAccounts', 'currentAgency.agencySetting']);

        return view(client_view('users.show', 'admin'), array_merge(
            ['userModel' => $user],
            $this->permissionViewData($user),
        ));
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('dashboard.admin.users.edit', array_merge(
            [
                'userModel' => $user->load(['staffProfile', 'agentProfile']),
                'isEdit' => true,
                'accountTypeOptions' => $this->accountTypeOptions($request->user()),
            ],
            $this->permissionFormData($request->user()),
        ));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $actor = $request->user();
        $validated = $this->validatePayload($request, true, $user);
        $agency = $this->resolveAgency($actor, $validated['agency_id'] ?? $user->current_agency_id);

        DB::transaction(function () use ($user, $validated, $agency, $actor): void {
            $previousAccountType = $user->account_type?->value;
            $meta = array_merge($user->meta ?? [], [
                'phone' => $validated['phone'] ?? null,
                'city' => $validated['city'] ?? null,
                'agency_name' => $validated['agency_name'] ?? null,
                'permission_group' => $validated['permission_group'] ?? null,
            ]);
            $meta = $this->mergeAgentStaffMeta($meta, $validated, $previousAccountType);
            $meta = $this->mergeStaffMeta($meta, $validated, $previousAccountType);
            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'account_type' => $validated['account_type'],
                'status' => $validated['status'],
                'current_agency_id' => $agency?->id,
                'meta' => $meta,
            ])->save();

            if ($agency !== null) {
                AgencyUser::query()->updateOrCreate(
                    ['agency_id' => $agency->id, 'user_id' => $user->id],
                    ['role' => $validated['account_type']]
                );
            }

            $this->syncProfile($user, $validated, $agency?->id);
            $this->writeAudit($actor, 'user.updated', ['user_id' => $user->id]);
        });

        return redirect()->route('admin.users.show', $user)->with('status', 'user-updated');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('suspend', $user);
        $user->forceFill(['status' => UserAccountStatus::Suspended])->save();
        $this->writeAudit($request->user(), 'user.suspended', ['user_id' => $user->id]);
        $this->notifyUserLifecycleEmail($user, $user->currentAgency, $request->user(), 'suspended');

        return back()->with('status', 'user-suspended');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('activate', $user);
        $user->forceFill(['status' => UserAccountStatus::Active])->save();
        $this->writeAudit($request->user(), 'user.activated', ['user_id' => $user->id]);
        $this->notifyUserLifecycleEmail($user, $user->currentAgency, $request->user(), 'activated');

        return back()->with('status', 'user-activated');
    }

    public function sendInvite(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->dispatchInvite($user, $request->user());

        return back()->with('status', 'user-invite-sent');
    }

    public function sendResetPasswordLink(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        Password::sendResetLink(['email' => $user->email]);
        CommunicationLog::query()->create([
            'agency_id' => $user->current_agency_id,
            'user_id' => $user->id,
            'channel' => 'system',
            'event' => 'password_reset_requested',
            'recipient_name' => $user->name,
            'recipient_email' => $user->email,
            'status' => 'sent',
            'meta' => ['requested_by' => $request->user()->id],
            'sent_at' => now(),
        ]);
        $this->writeAudit($request->user(), 'user.password_reset_requested', ['user_id' => $user->id]);

        return back()->with('status', 'password-reset-link-sent');
    }

    protected function dispatchInvite(User $user, User $actor): void
    {
        $user->forceFill(['status' => UserAccountStatus::Invited, 'invited_at' => now()])->save();
        Password::sendResetLink(['email' => $user->email]);
        CommunicationLog::query()->create([
            'agency_id' => $user->current_agency_id,
            'user_id' => $user->id,
            'channel' => 'system',
            'event' => 'user_invited',
            'recipient_name' => $user->name,
            'recipient_email' => $user->email,
            'status' => 'sent',
            'meta' => ['invited_by' => $actor->id],
            'sent_at' => now(),
        ]);
        $this->writeAudit($actor, 'user.invited', ['user_id' => $user->id]);
    }

    /**
     * @return array<int, string>
     */
    protected function accountTypeOptions(User $actor): array
    {
        $base = [
            AccountType::AgencyAdmin->value,
            AccountType::Staff->value,
            AccountType::Agent->value,
            AccountType::AgentStaff->value,
            AccountType::Customer->value,
        ];

        return $actor->isPlatformAdmin()
            ? [AccountType::PlatformAdmin->value, ...$base]
            : $base;
    }

    protected function resolveAgency(User $actor, ?int $requestedAgencyId): ?Agency
    {
        if ($requestedAgencyId === null) {
            return $actor->currentAgency;
        }

        if ($actor->isPlatformAdmin()) {
            return Agency::query()->find($requestedAgencyId);
        }

        if ($actor->current_agency_id !== $requestedAgencyId) {
            abort(403);
        }

        return Agency::query()->find($requestedAgencyId);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function syncProfile(User $user, array $validated, ?int $agencyId): void
    {
        if ($user->account_type === AccountType::Staff) {
            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id, 'agency_id' => $agencyId],
                [
                    'job_title' => $validated['role_title'] ?? 'Staff',
                    'department' => $validated['department'] ?? 'Operations',
                    'is_active' => $user->status !== UserAccountStatus::Suspended,
                ]
            );
        }

        if ($user->account_type === AccountType::Agent) {
            Agent::query()->updateOrCreate(
                ['user_id' => $user->id, 'agency_id' => $agencyId],
                [
                    'code' => $validated['agent_code'] ?? app(CompactReferenceGenerator::class)->generateUnique('agents', 'code', 7),
                    'commission_percent' => (float) ($validated['commission_percent'] ?? 0),
                    'is_active' => $user->status !== UserAccountStatus::Suspended,
                    'meta' => [
                        'agency_name' => $validated['agency_name'] ?? null,
                        'city' => $validated['city'] ?? null,
                        'phone' => $validated['phone'] ?? null,
                    ],
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, bool $isEdit, ?User $target): array
    {
        $actor = $request->user();
        $requestedAccountType = (string) $request->input('account_type', '');

        // Enforce explicit authorization failure instead of a validation error.
        if (! $actor->isPlatformAdmin() && $requestedAccountType === AccountType::PlatformAdmin->value) {
            abort(403);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'account_type' => ['required', 'in:'.implode(',', $this->accountTypeOptions($actor))],
            'status' => ['required', Rule::enum(UserAccountStatus::class)],
            'agency_id' => ['nullable', 'integer', 'exists:agencies,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:255'],
            'role_title' => ['nullable', 'string', 'max:255'],
            'permission_group' => ['nullable', 'string', 'max:255'],
            'owner_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'staff_permissions_configured' => ['nullable', 'boolean'],
            'agency_name' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'agent_code' => ['nullable', 'string', 'max:255'],
            'send_invite' => ['nullable', 'boolean'],
        ];

        if ($request->input('account_type') === AccountType::Staff->value) {
            $rules['staff_permissions'] = ['nullable', 'array'];
            $rules['staff_permissions.*'] = ['string', Rule::in(StaffPermission::staffSelectable())];
        }

        $validated = $request->validate($rules);
        if ($isEdit && $target !== null && ! $actor->isPlatformAdmin() && $target->current_agency_id !== $actor->current_agency_id) {
            abort(403);
        }

        if ($validated['account_type'] === AccountType::AgentStaff->value) {
            $ownerAgent = Agent::query()->find($validated['owner_agent_id'] ?? null);
            if ($ownerAgent === null) {
                throw ValidationException::withMessages([
                    'owner_agent_id' => 'An owner agent is required for agent staff users.',
                ]);
            }

            $agencyId = $isEdit && $target !== null
                ? $target->current_agency_id
                : ($actor->isPlatformAdmin()
                    ? ($validated['agency_id'] ?? $actor->current_agency_id)
                    : $actor->current_agency_id);

            if ($ownerAgent->agency_id !== $agencyId) {
                throw ValidationException::withMessages([
                    'owner_agent_id' => 'The selected agent must belong to the same agency.',
                ]);
            }
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    protected function permissionFormData(User $actor): array
    {
        return [
            'permissionMatricesByType' => $this->permissionMatricesByType(),
            'permissionScopeNotes' => $this->permissionScopeNotes(),
            'rolePresets' => $this->rolePresetLabels(),
            'groupedAgentPermissions' => $this->groupAgentPermissions(),
            'groupedStaffPermissions' => $this->groupStaffPermissions(),
            'staffPresetLabels' => RolePermissionMatrix::staffPresetLabels(),
            'staffPresetPermissions' => RolePermissionMatrix::staffPresetPermissions(),
            'groupedEffectiveAccessByType' => $this->groupedEffectiveAccessByType(),
            'agentPermissionLabels' => RolePermissionMatrix::agentStaffPermissionLabels(),
            'agencyAgents' => $this->agencyAgentsForActor($actor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function permissionViewData(User $userModel): array
    {
        $accountType = $userModel->account_type;

        return [
            'rolePreset' => $accountType !== null ? $this->rolePresetLabel($accountType) : 'Unknown',
            'agencyScope' => $this->agencyScopeLabel($userModel),
            'agencyRoleLabel' => in_array($accountType, [AccountType::Agent, AccountType::AgentStaff], true)
                ? AgencyRoleResolver::labelFor($userModel, $userModel->current_agency_id)
                : null,
            'agencyRoleIsStored' => in_array($accountType, [AccountType::Agent, AccountType::AgentStaff], true)
                ? AgencyRoleResolver::isStoredRole($userModel, $userModel->current_agency_id)
                : false,
            'agencyRoleCurrentValue' => in_array($accountType, [AccountType::Agent, AccountType::AgentStaff], true)
                && $userModel->current_agency_id !== null
                ? AgencyRoleResolver::resolve($userModel, $userModel->current_agency_id)->value
                : null,
            'agencyRoleOptions' => in_array($accountType, [AccountType::Agent, AccountType::AgentStaff], true)
                ? AgencyRoleAssignment::roleOptionsForActor(auth()->user() ?? $userModel)
                : [],
            'agencyRoleAssignmentAgencyId' => in_array($accountType, [AccountType::Agent, AccountType::AgentStaff], true)
                ? $userModel->current_agency_id
                : null,
            'permissionScopeNote' => $accountType !== null ? RolePermissionMatrix::scopeNote($accountType) : null,
            'groupedEffectiveAccess' => $accountType !== null ? $this->groupEffectiveAccess($accountType) : [],
            'isAgentStaffPermissions' => $accountType === AccountType::AgentStaff,
            'isStaffPermissions' => $accountType === AccountType::Staff,
            'isEditableMatrix' => false,
            'isEditableAgentStaffPermissions' => $accountType === AccountType::AgentStaff
                && $userModel->current_agency_id !== null,
            'agentPermissionsUpdateRoute' => $accountType === AccountType::AgentStaff && $userModel->current_agency_id !== null
                ? route('admin.agencies.users.agent-permissions.update', [
                    'agency' => $userModel->current_agency_id,
                    'user' => $userModel,
                ])
                : null,
            'agentPermissionsApplyTemplateRoute' => $accountType === AccountType::AgentStaff && $userModel->current_agency_id !== null
                ? route('admin.agencies.users.agent-permissions.apply-template', [
                    'agency' => $userModel->current_agency_id,
                    'user' => $userModel,
                ])
                : null,
            'canApplyAgentStaffRoleTemplate' => $accountType === AccountType::AgentStaff
                && $userModel->current_agency_id !== null
                && AgencyRoleResolver::resolve($userModel, $userModel->current_agency_id) !== AgencyRole::Owner,
            'agentStaffRoleTemplateSummary' => $accountType === AccountType::AgentStaff && $userModel->current_agency_id !== null
                ? AgencyRolePermissionMatrix::suggestedPermissionSummary(
                    AgencyRoleResolver::resolve($userModel, $userModel->current_agency_id),
                )
                : null,
            'groupedAgentPermissions' => $this->groupAgentPermissions(),
            'groupedStaffPermissions' => $this->groupStaffPermissions(),
            'selectedAgentPermissions' => is_array($userModel->meta['agent_permissions'] ?? null)
                ? $userModel->meta['agent_permissions']
                : [],
            'selectedStaffPermissions' => is_array($userModel->meta['staff_permissions'] ?? null)
                ? $userModel->meta['staff_permissions']
                : [],
            'usesLegacyStaffPermissions' => $userModel->usesLegacyStaffPermissions(),
            'staffAccessModeLabel' => $accountType === AccountType::Staff
                ? ($userModel->usesLegacyStaffPermissions()
                    ? 'Default staff access active'
                    : 'Permission-based access')
                : null,
            'staffAccessModeHelp' => $accountType === AccountType::Staff && $userModel->usesLegacyStaffPermissions()
                ? 'Once staff permissions are saved, this user will follow the selected permission controls.'
                : null,
            'permissionGroupLabel' => $userModel->meta['permission_group'] ?? null,
            'ownerAgent' => $accountType === AccountType::AgentStaff
                ? Agent::query()->find($userModel->meta['owner_agent_id'] ?? null)?->load('user')
                : null,
            'linkedProviders' => $userModel->socialAccounts->pluck('provider')->unique()->values()->all(),
            'isCustomerAccount' => $accountType === AccountType::Customer,
            'showAgentStaffOwnerLabelWarning' => $this->showAgentStaffOwnerLabelWarning($userModel),
            'showOwnerAccountTypeNote' => $accountType === AccountType::Agent,
            'showRecentPermissionAuditPanel' => $accountType === AccountType::AgentStaff,
            'recentPermissionAuditLogs' => $this->recentAgentPermissionAuditRows($userModel),
            'agencyActivityUrl' => $accountType === AccountType::AgentStaff && $userModel->current_agency_id !== null
                ? route('admin.agencies.show', ['agency' => $userModel->current_agency_id, 'tab' => 'activity'])
                : null,
        ];
    }

    protected function showAgentStaffOwnerLabelWarning(User $userModel): bool
    {
        if ($userModel->account_type !== AccountType::AgentStaff || $userModel->current_agency_id === null) {
            return false;
        }

        return AgencyRoleResolver::resolve($userModel, $userModel->current_agency_id) === AgencyRole::Owner;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentAgentPermissionAuditRows(User $userModel): array
    {
        if ($userModel->account_type !== AccountType::AgentStaff) {
            return [];
        }

        $logs = AuditLog::query()
            ->where('action', 'agent_permissions.updated')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $userModel->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'user_id', 'properties', 'created_at']);

        if ($logs->isEmpty()) {
            return [];
        }

        $actorIds = $logs->pluck('user_id')->filter()->unique()->all();
        $actors = $actorIds === []
            ? collect()
            : User::query()->whereIn('id', $actorIds)->get(['id', 'name'])->keyBy('id');

        return $logs->map(function (AuditLog $log) use ($actors): array {
            $properties = is_array($log->properties) ? $log->properties : [];
            $newValues = is_array($properties['new_values'] ?? null) ? $properties['new_values'] : [];
            $oldPermissions = $newValues['old_permissions']
                ?? (is_array($properties['old_values']['agent_permissions'] ?? null) ? $properties['old_values']['agent_permissions'] : []);
            $newPermissions = is_array($newValues['new_permissions'] ?? null) ? $newValues['new_permissions'] : [];
            $source = (string) ($newValues['source'] ?? 'manual');
            $agencyRoleValue = $newValues['agency_role'] ?? null;
            $agencyRole = AgencyRole::fromNullable($agencyRoleValue);

            return [
                'id' => (int) $log->id,
                'created_at' => $log->created_at?->format('M j, Y g:i A') ?? '—',
                'actor_name' => filled($log->user_id) ? ($actors->get((int) $log->user_id)?->name ?? 'Unknown') : 'System',
                'source_label' => $source === AgencyStaffPermissionAssignment::SourceRoleTemplate ? 'Role template' : 'Manual',
                'old_count' => count($oldPermissions),
                'new_count' => count($newPermissions),
                'agency_role_label' => $agencyRole?->label(),
                'properties' => $properties,
            ];
        })->all();
    }

    /**
     * @return array<string, string>
     */
    protected function rolePresetLabels(): array
    {
        $labels = [];
        foreach (AccountType::cases() as $accountType) {
            $labels[$accountType->value] = $this->rolePresetLabel($accountType);
        }

        return $labels;
    }

    protected function rolePresetLabel(AccountType $accountType): string
    {
        return match ($accountType) {
            AccountType::PlatformAdmin => 'Platform Admin — full platform access',
            AccountType::AgencyAdmin => 'Legacy Agency Admin — disabled account type',
            AccountType::Staff => 'Platform Staff — granular staff portal permissions',
            AccountType::Agent => 'Agency Owner — portal owner (full agent access)',
            AccountType::AgentStaff => 'Agency Staff — granular agent portal permissions',
            AccountType::Customer => 'Customer — customer portal only',
        };
    }

    protected function agencyScopeLabel(User $userModel): string
    {
        return AgencyScopeResolver::scopeLabel($userModel);
    }

    /**
     * @return array<string, list<array{area: string, access: string, enabled: bool, limited: bool}>>
     */
    protected function groupEffectiveAccess(AccountType $accountType): array
    {
        $rows = collect(RolePermissionMatrix::effectiveFor($accountType))->keyBy('area');
        $groups = [];
        foreach (RolePermissionMatrix::effectiveAccessModuleGroups() as $groupName => $areas) {
            $items = [];
            foreach ($areas as $area) {
                if (! $rows->has($area)) {
                    continue;
                }
                $access = $rows[$area]['access'];
                $items[] = [
                    'area' => $area,
                    'access' => $access,
                    'enabled' => in_array($access, [RolePermissionMatrix::Allowed, RolePermissionMatrix::Limited], true),
                    'limited' => $access === RolePermissionMatrix::Limited,
                ];
            }
            if ($items !== []) {
                $groups[$groupName] = $items;
            }
        }

        return $groups;
    }

    /**
     * @return array<string, list<array{area: string, access: string, enabled: bool, limited: bool}>>
     */
    protected function groupedEffectiveAccessByType(): array
    {
        $grouped = [];
        foreach (AccountType::cases() as $accountType) {
            if (in_array($accountType, [AccountType::AgentStaff, AccountType::Staff], true)) {
                continue;
            }
            $grouped[$accountType->value] = $this->groupEffectiveAccess($accountType);
        }

        return $grouped;
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function groupAgentPermissions(): array
    {
        $labels = RolePermissionMatrix::agentStaffPermissionLabels();
        $groups = [];
        foreach (RolePermissionMatrix::agentStaffModuleGroups() as $groupName => $keys) {
            $items = [];
            foreach ($keys as $key) {
                if (isset($labels[$key])) {
                    $items[$key] = $labels[$key];
                }
            }
            if ($items !== []) {
                $groups[$groupName] = $items;
            }
        }

        return $groups;
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function groupStaffPermissions(): array
    {
        $labels = RolePermissionMatrix::staffPermissionLabels();
        $groups = [];
        foreach (RolePermissionMatrix::staffModuleGroups() as $groupName => $keys) {
            $items = [];
            foreach ($keys as $key) {
                if (isset($labels[$key])) {
                    $items[$key] = $labels[$key];
                }
            }
            if ($items !== []) {
                $groups[$groupName] = $items;
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function mergeStaffMeta(array $meta, array $validated, ?string $previousAccountType = null): array
    {
        if ($validated['account_type'] !== AccountType::Staff->value) {
            if ($previousAccountType === AccountType::Staff->value) {
                unset($meta['staff_permissions']);
            }

            return $meta;
        }

        if ($previousAccountType !== AccountType::Staff->value || ! empty($validated['staff_permissions_configured'])) {
            $meta['staff_permissions'] = RolePermissionMatrix::normalizeStaffPermissions(
                $validated['staff_permissions'] ?? [],
            );
        }

        return $meta;
    }

    /**
     * @return array<string, list<array{area: string, access: string}>>
     */
    protected function permissionMatricesByType(): array
    {
        $matrices = [];
        foreach (AccountType::cases() as $accountType) {
            if (! RolePermissionMatrix::showsMatrix($accountType) || RolePermissionMatrix::isEditable($accountType)) {
                continue;
            }
            $matrices[$accountType->value] = RolePermissionMatrix::effectiveFor($accountType);
        }

        return $matrices;
    }

    /**
     * @return array<string, string>
     */
    protected function permissionScopeNotes(): array
    {
        $notes = [];
        foreach (AccountType::cases() as $accountType) {
            $note = RolePermissionMatrix::scopeNote($accountType);
            if ($note !== null) {
                $notes[$accountType->value] = $note;
            }
        }

        return $notes;
    }

    /**
     * @return Collection<int, Agent>
     */
    protected function agencyAgentsForActor(User $actor)
    {
        $query = Agent::query()->with('user')->where('is_active', true);

        if (! $actor->isPlatformAdmin()) {
            $query->where('agency_id', $actor->current_agency_id);
        }

        return $query->orderBy('code')->get();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function mergeAgentStaffMeta(array $meta, array $validated, ?string $previousAccountType = null): array
    {
        if ($validated['account_type'] !== AccountType::AgentStaff->value) {
            if ($previousAccountType === AccountType::AgentStaff->value) {
                unset($meta['agent_permissions'], $meta['owner_agent_id']);
            }

            return $meta;
        }

        $meta['owner_agent_id'] = (int) $validated['owner_agent_id'];
        $meta['agent_permissions'] = RolePermissionMatrix::normalizeAgentPermissions($validated['permissions'] ?? []);

        return $meta;
    }

    protected function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: 'agent-staff';
        $candidate = $base;
        $suffix = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    protected function writeAudit(User $actor, string $action, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $actor->current_agency_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $newValues['user_id'] ?? $actor->id,
            'properties' => ['old_values' => [], 'new_values' => $newValues],
        ]);
    }

    protected function notifyUserLifecycleEmail(User $user, ?Agency $agency, User $actor, string $action): void
    {
        if ($agency === null) {
            return;
        }

        $eventKey = match ($action) {
            'created' => match ($user->account_type) {
                AccountType::AgentStaff, AccountType::Staff => OtaNotificationEvent::StaffCreated->value,
                AccountType::Agent => OtaNotificationEvent::AgentCreated->value,
                default => null,
            },
            'suspended' => OtaNotificationEvent::UserSuspended->value,
            'activated' => OtaNotificationEvent::UserActivated->value,
            default => null,
        };

        if ($eventKey === null) {
            return;
        }

        $designation = $this->recipientDesignationLabel($user);

        try {
            $this->notificationService->send(
                agency: $agency,
                eventKey: $eventKey,
                payload: [
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                ],
                actor: $user,
                fallbackSubject: 'Account notification',
                fallbackBody: 'Your account status was updated.',
                templateVariables: [
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'recipient_designation' => $designation,
                ],
                recipientContext: [
                    'user_email' => $user->email,
                    'staff_email' => $user->email,
                    'recipient_designation' => $designation,
                ],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function recipientDesignationLabel(User $user): string
    {
        if ($user->account_type === AccountType::AgentStaff && $user->current_agency_id !== null) {
            return AgencyRoleResolver::resolve($user, $user->current_agency_id)?->label() ?? 'Agency Staff';
        }

        return AccountTypeLabels::label($user->account_type);
    }
}
