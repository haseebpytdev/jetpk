<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Enums\AgentDepositRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentDepositRequest;
use App\Models\User;
use App\Services\Agents\AgentWalletService;
use App\Support\Access\RolePermissionMatrix;
use App\Support\Agencies\AgencyPrefixService;
use App\Support\Agencies\AgencyRoleResolver;
use App\Support\Identity\ActorIdentifier;
use App\Support\Identity\IdentityDisplay;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Admin agency (company) list and profile — distinct from user accounts.
 */
class AgencyManagementController extends Controller
{
    public function __construct(
        protected AgentWalletService $walletService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Agency::class);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $query = Agency::query()
            ->with([
                'agencySetting',
                'agents' => fn ($q) => $q->with(['user', 'wallet'])->orderByDesc('id'),
            ])
            ->withCount([
                'bookings',
                'users as staff_count' => fn (Builder $q): Builder => $q->where('account_type', AccountType::AgentStaff),
            ]);

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhereHas('agents.user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($status === 'active') {
            $query->whereHas('agents', fn (Builder $q): Builder => $q->where('is_active', true));
        } elseif ($status === 'inactive') {
            $query->whereDoesntHave('agents', fn (Builder $q): Builder => $q->where('is_active', true));
        }

        $agencies = $query->orderByDesc('id')->paginate(25)->withQueryString();

        $agencyRows = $agencies->getCollection()->map(fn (Agency $agency): array => $this->buildAgencyRow($agency));

