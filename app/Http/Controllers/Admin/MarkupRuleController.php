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

    public function index(Request $request): View|JsonResponse
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

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'rules' => collect($rules->items())->map(fn (MarkupRule $rule) => $this->serializeRule($rule))->values()->all(),
                'kpis' => $kpis,
                'meta' => [
                    'current_page' => $rules->currentPage(),
                    'last_page' => $rules->lastPage(),
                    'per_page' => $rules->perPage(),
                    'total' => $rules->total(),
                ],
                'filters' => $request->only(['type', 'status']),
                'types' => collect(MarkupRuleType::cases())->map(fn ($type) => [
                    'value' => $type->value,
                    'label' => $type->name,
                ])->values()->all(),
                'statuses' => collect(MarkupRuleStatus::cases())->map(fn ($status) => [
                    'value' => $status->value,
                    'label' => $status->name,
                ])->values()->all(),
                'valueTypes' => collect(MarkupValueType::cases())->map(fn ($type) => [
                    'value' => $type->value,
                    'label' => $type->name,
                ])->values()->all(),
            ]);
        }

        return view(client_view('markups.index', 'admin'), [
            'rules' => $rules,
            'kpis' => $kpis,
            'filters' => $request->only(['type', 'status']),
            'types' => MarkupRuleType::cases(),
            'statuses' => MarkupRuleStatus::cases(),
        ]);
    }

    public function create(Request $request): View|JsonResponse
    {
        Gate::authorize('create', MarkupRule::class);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'rule' => $this->serializeRule(new MarkupRule),
                'types' => collect(MarkupRuleType::cases())->map(fn ($type) => [
                    'value' => $type->value,
                    'label' => $type->name,
                ])->values()->all(),
                'valueTypes' => collect(MarkupValueType::cases())->map(fn ($type) => [
                    'value' => $type->value,
                    'label' => $type->name,
                ])->values()->all(),
                'statuses' => collect(MarkupRuleStatus::cases())->map(fn ($status) => [
                    'value' => $status->value,
                    'label' => $status->name,
                ])->values()->all(),
            ]);
        }

        return view('dashboard.admin.markups.create', [
            'rule' => new MarkupRule,
            'types' => MarkupRuleType::cases(),
            'valueTypes' => MarkupValueType::cases(),
            'statuses' => MarkupRuleStatus::cases(),
            'method' => 'POST',
            'action' => route('admin.markups.store'),
        ]);
    }

    public function store(StoreMarkupRuleRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', MarkupRule::class);
        $agencyId = $this->resolveAgencyId($request);

        $rule = MarkupRule::query()->create($this->payload($request) + ['agency_id' => $agencyId]);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Markup rule created.',
                'rule' => $this->serializeRule($rule),
            ]);
        }

        return redirect()->route('admin.markups')->with('status', 'markup-rule-created');
    }

    public function edit(Request $request, MarkupRule $markupRule): View|JsonResponse
    {
        Gate::authorize('view', $markupRule);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'rule' => $this->serializeRule($markupRule),
                'types' => collect(MarkupRuleType::cases())->map(fn ($type) => [
                    'value' => $type->value,
                    'label' => $type->name,
                ])->values()->all(),
                'valueTypes' => collect(MarkupValueType::cases())->map(fn ($type) => [
                    'value' => $type->value,
                    'label' => $type->name,
                ])->values()->all(),
                'statuses' => collect(MarkupRuleStatus::cases())->map(fn ($status) => [
                    'value' => $status->value,
                    'label' => $status->name,
                ])->values()->all(),
            ]);
        }

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
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Markup rule updated.',
                'rule' => $this->serializeRule($markupRule->fresh() ?? $markupRule),
            ]);
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
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Markup rule status updated.',
                'rule' => $this->serializeRule($markupRule->fresh() ?? $markupRule),
            ]);
        }

        return back()->with('status', 'markup-rule-status-updated');
    }

    public function destroy(Request $request, MarkupRule $markupRule): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $markupRule);

        $markupRule->delete();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Markup rule deleted.',
            ]);
        }

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

    /**
     * @return array<string, mixed>
     */
    private function serializeRule(MarkupRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'rule_type' => $rule->rule_type instanceof MarkupRuleType ? $rule->rule_type->value : (string) $rule->rule_type,
            'value' => $rule->value,
            'value_type' => $rule->value_type instanceof MarkupValueType ? $rule->value_type->value : (string) $rule->value_type,
            'priority' => $rule->priority,
            'status' => $rule->status instanceof MarkupRuleStatus ? $rule->status->value : (string) $rule->status,
            'is_active' => (bool) $rule->is_active,
            'applies_to' => $rule->applies_to,
            'starts_at' => optional($rule->starts_at)?->toDateString(),
            'ends_at' => optional($rule->ends_at)?->toDateString(),
            'meta_notes' => is_array($rule->meta) ? (string) ($rule->meta['notes'] ?? '') : '',
            'agency_id' => $rule->agency_id,
        ];
    }
}
