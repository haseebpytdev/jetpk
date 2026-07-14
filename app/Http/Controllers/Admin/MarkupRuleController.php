<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMarkupRuleRequest;
use App\Http\Requests\Admin\UpdateMarkupRuleRequest;
use App\Models\MarkupRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MarkupRuleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MarkupRule::class);
        $query = $this->scopedQuery($request->user());

        if ($request->filled('type')) {
            $query->where('rule_type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $rules = (clone $query)->orderBy('priority')->orderByDesc('created_at')->paginate(20)->withQueryString();

        $kpisBase = $this->scopedQuery($request->user());
        $kpis = [
            'active' => (clone $kpisBase)->where('status', MarkupRuleStatus::Active)->count(),
            'route' => (clone $kpisBase)->where('rule_type', MarkupRuleType::Route)->count(),
            'airline' => (clone $kpisBase)->where('rule_type', MarkupRuleType::Airline)->count(),
            'agent' => (clone $kpisBase)->where('rule_type', MarkupRuleType::Agent)->count(),
        ];

        return view(client_view('markups.index', 'admin'), [
            'rules' => $rules,
            'kpis' => $kpis,
            'filters' => $request->only(['type', 'status']),
            'types' => MarkupRuleType::cases(),
            'statuses' => MarkupRuleStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', MarkupRule::class);

        return view('dashboard.admin.markups.create', [
            'rule' => new MarkupRule,
            'types' => MarkupRuleType::cases(),
            'valueTypes' => MarkupValueType::cases(),
            'statuses' => MarkupRuleStatus::cases(),
            'method' => 'POST',
            'action' => route('admin.markups.store'),
        ]);
    }

    public function store(StoreMarkupRuleRequest $request): RedirectResponse
    {
        Gate::authorize('create', MarkupRule::class);
        $agencyId = $this->resolveAgencyId($request);

        MarkupRule::query()->create($this->payload($request) + ['agency_id' => $agencyId]);

        return redirect()->route('admin.markups')->with('status', 'markup-rule-created');
    }

    public function edit(MarkupRule $markupRule): View
    {
        Gate::authorize('view', $markupRule);

        return view('dashboard.admin.markups.edit', [
            'rule' => $markupRule,
            'types' => MarkupRuleType::cases(),
            'valueTypes' => MarkupValueType::cases(),
            'statuses' => MarkupRuleStatus::cases(),
            'method' => 'PATCH',
            'action' => route('admin.markups.update', $markupRule),
        ]);
    }

    public function update(UpdateMarkupRuleRequest $request, MarkupRule $markupRule): RedirectResponse
    {
        Gate::authorize('update', $markupRule);

        $markupRule->update($this->payload($request));

        return redirect()->route('admin.markups')->with('status', 'markup-rule-updated');
    }

    public function toggleStatus(Request $request, MarkupRule $markupRule): RedirectResponse
    {
        Gate::authorize('update', $markupRule);

        $next = $markupRule->status === MarkupRuleStatus::Active
            ? MarkupRuleStatus::Inactive
            : MarkupRuleStatus::Active;

        $markupRule->forceFill([
            'status' => $next,
            'is_active' => $next === MarkupRuleStatus::Active,
        ])->save();

        return back()->with('status', 'markup-rule-status-updated');
    }

    public function destroy(MarkupRule $markupRule): RedirectResponse
    {
        Gate::authorize('delete', $markupRule);

        $markupRule->delete();

        return redirect()->route('admin.markups')->with('status', 'markup-rule-deleted');
    }

    protected function scopedQuery($user): Builder
    {
        $query = MarkupRule::query();

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        return $query;
    }

    protected function resolveAgencyId(Request $request): int
    {
        if ($request->user()->isPlatformAdmin() && $request->filled('agency_id')) {
            return $request->integer('agency_id');
        }

        $agencyId = $request->user()->current_agency_id;
        abort_if($agencyId === null, 403, 'No agency context assigned.');

        return $agencyId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        $appliesRaw = trim((string) $request->input('applies_to', ''));
        $applies = $appliesRaw !== '' ? json_decode($appliesRaw, true) : null;

        if (! is_array($applies)) {
            $applies = $this->inferAppliesTo($request);
        }

        return [
            'name' => $request->string('name')->toString(),
            'rule_type' => $request->string('rule_type')->toString(),
            'value' => $request->input('value'),
            'value_type' => $request->string('value_type')->toString(),
            'applies_to' => $applies,
            'priority' => $request->integer('priority') ?: 100,
            'status' => $request->string('status')->toString(),
            'starts_at' => $request->input('starts_at') ?: null,
            'ends_at' => $request->input('ends_at') ?: null,
            'meta' => [
                'notes' => $request->string('meta_notes')->toString(),
            ],
            'is_active' => $request->string('status')->toString() === MarkupRuleStatus::Active->value,
            'config' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function inferAppliesTo(Request $request): ?array
    {
        return match ($request->string('rule_type')->toString()) {
            MarkupRuleType::Route->value => ['route' => $request->string('name')->toString()],
            default => null,
        };
    }
}