        return view(client_view('agencies.index', 'admin'), [
            'agencies' => $agencies,
            'agencyRows' => $agencyRows,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'kpis' => [
                'total' => Agency::query()->count(),
                'active' => $this->countAgenciesByStatus('active'),
                'inactive' => $this->countAgenciesByStatus('inactive'),
            ],
        ]);
    }

    public function show(Request $request, Agency $agency): View
    {
        Gate::authorize('view', $agency);

        $primaryAgent = $this->primaryAgentFor($agency);
        $ownerUser = $primaryAgent?->user;
        $allowedTabs = [
            'overview',
            'owner',
            'staff',
            'markups',
            'wallet',
            'deposits',
            'bookings',
            'travelers',
            'support',
            'activity',
        ];
        $activeTab = (string) $request->query('tab', 'overview');
        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'overview';
        }

        $staffMembers = User::query()
            ->where('current_agency_id', $agency->id)
            ->where('account_type', AccountType::AgentStaff)
            ->when($primaryAgent !== null, fn (Builder $q): Builder => $q->where('meta->owner_agent_id', $primaryAgent->id))
            ->orderBy('name')
            ->get();

        $staffAgencyRoles = $staffMembers->mapWithKeys(
            fn (User $member): array => [
                $member->id => [
                    'label' => AgencyRoleResolver::labelFor($member, (int) $agency->id),
                    'value' => AgencyRoleResolver::resolve($member, (int) $agency->id)->value,
                    'stored' => AgencyRoleResolver::isStoredRole($member, (int) $agency->id),
                ],
            ],
        );

        $walletSummary = $this->walletService->agencyWalletSummary($agency->id);

        $markupRuleRows = DB::table('markup_rules')
            ->where('agency_id', $agency->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'name', 'rule_type', 'status'])
            ->map(fn (object $row): array => [
                'name' => (string) ($row->name ?: ('Rule #'.$row->id)),
                'type' => (string) ($row->rule_type ?: '—'),
                'status' => (string) ($row->status ?: '—'),
            ]);
        $depositRequests = $primaryAgent !== null
            ? $this->safeDepositRows($primaryAgent->depositRequests()->orderByDesc('id')->limit(20))
            : collect();
        $recentBookings = $this->safeBookingRows(
            DB::table('bookings')
                ->where('agency_id', $agency->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'booking_reference', 'route', 'status', 'created_at'])
        );
        $supportTickets = $this->safeSupportTicketRows(
            DB::table('support_tickets')
                ->where('agency_id', $agency->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'subject', 'status', 'priority', 'created_at'])
        );
        $travelerUsers = User::query()
            ->where('current_agency_id', $agency->id)
            ->where('account_type', AccountType::Customer)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'name', 'email', 'status', 'last_login_at']);
        $auditLogs = $this->auditLogActivityRows((int) $agency->id);

        $overviewRow = $this->buildAgencyRow($agency->loadMissing([
            'agencySetting',
            'agents' => fn ($q) => $q->with(['user', 'wallet'])->orderByDesc('id'),
        ])->loadCount([
            'bookings',
            'users as staff_count' => fn (Builder $q): Builder => $q->where('account_type', AccountType::AgentStaff),
        ]));

        return view(client_view('agencies.show', 'admin'), [
            'agency' => $agency,
            'overview' => $overviewRow,
            'primaryAgent' => $primaryAgent,
            'ownerUser' => $ownerUser,
            'staffMembers' => $staffMembers,
            'staffAgencyRoles' => $staffAgencyRoles,
            'agencyRoleOptions' => AgencyRole::options(),
            'walletSummary' => $walletSummary,
            'markupRuleRows' => $markupRuleRows,
            'depositRequests' => $depositRequests,
            'recentBookings' => $recentBookings,
            'supportTickets' => $supportTickets,
            'travelerUsers' => $travelerUsers,
            'auditLogs' => $auditLogs,
            'activeTab' => $activeTab,
            'agentPermissionLabels' => RolePermissionMatrix::agentStaffPermissionLabels(),
            'agencyPrefix' => AgencyPrefixService::resolvePrefix($agency),
            'suggestedAgencyPrefix' => AgencyPrefixService::suggestPrefix($agency->name, (int) $agency->id),
            'storedAgencyPrefix' => AgencyPrefixService::storedPrefix($agency),
        ]);
    }

    public function updatePrefix(Request $request, Agency $agency): RedirectResponse
    {
        Gate::authorize('view', $agency);

        $validated = $request->validate([
            'code_prefix' => ['required', 'string', 'min:2', 'max:4', 'regex:/^[A-Z0-9]+$/'],
        ]);

        AgencyPrefixService::savePrefix($agency, $validated['code_prefix']);

        return redirect()
            ->route('admin.agencies.show', ['agency' => $agency, 'tab' => 'overview'])
            ->with('status', 'Agency prefix updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAgencyRow(Agency $agency): array
    {
        $primaryAgent = $this->primaryAgentFor($agency);
        $ownerUser = $primaryAgent?->user;
        $walletSummary = $this->walletService->agencyWalletSummary($agency->id);
        $pendingDeposits = (int) AgentDepositRequest::query()
            ->where('agency_id', $agency->id)
            ->where('status', AgentDepositRequestStatus::Submitted)
            ->count();

        $displayName = trim((string) ($agency->agencySetting?->display_name ?? ''));
        if ($displayName === '') {
            $displayName = $agency->name;
        }

        $status = 'inactive';
        $hasActiveAgent = $agency->relationLoaded('agents')
            ? $agency->agents->contains(fn (Agent $agent): bool => (bool) $agent->is_active)
            : $agency->agents()->where('is_active', true)->exists();
        if ($hasActiveAgent) {
            $status = 'active';
        }

        return [
            'id' => $agency->id,
            'name' => $displayName,
            'agency_code' => IdentityDisplay::agencyCodeDisplay($agency),
            'slug' => $agency->slug,
            'owner_name' => $ownerUser?->name ?? '—',
            'owner_email' => $ownerUser?->email ?? '—',
            'owner_user_id' => $ownerUser?->id,
            'primary_agent_id' => $primaryAgent?->id,
            'staff_count' => (int) ($agency->staff_count ?? 0),
            'bookings_count' => (int) ($agency->bookings_count ?? $agency->bookings()->count()),
            'wallet_label' => $walletSummary['currency'].' '.number_format((float) $walletSummary['balance'], 2),
            'deposit_status' => $pendingDeposits > 0 ? $pendingDeposits.' pending' : 'Clear',
            'status' => $status,
            'created_at' => $agency->created_at?->format('Y-m-d') ?? '—',
        ];
    }

    protected function primaryAgentFor(Agency $agency): ?Agent
    {
        $agents = $agency->relationLoaded('agents')
            ? $agency->agents
            : $agency->agents()->with('user')->orderByDesc('id')->get();

        $ownerAgent = $agents
            ->filter(fn (Agent $agent): bool => (bool) $agent->is_active && $agent->user?->account_type === AccountType::Agent)
            ->sortBy('id')
            ->first();

        if ($ownerAgent === null) {
            $ownerAgent = $agents
                ->filter(fn (Agent $agent): bool => $agent->user?->account_type === AccountType::Agent)
                ->sortBy('id')
                ->first();
        }

        return $ownerAgent ?? $agents->sortBy('id')->first();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function safeBookingRows($rows): Collection
    {
        return collect($rows)->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'booking_reference' => (string) ($row->booking_reference ?: ('#'.$row->id)),
            'route' => (string) ($row->route ?: '—'),
            'status' => (string) ($row->status ?: '—'),
            'created_at' => $row->created_at ? Carbon::parse((string) $row->created_at)->format('Y-m-d H:i') : '—',
        ]);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function safeSupportTicketRows($rows): Collection
    {
        return collect($rows)->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'subject' => (string) ($row->subject ?: ('Ticket #'.$row->id)),
            'status' => (string) ($row->status ?: '—'),
            'priority' => (string) ($row->priority ?: '—'),
            'created_at' => $row->created_at ? Carbon::parse((string) $row->created_at)->format('Y-m-d H:i') : '—',
        ]);
    }

    /**
     * @param  Relation|Builder  $query
     * @return Collection<int, array<string, mixed>>
     */
    protected function safeDepositRows($query): Collection
    {
        return collect($query->get(['id', 'amount', 'status', 'created_at']))
            ->map(function ($deposit): array {
                $status = $deposit->getRawOriginal('status') ?? $deposit->status;
                if ($status instanceof \BackedEnum) {
                    $status = $status->value;
                }

                return [
                    'id' => (int) $deposit->id,
                    'amount' => (float) $deposit->amount,
                    'status' => (string) ($status ?: '—'),
                    'created_at' => $deposit->created_at?->format('Y-m-d H:i') ?? '—',
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function auditLogActivityRows(int $agencyId): Collection
    {
        $columns = ['id', 'action', 'user_id', 'created_at'];
        if (Schema::hasColumn('audit_logs', 'meta')) {
            $columns[] = 'meta';
        } elseif (Schema::hasColumn('audit_logs', 'properties')) {
            $columns[] = 'properties';
        }

        $rows = DB::table('audit_logs')
            ->where('agency_id', $agencyId)
            ->orderByDesc('id')
            ->limit(25)
            ->get($columns);

        $userIds = collect($rows)->pluck('user_id')->filter()->unique()->all();
        $users = $userIds === []
            ? collect()
            : User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        return collect($rows)->map(function (object $row) use ($users): array {
            $user = filled($row->user_id ?? null) ? $users->get((int) $row->user_id) : null;

            return [
                'id' => (int) $row->id,
                'action' => (string) ($row->action ?? ''),
                'actor_label' => $user?->name ?? 'System',
                'actor_code' => ActorIdentifier::forUser($user),
                'created_at' => filled($row->created_at ?? null)
                    ? Carbon::parse((string) $row->created_at)->format('Y-m-d H:i')
                    : '—',
                'details' => $this->auditLogDetailsFromRow($row),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditLogDetailsFromRow(object $row): array
    {
        if (property_exists($row, 'meta') && filled($row->meta)) {
            return $this->decodeAuditLogDetails($row->meta);
        }

        if (property_exists($row, 'properties') && filled($row->properties)) {
            return $this->decodeAuditLogDetails($row->properties);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeAuditLogDetails(mixed $value): array
    {
        if (is_array($value)) {
            $redacted = SensitiveDataRedactor::redact($value);

            return is_array($redacted) ? $redacted : [];
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $redacted = SensitiveDataRedactor::redact($decoded);

        return is_array($redacted) ? $redacted : [];
    }

    protected function countAgenciesByStatus(string $status): int
    {
        return Agency::query()
            ->with(['agents.user'])
            ->get()
            ->filter(fn (Agency $agency): bool => $this->buildAgencyRow($agency)['status'] === $status)
            ->count();
    }
}
