<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMarkupRuleRequest;
use App\Http\Requests\Admin\UpdateMarkupRuleRequest;
use App\Models\MarkupRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MarkupRuleController extends Controller
{
    use RespondsWithBackOfficeJson;
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

    public function lookups(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MarkupRule::class);
        $type = $request->string('type')->toString();
        $q = trim($request->string('q')->toString());
        $items = [];
        if ($type === 'airline') {
            $items = \App\Models\Airline::query()
                ->search($q !== '' ? $q : null)
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'iata_code'])
                ->map(static fn ($row) => [
                    'id' => (string) $row->iata_code,
                    'label' => trim(($row->name ?? '').' ('.$row->iata_code.')'),
                ])
                ->all();
        }
        if ($type === 'agency') {
            $query = \App\Models\Agency::query()->orderBy('name')->limit(20);
            if ($q !== '') {
                $query->where('name', 'like', '%'.$q.'%');
            }
            $items = $query->get(['id', 'name'])
                ->map(static fn ($row) => [
                    'id' => (string) $row->id,
                    'label' => (string) $row->name,
                ])
                ->all();
        }
        if ($type === 'user') {
            $query = \App\Models\User::query()->orderBy('name')->limit(20);
            if ($q !== '') {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%');
                });
            }
            $items = $query->get(['id', 'name', 'email'])
                ->map(static fn ($row) => [
                    'id' => (string) $row->id,
                    'label' => trim($row->name.' · '.$row->email),
                ])
                ->all();
        }

        return $this->backOfficeJson(['ok' => true, 'items' => $items]);
    }

    public function store(StoreMarkupRuleRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', MarkupRule::class);
        $agencyId = $this->resolveAgencyId($request);

        $rule = MarkupRule::query()->create($this->payload($request) + ['agency_id' => $agencyId]);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true, 'markup' => $this->presentRule($rule)]);
        }

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

    public function update(UpdateMarkupRuleRequest $request, MarkupRule $markupRule): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $markupRule);

        $markupRule->update($this->payload($request));

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true, 'markup' => $this->presentRule($markupRule->fresh() ?? $markupRule)]);
        }

        return redirect()->route('admin.markups')->with('status', 'markup-rule-updated');
    }

    public function toggleStatus(Request $request, MarkupRule $markupRule): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $markupRule);

        $next = $markupRule->status === MarkupRuleStatus::Active
            ? MarkupRuleStatus::Inactive
            : MarkupRuleStatus::Active;

        $markupRule->forceFill([
            'status' => $next,
            'is_active' => $next === MarkupRuleStatus::Active,
        ])->save();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true, 'markup' => $this->presentRule($markupRule)]);
        }

        return back()->with('status', 'markup-rule-status-updated');
    }

    public function destroy(Request $request, MarkupRule $markupRule): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $markupRule);

        $markupRule->delete();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson(['ok' => true]);
        }

        return redirect()->route('admin.markups')->with('status', 'markup-rule-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentRule(MarkupRule $rule): array
    {
        return [
            'id' => (string) $rule->id,
            'name' => (string) $rule->name,
            'ruleType' => is_object($rule->rule_type) ? (string) $rule->rule_type->value : (string) $rule->rule_type,
            'value' => (string) $rule->value,
            'valueType' => is_object($rule->value_type) ? (string) $rule->value_type->value : (string) $rule->value_type,
            'priority' => (int) $rule->priority,
            'status' => is_object($rule->status) ? (string) $rule->status->value : (string) $rule->status,
            'appliesTo' => is_array($rule->applies_to) ? $rule->applies_to : [],
        ];
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
        $appliesRaw = $request->input('applies_to');
        $applies = null;
        if (is_array($appliesRaw)) {
            $applies = $appliesRaw;
        } elseif (is_string($appliesRaw) && trim($appliesRaw) !== '') {
            $decoded = json_decode($appliesRaw, true);
            $applies = is_array($decoded) ? $decoded : null;
        }
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
            MarkupRuleType::Supplier->value => array_filter([
                'supplier' => $request->string('supplier_key')->toString() ?: null,
            ]),
            MarkupRuleType::Airline->value => array_filter([
                'airline' => strtoupper($request->string('airline_code')->toString()),
                'flight_number' => $request->string('flight_number')->toString() ?: null,
                'origin' => strtoupper($request->string('origin')->toString()) ?: null,
                'destination' => strtoupper($request->string('destination')->toString()) ?: null,
            ]),
            MarkupRuleType::Route->value => array_filter([
                'origin' => strtoupper($request->string('origin')->toString()),
                'destination' => strtoupper($request->string('destination')->toString()),
                'direction' => $request->string('route_direction')->toString() ?: 'both',
            ]),
            MarkupRuleType::Agent->value => array_filter([
                'agent_id' => $request->string('agent_id')->toString() ?: null,
            ]),
            MarkupRuleType::Cabin->value => array_filter([
                'cabin' => $request->string('cabin')->toString() ?: null,
            ]),
            MarkupRuleType::FareFamily->value => array_filter([
                'fare_family' => $request->string('fare_family')->toString() ?: null,
            ]),
            default => null,
        };
    }
}
