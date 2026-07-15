<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\AgentCommissionEntry;
use App\Models\Booking;
use App\Models\StaffProfile;
use App\Models\SupplierConnection;
use App\Models\SupplierDiagnosticLog;
use App\Services\Reports\BookingReportService;
use App\Support\Access\RolePermissionMatrix;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSectionController extends Controller
{
    public function __construct(
        protected BookingReportService $bookingReportService,
    ) {}

    public function agents(Request $request): View
    {
        Gate::authorize('viewAny', Agent::class);

        $payload = $this->buildAgentsPayload($request);

        return view(client_view('agents', 'admin'), $payload);
    }

    /**
     * AJAX preview endpoint — returns rendered HTML for the agent mini-profile
     * panel so the Agents page can swap previews without a full reload.
     * Mirrors the bookings preview pattern (admin.bookings.preview).
     */
    public function agentPreview(Agent $agent): JsonResponse
    {
        Gate::authorize('view', $agent);

        $agent->loadMissing(['agency', 'user', 'bookings.fareBreakdown', 'commissionEntries']);
        $row = $this->buildAgentRow($agent);

        $html = view('dashboard.admin.partials.agent-preview-body', ['a' => $row])->render();

        return response()->json([
            'agent_id' => (int) $row['id'],
            'agent_code' => (string) $row['agent_code'],
            'preview_url' => route('admin.agents', ['preview' => $row['id']]),
            'html' => $html,
        ]);
    }

    /**
     * AJAX filter swap — same payload shape as the page render but only
     * what the table + preview need. Lets the agents page refresh in place
     * when filters/search change, no full reload, no flash of empty layout.
     *
     * Response shape (Phase 23B.7.1):
     *   - rows_html / preview_html : drop-in HTML for the live UI swap.
     *   - rows[]                   : safe data projection of each filtered
     *                                agent (agent_code, agency_name, status,
     *                                commission_rate, monthly_sales, …).
     *                                Never includes secrets or passenger PII.
     *   - selected_agent           : safe object for the currently focused
     *                                agent (or null when the list is empty).
     *   - counts                   : summary KPIs (total, active, inactive,
     *                                pending_commission, monthly_sales).
     *   - pagination               : current_page/per_page/total/last_page/
     *                                from/to. We don't paginate today, so
     *                                everything maps to a single page.
     */
    public function agentsData(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Agent::class);

        $payload = $this->buildAgentsPayload($request);

        $hasUserFilters = array_filter([
            $payload['filters']['search'] ?? '',
            $payload['filters']['city'] ?? '',
            $payload['filters']['status'] ?? '',
            $payload['filters']['commission_filter'] ?? '',
            $payload['filters']['sales_from'] ?? '',
            $payload['filters']['sales_to'] ?? '',
            $payload['filters']['created_from'] ?? '',
            $payload['filters']['created_to'] ?? '',
        ], static fn ($v): bool => $v !== '' && $v !== null) !== [];
        $hasNonDefaultQueue = ($payload['filters']['queue'] ?? 'all') !== 'all';

        $rowsHtml = view('dashboard.admin.partials.agents-table-rows', [
            'agents' => $payload['agents'],
            'a' => $payload['selectedAgent'],
            'totalAgents' => $payload['agentsTotalCount'],
            'hasFilters' => $hasUserFilters || $hasNonDefaultQueue,
        ])->render();

        $previewHtml = view('dashboard.admin.partials.agent-preview-body', [
            'a' => $payload['selectedAgent'],
        ])->render();

        $listed = is_countable($payload['agents']) ? count($payload['agents']) : 0;
        $total = (int) $payload['agentsTotalCount'];
        $a = $payload['selectedAgent'];

        $rowsData = collect($payload['agents'])
            ->map(fn (array $row): array => $this->projectAgentRowForApi($row))
            ->values()
            ->all();

        $kpis = $payload['kpis'] ?? [];

        return response()->json([
            'rows_html' => $rowsHtml,
            'preview_html' => $previewHtml,
            'rows' => $rowsData,
            'selected_agent' => $a ? $this->projectAgentRowForApi($a, withPreviewExtras: true) : null,
            'counts' => [
                'total' => (int) ($kpis['total'] ?? $listed),
                'active' => (int) ($kpis['active'] ?? 0),
                'inactive' => (int) ($kpis['inactive'] ?? 0),
                'pending_commission' => (float) ($kpis['commission_pending_total'] ?? 0),
                'monthly_sales' => (float) ($kpis['monthly_sales'] ?? 0),
            ],
            'pagination' => [
                'current_page' => 1,
                'per_page' => max($listed, 1),
                'total' => $listed,
                'last_page' => 1,
                'from' => $listed > 0 ? 1 : 0,
                'to' => $listed,
            ],
            'listed_count' => $listed,
            'total_count' => $total,
            'has_filters_applied' => $hasUserFilters,
            'selected_agent_id' => $a ? (int) $a['id'] : null,
            'selected_agent_code' => $a ? (string) $a['agent_code'] : null,
            'queue_label' => $payload['queueTabs'][$payload['filters']['queue']] ?? 'Agents',
        ]);
    }

    /**
     * Project a buildAgentRow() payload down to the documented public API
     * shape used by /admin/agents/data and /admin/agents/search. Strictly a
     * whitelist — no password/token/PII fields.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function projectAgentRowForApi(array $row, bool $withPreviewExtras = false): array
    {
        $base = [
            'id' => (int) ($row['id'] ?? 0),
            'agent_code' => (string) ($row['agent_code'] ?? ''),
            'agency_name' => (string) ($row['agency_name'] ?? ''),
            'contact_person' => (string) ($row['contact_person'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'status' => (string) ($row['status'] ?? 'inactive'),
            'commission_rate' => (float) ($row['commission_percent'] ?? 0),
            'monthly_sales' => (float) ($row['monthly_sales'] ?? 0),
            'total_bookings' => (int) ($row['bookings_count'] ?? 0),
        ];

        if (! $withPreviewExtras) {
            return $base;
        }

        return $base + [
            'gross_sales' => (float) ($row['gross_sales'] ?? 0),
            'commission_paid' => (float) ($row['commission_paid'] ?? 0),
            'commission_payable' => (float) ($row['commission_payable'] ?? 0),
            'commission_pending' => (float) ($row['commission_pending'] ?? 0),
            'outstanding_balance' => (float) ($row['outstanding_balance'] ?? 0),
            'onboarded_at' => (string) ($row['onboarded_at'] ?? '—'),
            'last_booking_at' => (string) ($row['last_booking_at'] ?? '—'),
            'last_booking_reference' => $row['last_booking_reference'] ?? null,
            'preview_url' => route('admin.agents', ['preview' => $row['id'] ?? null]),
        ];
    }

    /**
     * Typeahead suggestions for the agents search input. Returns up to 10
     * compact items keyed for the UI (agent code, agency, email, city, status).
     * Sensitive user fields are never projected.
     */
    public function agentsSuggestions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Agent::class);

        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $user = $request->user();

        $query = Agent::query()
            // Eager-load just enough to enrich each suggestion with totals
            // (commission rate, monthly sales, total bookings) without N+1.
            // Bound to 10 results so the join cost is negligible.
            ->with(['agency', 'user', 'bookings.fareBreakdown'])
            ->when(! $user->isPlatformAdmin(), function (Builder $inner) use ($user): void {
                $inner->where('agency_id', $user->current_agency_id);
            })
            ->where(function (Builder $inner) use ($q): void {
                $inner->where('code', 'like', '%'.$q.'%')
                    ->orWhereHas('agency', fn (Builder $b): Builder => $b->where('name', 'like', '%'.$q.'%'))
                    ->orWhereHas('user', function (Builder $b) use ($q): void {
                        $b->where('name', 'like', '%'.$q.'%')
                            ->orWhere('email', 'like', '%'.$q.'%')
                            ->orWhere('meta->phone', 'like', '%'.$q.'%');
                    });
            })
            ->orderByDesc('id')
            ->limit(10);

        $monthOpen = now()->startOfMonth();

        $suggestions = $query->get()->map(function (Agent $agent) use ($monthOpen): array {
            $code = (string) ($agent->code ?: ('AGT-'.$agent->id));
            $agency = (string) ($agent->agency?->name ?? '—');
            $contact = (string) ($agent->user?->name ?? '—');
            $email = (string) ($agent->user?->email ?? '—');
            $phone = (string) ($agent->user?->meta['phone'] ?? '—');
            $city = (string) ($agent->meta['city'] ?? '');
            $status = $agent->is_active ? 'active' : 'inactive';
            $bookings = $agent->bookings;
            $totalBookings = $bookings->count();
            $monthlySales = (float) $bookings
                ->where('created_at', '>=', $monthOpen)
                ->sum(fn (Booking $b): float => (float) ($b->fareBreakdown?->total ?? 0));

            $secondaryParts = array_values(array_filter([$email !== '—' ? $email : null, $city !== '' ? $city : null, ucfirst($status)]));

            return [
                // Documented Phase 23B.7.1 field names (admin.agents.search):
                'id' => (int) $agent->id,
                'agent_code' => $code,
                'agency_name' => $agency,
                'contact_person' => $contact,
                'email' => $email,
                'phone' => $phone,
                'city' => $city,
                'status' => $status,
                'commission_rate' => (float) ($agent->commission_percent ?? 0),
                'monthly_sales' => $monthlySales,
                'total_bookings' => $totalBookings,
                // Legacy/typeahead helpers — kept so the existing dropdown JS
                // can keep using primary_line/secondary_line without a second
                // server roundtrip.
                'code' => $code,
                'agency' => $agency,
                'primary_line' => $code.' — '.$agency,
                'secondary_line' => implode(' · ', $secondaryParts),
                'preview_url' => route('admin.agents.preview', ['agent' => $agent->id]),
            ];
        })->values()->all();

        return response()->json(['suggestions' => $suggestions]);
    }

    public function agentsExport(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Agent::class);

        $payload = $this->buildAgentsPayload($request);
        $filename = 'agents-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Agent code',
                'Agency',
                'Contact',
                'Email',
                'City',
                'Status',
                'Commission rate',
                'Bookings',
                'Gross sales',
                'Monthly sales',
                'Pending commission',
                'Commission payable',
                'Commission paid',
                'Outstanding balance',
                'Onboarded at',
                'Last booking at',
            ]);

            foreach ($payload['agents'] as $row) {
                fputcsv($out, [
                    $row['agent_code'],
                    $row['agency_name'],
                    $row['contact_person'],
                    $row['email'],
                    $row['city'],
                    $row['status'],
                    number_format((float) $row['commission_percent'], 2, '.', ''),
                    (int) $row['bookings_count'],
                    (float) $row['gross_sales'],
                    (float) $row['monthly_sales'],
                    (float) $row['commission_pending'],
                    (float) $row['commission_payable'],
                    (float) $row['commission_paid'],
                    (float) $row['outstanding_balance'],
                    $row['onboarded_at'],
                    $row['last_booking_at'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Build the canonical agent row payload used by the list view, the CSV
     * export, and the AJAX preview endpoint. Centralising this here keeps the
     * three surfaces in sync.
     *
     * @return array<string, mixed>
     */
    protected function buildAgentRow(Agent $agent): array
    {
        $bookings = $agent->bookings;
        $monthOpen = now()->startOfMonth();

        $monthlySales = (float) $bookings
            ->where('created_at', '>=', $monthOpen)
            ->sum(fn (Booking $booking): float => (float) ($booking->fareBreakdown?->total ?? 0));
        $grossSales = (float) $bookings
            ->sum(fn (Booking $booking): float => (float) ($booking->fareBreakdown?->total ?? 0));

        $entries = $agent->commissionEntries;
        $commissionPaid = (float) $entries->where('status', 'paid')->sum('commission_amount');
        $commissionPayable = (float) $entries->where('status', 'approved')->sum('commission_amount');
        $commissionPending = (float) $entries->where('status', 'pending')->sum('commission_amount');
        $outstanding = $commissionPayable + $commissionPending;
        $bookingsCount = $bookings->count();
        $lastBooking = $bookings->sortByDesc('created_at')->first();

        return [
            'id' => $agent->id,
            'user_id' => $agent->user_id,
            'agent_code' => $agent->code ?: ('AGT-'.$agent->id),
            'agency_name' => $agent->agency?->name ?? '—',
            'contact_person' => $agent->user?->name ?? '—',
            'email' => $agent->user?->email ?? '—',
            'phone' => (string) ($agent->user?->meta['phone'] ?? '—'),
            'city' => (string) ($agent->meta['city'] ?? '—'),
            'commission_percent' => (float) ($agent->commission_percent ?? 0),
            'commission_plan' => (string) ($agent->meta['commission_plan'] ?? (($agent->commission_percent ?? 0).'%')),
            'bookings_count' => $bookingsCount,
            'monthly_sales' => $monthlySales,
            'gross_sales' => $grossSales,
            'commission_paid' => $commissionPaid,
            'commission_payable' => $commissionPayable,
            'commission_pending' => $commissionPending,
            'outstanding_balance' => $outstanding,
            'status' => $agent->is_active ? 'active' : 'inactive',
            'commission_entries_count' => $entries->count(),
            'onboarded_at' => $agent->created_at?->format('Y-m-d') ?? '—',
            'onboarded_human' => $agent->created_at?->diffForHumans() ?? '—',
            'last_booking_at' => $lastBooking?->created_at?->format('Y-m-d') ?? '—',
            'last_booking_human' => $lastBooking?->created_at?->diffForHumans() ?? 'No bookings yet',
            'last_booking_reference' => $lastBooking?->booking_reference,
            'recent_bookings' => $bookings->sortByDesc('created_at')->take(5)->map(fn (Booking $booking): array => [
                'reference' => (string) ($booking->booking_reference ?? ('#'.$booking->id)),
                'route' => (string) ($booking->route ?? '—'),
                'amount' => (float) ($booking->fareBreakdown?->total ?? 0),
                'status' => $booking->status->value,
            ])->values()->all(),
            'notes' => (string) ($agent->meta['notes'] ?? 'No notes added yet.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAgentsPayload(Request $request): array
    {
        $agentsQuery = Agent::query()
            ->with(['agency', 'user', 'bookings.fareBreakdown', 'commissionEntries'])
            ->when(! $request->user()->isPlatformAdmin(), function (Builder $query) use ($request): void {
                $query->where('agency_id', $request->user()->current_agency_id);
            });

        $search = $request->string('search')->toString();
        $city = $request->string('city')->toString();
        $status = $request->string('status')->toString();
        $commissionFilter = $request->string('commission_filter')->toString();
        $salesFrom = $request->input('sales_from');
        $salesTo = $request->input('sales_to');
        $createdFrom = $request->date('created_from');
        $createdTo = $request->date('created_to');
        $queue = $request->string('queue')->toString() ?: 'all';
        $allowedQueues = ['all', 'active', 'inactive', 'with_balance', 'recent_onboards'];
        if (! in_array($queue, $allowedQueues, true)) {
            $queue = 'all';
        }

        if ($search !== '') {
            $agentsQuery->where(function (Builder $query) use ($search): void {
                $query->where('code', 'like', '%'.$search.'%')
                    ->orWhereHas('agency', fn (Builder $q): Builder => $q->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('user', function (Builder $q) use ($search): void {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('meta->phone', 'like', '%'.$search.'%');
                    });
            });
        }
        if ($city !== '') {
            $agentsQuery->where('meta->city', $city);
        }
        if ($status !== '') {
            $agentsQuery->where('is_active', $status === 'active');
        }
        if ($createdFrom !== null) {
            $agentsQuery->whereDate('created_at', '>=', $createdFrom->toDateString());
        }
        if ($createdTo !== null) {
            $agentsQuery->whereDate('created_at', '<=', $createdTo->toDateString());
        }

        /** @var Collection<int, Agent> $agents */
        $agents = $agentsQuery->orderByDesc('id')->get();
        $agentRows = $agents->map(fn (Agent $agent): array => $this->buildAgentRow($agent))
            ->filter(function (array $row) use ($commissionFilter, $salesFrom, $salesTo): bool {
                $commissionRate = (float) $row['commission_percent'];
                $grossSales = (float) $row['gross_sales'];

                if ($commissionFilter === 'zero' && $commissionRate !== 0.0) {
                    return false;
                }
                if ($commissionFilter === 'below_5' && ! ($commissionRate > 0.0 && $commissionRate < 5.0)) {
                    return false;
                }
                if ($commissionFilter === '5_to_10' && ! ($commissionRate >= 5.0 && $commissionRate <= 10.0)) {
                    return false;
                }
                if ($commissionFilter === 'above_10' && ! ($commissionRate > 10.0)) {
                    return false;
                }
                if (is_numeric($salesFrom) && $grossSales < (float) $salesFrom) {
                    return false;
                }
                if (is_numeric($salesTo) && $grossSales > (float) $salesTo) {
                    return false;
                }

                return true;
            })
            ->values();

        $monthOpen = now()->startOfMonth();
        $applicationStats = [
            'pending_applications' => AgentApplication::query()->where('status', 'pending')->count(),
            'approved_this_month' => AgentApplication::query()
                ->where('status', 'approved')
                ->where('reviewed_at', '>=', $monthOpen)
                ->count(),
            'rejected_this_month' => AgentApplication::query()
                ->where('status', 'rejected')
                ->where('reviewed_at', '>=', $monthOpen)
                ->count(),
        ];

        $kpis = [
            'total' => $agentRows->count(),
            'active' => $agentRows->where('status', 'active')->count(),
            'inactive' => $agentRows->where('status', 'inactive')->count(),
            'monthly_sales' => (float) $agentRows->sum('monthly_sales'),
            'gross_sales' => (float) $agentRows->sum('gross_sales'),
            'outstanding' => (float) $agentRows->sum('outstanding_balance'),
            'commission_paid_total' => (float) $agentRows->sum('commission_paid'),
            'commission_payable_total' => (float) $agentRows->sum('commission_payable'),
            'commission_pending_total' => (float) $agentRows->sum('commission_pending'),
            'with_balance' => $agentRows->filter(fn (array $row): bool => (float) $row['outstanding_balance'] > 0)->count(),
            'recent_onboards' => $agentRows->filter(fn (array $row): bool => $row['onboarded_at'] !== '—' && $row['onboarded_at'] >= now()->subDays(30)->toDateString())->count(),
            ...$applicationStats,
        ];
        $cities = $agentRows->pluck('city')->filter(fn (string $cityValue): bool => $cityValue !== '—')->unique()->sort()->values();

        $filteredRows = match ($queue) {
            'active' => $agentRows->where('status', 'active')->values(),
            'inactive' => $agentRows->where('status', 'inactive')->values(),
            'with_balance' => $agentRows->filter(fn (array $row): bool => (float) $row['outstanding_balance'] > 0)->values(),
            'recent_onboards' => $agentRows->filter(fn (array $row): bool => $row['onboarded_at'] !== '—' && $row['onboarded_at'] >= now()->subDays(30)->toDateString())->values(),
            default => $agentRows,
        };

        $preview = $request->string('preview')->toString();
        $selectedAgent = $filteredRows->first(fn (array $row): bool => (string) $row['id'] === $preview || (string) $row['agent_code'] === $preview)
            ?? $agentRows->first(fn (array $row): bool => (string) $row['id'] === $preview || (string) $row['agent_code'] === $preview)
            ?? $filteredRows->first()
            ?? $agentRows->first();

        return [
            'agents' => $filteredRows,
            'agentsTotalCount' => $agentRows->count(),
            'kpis' => $kpis,
            'cities' => $cities,
            'selectedAgent' => $selectedAgent,
            'previewCode' => $preview,
            'activeQueue' => $queue,
            'queueTabs' => [
                'all' => 'All agents',
                'active' => 'Active',
                'inactive' => 'Inactive',
                'with_balance' => 'With balance',
                'recent_onboards' => 'Recently onboarded',
            ],
            'filters' => [
                'search' => $search,
                'city' => $city,
                'status' => $status,
                'commission_filter' => $commissionFilter,
                'sales_from' => is_numeric($salesFrom) ? (string) $salesFrom : '',
                'sales_to' => is_numeric($salesTo) ? (string) $salesTo : '',
                'created_from' => $createdFrom?->toDateString() ?? '',
                'created_to' => $createdTo?->toDateString() ?? '',
                'queue' => $queue,
            ],
        ];
    }

    public function staff(Request $request): View
    {
        Gate::authorize('viewAny', StaffProfile::class);

        $staffQuery = StaffProfile::query()
            ->with(['agency', 'user'])
            ->when(! $request->user()->isPlatformAdmin(), function (Builder $query) use ($request): void {
                $query->where('agency_id', $request->user()->current_agency_id);
            });

        $search = $request->string('search')->toString();
        $department = $request->string('department')->toString();
        $status = $request->string('status')->toString();

        if ($search !== '') {
            $staffQuery->where(function (Builder $query) use ($search): void {
                $query->where('job_title', 'like', '%'.$search.'%')
                    ->orWhere('department', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $q) use ($search): void {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }
        if ($department !== '') {
            $staffQuery->where('department', $department);
        }
        if ($status !== '') {
            $staffQuery->whereHas('user', fn (Builder $query): Builder => $query->where('status', $status));
        }

        /** @var Collection<int, StaffProfile> $staffProfiles */
        $staffProfiles = $staffQuery->orderByDesc('id')->get();
        $staffRows = $staffProfiles->map(function (StaffProfile $profile): array {
            $assignedBookings = Booking::query()->where('assigned_staff_id', $profile->user_id)->count();
            $recentBookings = Booking::query()
                ->where('assigned_staff_id', $profile->user_id)
                ->latest('id')
                ->limit(5)
                ->get(['id', 'booking_reference', 'route', 'status']);

            $status = $profile->user?->status?->value ?? UserAccountStatus::Inactive->value;

            return [
                'id' => $profile->id,
                'user_id' => $profile->user_id,
                'staff_code' => 'STF-'.$profile->id,
                'name' => $profile->user?->name ?? '—',
                'email' => $profile->user?->email ?? '—',
                'job_title' => (string) ($profile->job_title ?? '—'),
                'department' => (string) ($profile->department ?? 'General'),
                'assigned_bookings' => $assignedBookings,
                'status' => $status,
                'last_login_at' => $profile->user?->last_login_at?->format('Y-m-d H:i') ?? 'Never',
                'recent_bookings' => $recentBookings->map(fn (Booking $booking): array => [
                    'reference' => (string) ($booking->booking_reference ?? ('#'.$booking->id)),
                    'route' => (string) ($booking->route ?? '—'),
                    'status' => $booking->status->value,
                ])->all(),
            ];
        })->values();

        $kpis = [
            'total' => $staffRows->count(),
            'active' => $staffRows->where('status', UserAccountStatus::Active->value)->count(),
            'inactive' => $staffRows->whereIn('status', [UserAccountStatus::Inactive->value, UserAccountStatus::Suspended->value])->count(),
            'assigned_bookings' => (int) $staffRows->sum('assigned_bookings'),
        ];

        $departments = $staffRows->pluck('department')->unique()->sort()->values();
        $preview = $request->string('preview')->toString();
        $selectedStaff = $staffRows->first(fn (array $row): bool => (string) $row['id'] === $preview || (string) $row['staff_code'] === $preview)
            ?? $staffRows->first();

        return view('dashboard.admin.staff', [
            'staff' => $staffRows,
            'kpis' => $kpis,
            'departments' => $departments,
            'selectedStaff' => $selectedStaff,
            'previewCode' => $preview,
            'filters' => [
                'search' => $search,
                'department' => $department,
                'status' => $status,
            ],
        ]);
    }

    public function apiSettings(): RedirectResponse
    {
        Gate::authorize('viewAny', SupplierConnection::class);

        return redirect()->route('admin.api-settings');
    }

    public function rolesPermissions(): View
    {
        Gate::authorize('platform.admin');

        return view('dashboard.admin.roles-permissions', [
            'accountTypes' => array_map(
                fn (AccountType $type): array => [
                    'key' => $type->value,
                    'label' => match ($type) {
                        AccountType::PlatformAdmin => 'Platform admin — full platform access',
                        AccountType::AgencyAdmin => 'Agency admin — legacy (disabled)',
                        AccountType::Staff => 'Staff — permission-based / legacy mode',
                        AccountType::Agent => 'Agent — agency owner',
                        AccountType::AgentStaff => 'Agent staff — permission-based',
                        AccountType::Customer => 'Customer — own portal',
                    },
                ],
                AccountType::cases(),
            ),
            'matrix' => RolePermissionMatrix::areas(),
        ]);
    }

    public function reports(Request $request): View
    {
        Gate::authorize('viewPlatformReports', Booking::class);
        $report = $this->bookingReportService->build($request->user(), $request);
        $user = $request->user();
        $commissionQuery = AgentCommissionEntry::query();
        if (! $user->isPlatformAdmin() && $user->current_agency_id !== null) {
            $commissionQuery->where('agency_id', $user->current_agency_id);
        }
        $commissionTotals = [
            'approved' => (float) (clone $commissionQuery)->where('status', 'approved')->sum('commission_amount'),
            'paid' => (float) (clone $commissionQuery)->where('status', 'paid')->sum('commission_amount'),
        ];

        $tab = $request->string('tab')->toString();
        $allowedTabs = ['overview', 'sales', 'payments', 'bookings', 'suppliers', 'agents', 'routes', 'refunds', 'documents', 'exports'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        return view(client_view('reports', 'admin'), array_merge($report, [
            'commissionTotals' => $commissionTotals,
            'activeTab' => $tab,
            'allowedTabs' => $allowedTabs,
            'bookingStatusOptions' => array_map(fn ($status): string => $status->value, BookingStatus::cases()),
            'reportsTitle' => 'Platform Reports',
            'reportsExportRoute' => 'admin.reports.export',
            'reportsIndexRoute' => 'admin.reports',
            'reportsSupplierDiagnosticsRoute' => 'admin.reports.supplier-diagnostics',
            'reportsScope' => 'platform',
        ]));
    }

    /**
     * Stream a sanitized CSV export. Only safe, non-sensitive columns are
     * exported. Cross-agency scoping is preserved by the report service.
     */
    public function reportsExport(Request $request, string $type): StreamedResponse
    {
        Gate::authorize('exportPlatformReports', Booking::class);

        $allowedTypes = ['sales', 'payments', 'bookings', 'agents', 'refunds', 'supplier_diagnostics', 'documents'];
        if (! in_array($type, $allowedTypes, true)) {
            abort(404);
        }

        $report = $this->bookingReportService->build($request->user(), $request);
        $rows = $this->bookingReportService->exportRows($type, $report);

        $filename = sprintf('reports-%s-%s.csv', $type, now()->format('Ymd-His'));
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ];

        return response()->stream(static function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<int, string>>
     */
    protected function buildExportRows(string $type, array $report): array
    {
        switch ($type) {
            case 'sales':
                $out = [['Period', 'Bookings', 'Gross sales', 'Base fare', 'Markup', 'Service fee', 'Net revenue', 'Average ticket']];
                foreach ($report['salesByPeriod'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['period'] ?? ''),
                        (string) (int) ($row['bookings'] ?? 0),
                        (string) (int) ($row['gross_sales'] ?? 0),
                        (string) (int) ($row['base_fare'] ?? 0),
                        (string) (int) ($row['markup'] ?? 0),
                        (string) (int) ($row['service_fee'] ?? 0),
                        (string) (int) ($row['net_revenue'] ?? 0),
                        (string) (int) ($row['average_ticket'] ?? 0),
                    ];
                }

                return $out;
            case 'payments':
                $out = [['Booking', 'Customer', 'Route', 'Total', 'Paid', 'Balance', 'Payment status', 'Method', 'Created']];
                foreach ($report['paymentRows'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['booking_ref'] ?? ''),
                        (string) ($row['customer'] ?? ''),
                        (string) ($row['route'] ?? ''),
                        (string) (int) ($row['total'] ?? 0),
                        (string) (int) ($row['paid'] ?? 0),
                        (string) (int) ($row['balance'] ?? 0),
                        (string) ($row['payment_status'] ?? ''),
                        (string) ($row['method'] ?? ''),
                        (string) ($row['created_at'] ?? ''),
                    ];
                }

                return $out;
            case 'bookings':
                $out = [['Booking', 'Customer', 'Route', 'Travel date', 'Status', 'Payment', 'Supplier', 'Ticketing', 'Amount']];
                foreach ($report['bookingPipelineRows'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['booking_ref'] ?? ''),
                        (string) ($row['customer'] ?? ''),
                        (string) ($row['route'] ?? ''),
                        (string) ($row['travel_date'] ?? ''),
                        (string) ($row['status'] ?? ''),
                        (string) ($row['payment_status'] ?? ''),
                        (string) ($row['supplier_status'] ?? ''),
                        (string) ($row['ticketing_status'] ?? ''),
                        (string) (int) ($row['amount'] ?? 0),
                    ];
                }

                return $out;
            case 'agents':
                $out = [['Agent code', 'Agent', 'Bookings', 'Gross sales', 'Net revenue', 'Approved commission', 'Paid commission', 'Pending commission']];
                foreach ($report['agentPerformance'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['agent_code'] ?? ''),
                        (string) ($row['agent_name'] ?? ''),
                        (string) (int) ($row['bookings'] ?? 0),
                        (string) (int) ($row['gross_sales'] ?? 0),
                        (string) (int) ($row['net_revenue'] ?? 0),
                        (string) (int) ($row['approved_commission'] ?? 0),
                        (string) (int) ($row['paid_commission'] ?? 0),
                        (string) (int) ($row['pending_commission'] ?? 0),
                    ];
                }

                return $out;
            case 'refunds':
                $out = [['Booking', 'Customer', 'Route', 'Paid amount', 'Refund amount', 'Refund status', 'Cancellation status', 'Created']];
                foreach ($report['refundRows'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['booking_ref'] ?? ''),
                        (string) ($row['customer'] ?? ''),
                        (string) ($row['route'] ?? ''),
                        (string) (int) ($row['paid_amount'] ?? 0),
                        (string) (int) ($row['refund_amount'] ?? 0),
                        (string) ($row['refund_status'] ?? ''),
                        (string) ($row['cancellation_status'] ?? ''),
                        (string) ($row['created_at'] ?? ''),
                    ];
                }

                return $out;
            case 'supplier_diagnostics':
                $out = [['Provider', 'Status', 'Searches', 'Successful', 'Validation failures', 'Offer unavailable', 'Errors', 'PNRs created', 'Tickets issued', 'Last success', 'Last error']];
                foreach ($report['supplierPerformance'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['provider'] ?? ''),
                        (string) ($row['status_label'] ?? ''),
                        (string) (int) ($row['searches'] ?? 0),
                        (string) (int) ($row['successful_searches'] ?? 0),
                        (string) (int) ($row['validation_failures'] ?? 0),
                        (string) (int) ($row['offer_unavailable'] ?? 0),
                        (string) (int) ($row['errors'] ?? 0),
                        (string) (int) ($row['pnr_created'] ?? 0),
                        (string) (int) ($row['ticketing_success'] ?? 0),
                        (string) ($row['last_success_at'] ?? ''),
                        (string) ($row['last_error_at'] ?? ''),
                    ];
                }

                return $out;
            case 'documents':
                $out = [['Booking', 'Document type', 'Status', 'Generated at', 'Sent at']];
                foreach ($report['documentRows'] ?? [] as $row) {
                    $out[] = [
                        (string) ($row['booking_ref'] ?? ''),
                        (string) ($row['document_type'] ?? ''),
                        (string) ($row['status'] ?? ''),
                        (string) ($row['generated_at'] ?? ''),
                        (string) ($row['sent_at'] ?? ''),
                    ];
                }

                return $out;
            default:
                return [['type', 'message'], [$type, 'Unsupported']];
        }
    }

    public function supplierDiagnostics(Request $request): View
    {
        Gate::authorize('viewPlatformReports', Booking::class);

        $filters = [
            'provider' => $request->string('provider')->toString() ?: 'all',
            'action' => $request->string('action')->toString(),
            'status' => $request->string('status')->toString() ?: 'errors',
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
        ];

        $providerOptions = SupplierDiagnosticLog::query()
            ->when(! $request->user()->isPlatformAdmin(), fn (Builder $query): Builder => $query->where('agency_id', $request->user()->current_agency_id))
            ->select('provider')
            ->whereNotNull('provider')
            ->distinct()
            ->orderBy('provider')
            ->pluck('provider')
            ->filter()
            ->values();

        $actionOptions = SupplierDiagnosticLog::query()
            ->when(! $request->user()->isPlatformAdmin(), fn (Builder $query): Builder => $query->where('agency_id', $request->user()->current_agency_id))
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->values();

        $query = SupplierDiagnosticLog::query()
            ->when(! $request->user()->isPlatformAdmin(), fn (Builder $query): Builder => $query->where('agency_id', $request->user()->current_agency_id));

        if ($filters['provider'] !== 'all' && $filters['provider'] !== '') {
            $query->where('provider', $filters['provider']);
        }

        if ($filters['action'] !== '') {
            $query->where('action', $filters['action']);
        }

        if ($filters['status'] === 'errors') {
            $query->whereIn('status', ['failed', 'error']);
        } elseif ($filters['status'] !== 'all' && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $diagnostics = $query
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (SupplierDiagnosticLog $log): array => $this->safeDiagnosticRow($log));

        return view(client_view('supplier-diagnostics', 'admin'), [
            'diagnostics' => $diagnostics,
            'filters' => $filters,
            'providerOptions' => $providerOptions,
            'actionOptions' => $actionOptions,
            'statusOptions' => ['errors', 'failed', 'error', 'ok', 'all'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeDiagnosticRow(SupplierDiagnosticLog $log): array
    {
        $meta = is_array($log->meta) ? $log->meta : [];
        $duffelErrors = collect($meta['duffel_errors'] ?? [])
            ->filter(fn ($error): bool => is_array($error))
            ->map(fn (array $error): array => [
                'code' => $this->sanitizeDiagnosticText((string) ($error['code'] ?? '')),
                'title' => $this->sanitizeDiagnosticText((string) ($error['title'] ?? '')),
                'detail' => $this->sanitizeDiagnosticText((string) ($error['detail'] ?? '')),
                'source_pointer' => $this->sanitizeDiagnosticText((string) ($error['source_pointer'] ?? data_get($error, 'source.pointer', ''))),
            ])
            ->values()
            ->all();

        return [
            'created_at' => $log->created_at?->format('Y-m-d H:i:s') ?? '',
            'provider' => $this->sanitizeDiagnosticText((string) $log->provider),
            'action' => $this->sanitizeDiagnosticText((string) $log->action),
            'status' => $this->sanitizeDiagnosticText((string) $log->status),
            'safe_message' => $this->sanitizeDiagnosticText((string) $log->safe_message),
            'reason_code' => $this->sanitizeDiagnosticText((string) data_get($meta, 'reason_code', '')),
            'error_code' => $this->sanitizeDiagnosticText((string) data_get($meta, 'error_code', '')),
            'http_status' => $this->sanitizeDiagnosticText((string) data_get($meta, 'http_status', '')),
            'endpoint' => $this->sanitizeDiagnosticText((string) data_get($meta, 'endpoint', '')),
            'duffel_errors' => $duffelErrors,
        ];
    }

    protected function sanitizeDiagnosticText(string $value): string
    {
        $sanitized = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $value) ?? $value;
        $sanitized = preg_replace('/duffel_(test|live)_[A-Za-z0-9._\-]+/i', 'duffel_$1_[redacted]', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/authorization\s*[:=]\s*[^,\s;]+/i', '[redacted header]', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(api[_-]?key|access[_-]?token|client[_-]?secret)\s*[:=]\s*[^,\s;]+/i', '$1: [redacted]', $sanitized) ?? $sanitized;

        return Str::limit($sanitized, 500);
    }

    public function branding(): RedirectResponse
    {
        return redirect()->route('admin.settings.branding.edit');
    }

    public function goLiveChecklist(): View
    {
        Gate::authorize('platform.admin');

        $items = collect(config('ota-go-live.items', []))
            ->map(function (array $item): array {
                $note = (string) ($item['note'] ?? '');
                $cleanNote = str_ireplace(
                    ['demo', 'sample', 'placeholder', 'fake'],
                    ['deployment', 'production', 'not configured', ''],
                    $note
                );

                return [
                    'label' => $item['label'] ?? 'Checklist item',
                    'note' => trim(preg_replace('/\s+/', ' ', $cleanNote) ?? ''),
                    'done' => (bool) ($item['done'] ?? false),
                ];
            })
            ->values()
            ->all();

        return view('dashboard.admin.go-live-checklist', [
            'items' => $items,
        ]);
    }
}
