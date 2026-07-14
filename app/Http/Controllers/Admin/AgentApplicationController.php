<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Services\Agencies\AgencyReconciliationService;
use App\Services\Agents\AgentApplicationOnboardingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentApplicationController extends Controller
{
    public function __construct(
        protected AgentApplicationOnboardingService $onboardingService,
        protected AgencyReconciliationService $reconciliationService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('platform.admin');

        return view(client_view('agent-applications.index', 'admin'), $this->buildApplicationsListing($request));
    }

    public function data(Request $request): JsonResponse
    {
        Gate::authorize('platform.admin');

        $payload = $this->buildApplicationsListing($request);
        $applications = $payload['applications'];

        $tableHtml = view('dashboard.admin.partials.agent-applications-table-body', $payload)->render();
        $paginationHtml = $applications->hasPages()
            ? '<div class="card-footer">'.$applications->links()->render().'</div>'
            : '';

        return response()->json([
            'table_html' => $tableHtml,
            'pagination_html' => $paginationHtml,
            'listed_count' => $applications->count(),
            'total_count' => $applications->total(),
            'has_filters_applied' => (bool) $payload['hasFilters'],
            'current_page' => $applications->currentPage(),
            'last_page' => $applications->lastPage(),
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        Gate::authorize('platform.admin');

        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $applications = AgentApplication::query()
            ->where(function (Builder $inner) use ($q): void {
                $inner->where('first_name', 'like', '%'.$q.'%')
                    ->orWhere('last_name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('mobile', 'like', '%'.$q.'%')
                    ->orWhere('company_name', 'like', '%'.$q.'%');
            })
            ->latest('id')
            ->limit(10)
            ->get();

        $suggestions = $applications->map(function (AgentApplication $application): array {
            $name = trim($application->first_name.' '.$application->last_name);
            $company = (string) ($application->company_name ?: '—');
            $location = trim(implode(', ', array_filter([
                (string) ($application->city ?? ''),
                (string) ($application->country ?? ''),
            ])), ', ');

            $secondaryParts = array_values(array_filter([
                $application->email !== '' ? (string) $application->email : null,
                $location !== '' ? $location : null,
            ]));

            return [
                'id' => (int) $application->id,
                'primary_line' => ($name !== '' ? $name : 'Applicant').' — '.$company,
                'secondary_line' => implode(' · ', $secondaryParts),
                'search_value' => (string) ($application->email ?: $name),
                'review_url' => route('admin.agent-applications.show', $application),
            ];
        })->values()->all();

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildApplicationsListing(Request $request): array
    {
        $filters = $this->applicationFilters($request);
        $duplicateEmailKeys = $this->duplicateApplicationEmailKeys();
        $convertedEmailKeys = $this->convertedApplicationEmailKeys();
        $duplicateEmailCounts = $this->duplicateApplicationEmailCounts();

        $applications = $this->applyApplicationFilters(
            AgentApplication::query()->with('reviewer'),
            $filters,
            $duplicateEmailKeys
        )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $hasFilters = ($filters['search'] ?? '') !== ''
            || ($filters['status'] ?? '') !== ''
            || ($filters['submitted_from'] ?? '') !== ''
            || ($filters['submitted_to'] ?? '') !== ''
            || ($filters['city_country'] ?? '') !== ''
            || (bool) ($filters['duplicate_only'] ?? false);

        return [
            'applications' => $applications,
            'filters' => $filters,
            'kpis' => $this->applicationKpis($duplicateEmailKeys, $convertedEmailKeys),
            'duplicateEmailKeys' => $duplicateEmailKeys,
            'convertedEmailKeys' => $convertedEmailKeys,
            'duplicateEmailCounts' => $duplicateEmailCounts,
            'hasFilters' => $hasFilters,
        ];
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('platform.admin');

        $filters = $this->applicationFilters($request);
        $duplicateEmailKeys = $this->duplicateApplicationEmailKeys();
        $convertedEmailKeys = $this->convertedApplicationEmailKeys();

        $applications = $this->applyApplicationFilters(
            AgentApplication::query()->with('reviewer'),
            $filters,
            $duplicateEmailKeys
        )
            ->latest('id')
            ->get();

        $filename = 'agent-applications-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($applications, $duplicateEmailKeys, $convertedEmailKeys): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Applicant',
                'Company',
                'Email',
                'Mobile',
                'City',
                'Country',
                'Status',
                'Submitted at',
                'Reviewed at',
                'Reviewer',
                'Duplicate email',
                'Converted to agent',
            ]);

            foreach ($applications as $application) {
                $emailKey = $this->applicationEmailKey($application);
                fputcsv($out, [
                    trim($application->first_name.' '.$application->last_name),
                    $application->company_name,
                    $application->email,
                    $application->mobile,
                    $application->city,
                    $application->country,
                    $application->status,
                    $application->created_at?->format('Y-m-d H:i'),
                    $application->reviewed_at?->format('Y-m-d H:i'),
                    $application->reviewer?->name,
                    in_array($emailKey, $duplicateEmailKeys, true) ? 'Yes' : 'No',
                    in_array($emailKey, $convertedEmailKeys, true) ? 'Yes' : 'No',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(AgentApplication $application): View
    {
        Gate::authorize('platform.admin');

        $application->load('reviewer');
        $needsRepair = $this->reconciliationService->applicationNeedsRepair($application);

        return view(client_view('agent-applications.show', 'admin'), [
            'application' => $application,
            'needsAgencyRepair' => $needsRepair,
        ]);
    }

    /**
     * @return array<string, string|bool>
     */
    protected function applicationFilters(Request $request): array
    {
        $submittedFrom = $request->date('submitted_from');
        $submittedTo = $request->date('submitted_to');

        return [
            'search' => trim($request->string('search')->toString()),
            'status' => $request->string('status')->toString(),
            'submitted_from' => $submittedFrom?->toDateString() ?? '',
            'submitted_to' => $submittedTo?->toDateString() ?? '',
            'city_country' => trim($request->string('city_country')->toString()),
            'duplicate_only' => $request->boolean('duplicate_only'),
        ];
    }

    /**
     * @param  array<string, string|bool>  $filters
     * @param  array<int, string>  $duplicateEmailKeys
     * @return Builder<AgentApplication>
     */
    protected function applyApplicationFilters(Builder $query, array $filters, array $duplicateEmailKeys): Builder
    {
        $search = (string) ($filters['search'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $cityCountry = (string) ($filters['city_country'] ?? '');
        $submittedFrom = (string) ($filters['submitted_from'] ?? '');
        $submittedTo = (string) ($filters['submitted_to'] ?? '');
        $duplicateOnly = (bool) ($filters['duplicate_only'] ?? false);

        return $query
            ->when($status !== '', fn (Builder $q): Builder => $q->where('status', $status))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $inner) use ($search): void {
                    $inner->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('mobile', 'like', '%'.$search.'%')
                        ->orWhere('company_name', 'like', '%'.$search.'%');
                });
            })
            ->when($cityCountry !== '', function (Builder $q) use ($cityCountry): void {
                $q->where(function (Builder $inner) use ($cityCountry): void {
                    $inner->where('city', 'like', '%'.$cityCountry.'%')
                        ->orWhere('country', 'like', '%'.$cityCountry.'%');
                });
            })
            ->when($submittedFrom !== '', fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $submittedFrom))
            ->when($submittedTo !== '', fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $submittedTo))
            ->when($duplicateOnly, function (Builder $q) use ($duplicateEmailKeys): void {
                if ($duplicateEmailKeys === []) {
                    $q->whereRaw('1 = 0');

                    return;
                }

                $q->whereIn(DB::raw('LOWER(email)'), $duplicateEmailKeys);
            });
    }

    /**
     * @return array<string, int>
     */
    protected function applicationKpis(array $duplicateEmailKeys, array $convertedEmailKeys): array
    {
        return [
            'total' => AgentApplication::query()->count(),
            'pending' => AgentApplication::query()->where('status', 'pending')->count(),
            'approved' => AgentApplication::query()->where('status', 'approved')->count(),
            'rejected' => AgentApplication::query()->where('status', 'rejected')->count(),
            'converted' => AgentApplication::query()
                ->whereIn(DB::raw('LOWER(email)'), $convertedEmailKeys === [] ? ['__none__'] : $convertedEmailKeys)
                ->count(),
            'duplicates' => AgentApplication::query()
                ->whereIn(DB::raw('LOWER(email)'), $duplicateEmailKeys === [] ? ['__none__'] : $duplicateEmailKeys)
                ->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function duplicateApplicationEmailKeys(): array
    {
        return AgentApplication::query()
            ->selectRaw('LOWER(email) as email_key')
            ->groupBy('email_key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email_key')
            ->filter()
            ->map(fn (string $email): string => strtolower($email))
            ->values()
            ->all();
    }

    /**
     * Map of lower-cased email → number of applications submitted with that
     * email. Used to surface "X applications from this email" in the preview
     * panel and the table flag tooltip.
     *
     * @return array<string, int>
     */
    protected function duplicateApplicationEmailCounts(): array
    {
        return AgentApplication::query()
            ->selectRaw('LOWER(email) as email_key, COUNT(*) as total')
            ->groupBy('email_key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('total', 'email_key')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function convertedApplicationEmailKeys(): array
    {
        return Agent::query()
            ->with('user:id,email')
            ->whereHas('user')
            ->get()
            ->pluck('user.email')
            ->filter()
            ->map(fn (string $email): string => strtolower($email))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AgentApplication>  $visibleApplications
     * @param  array<int, string>  $duplicateEmailKeys
     * @param  array<int, string>  $convertedEmailKeys
     * @param  array<string, int>  $duplicateEmailCounts
     */
    protected function selectedApplication(
        Request $request,
        $visibleApplications,
        array $duplicateEmailKeys,
        array $convertedEmailKeys,
        array $duplicateEmailCounts = []
    ): ?AgentApplication {
        $preview = $request->integer('preview');
        $selected = null;

        if ($preview > 0) {
            $selected = AgentApplication::query()->with('reviewer')->find($preview);
        }

        $selected ??= $visibleApplications->first();

        if ($selected instanceof AgentApplication) {
            $emailKey = $this->applicationEmailKey($selected);
            $selected->setAttribute('is_duplicate_email', in_array($emailKey, $duplicateEmailKeys, true));
            $selected->setAttribute('is_converted_to_agent', in_array($emailKey, $convertedEmailKeys, true));
            $selected->setAttribute('duplicate_email_count', (int) ($duplicateEmailCounts[$emailKey] ?? 0));
        }

        return $selected;
    }

    protected function applicationEmailKey(AgentApplication $application): string
    {
        return strtolower((string) $application->email);
    }

    public function approve(Request $request, AgentApplication $application): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $request->validate(['internal_note' => ['nullable', 'string', 'max:2000']]);
        if ($application->status === 'approved') {
            return back()->with('status', 'already-approved');
        }

        try {
            $this->onboardingService->approve(
                $application,
                $request->user(),
                $request->string('internal_note')->toString() ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return back()->with('status', 'application-approved');
    }

    public function reject(Request $request, AgentApplication $application): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $request->validate(['internal_note' => ['nullable', 'string', 'max:2000']]);
        $note = $request->string('internal_note')->toString() ?: null;

        $application->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'internal_note' => $note ?: $application->internal_note,
        ])->save();

        $agency = $this->notificationAgency($request);
        $this->onboardingService->sendRejectionNotification($application, $agency, $note);

        return back()->with('status', 'application-rejected');
    }

    public function needsMoreInfo(Request $request, AgentApplication $application): RedirectResponse
    {
        Gate::authorize('platform.admin');

        $request->validate(['internal_note' => ['nullable', 'string', 'max:2000']]);
        $note = $request->string('internal_note')->toString() ?: null;

        $application->forceFill([
            'status' => 'needs_more_info',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'internal_note' => $note ?: $application->internal_note,
        ])->save();

        $agency = $this->notificationAgency($request);
        $this->onboardingService->sendNeedsMoreInfoNotification($application, $agency, $note);

        return back()->with('status', 'application-needs-info');
    }

    protected function notificationAgency(Request $request): Agency
    {
        return $request->user()->currentAgency
            ?? Agency::query()->where('slug', config('ota.default_agency_slug'))->first()
            ?? Agency::query()->firstOrFail();
    }
}
