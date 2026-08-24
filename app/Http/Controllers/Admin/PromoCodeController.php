<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromoCodeAppliesTo;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoCodeType;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromoCodeRequest;
use App\Http\Requests\Admin\UpdatePromoCodeRequest;
use App\Models\AuditLog;
use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PromoCodeController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        Gate::authorize('viewAny', PromoCode::class);

        $query = $this->scopedQuery($request->user());

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('code', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        $promoCodes = (clone $query)->orderByDesc('created_at')->paginate(20)->withQueryString();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'filters' => $request->only(['status', 'q']),
                'statuses' => array_map(static fn (PromoCodeStatus $s) => $s->value, PromoCodeStatus::cases()),
                'types' => array_map(static fn (PromoCodeType $t) => $t->value, PromoCodeType::cases()),
                'applies_to' => array_map(static fn (PromoCodeAppliesTo $a) => $a->value, PromoCodeAppliesTo::cases()),
                'promo_codes' => collect($promoCodes->items())->map(fn (PromoCode $promo) => $this->presentPromo($promo))->values()->all(),
                'meta' => [
                    'current_page' => $promoCodes->currentPage(),
                    'last_page' => $promoCodes->lastPage(),
                    'total' => $promoCodes->total(),
                ],
            ]);
        }

        if ($request->expectsJson() === false) {
            return redirect()->to('/admin/dashboard/settings/promo-codes');
        }

        return view('dashboard.admin.promo-codes.index', [
            'promoCodes' => $promoCodes,
            'filters' => $request->only(['status', 'q']),
            'statuses' => PromoCodeStatus::cases(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        Gate::authorize('create', PromoCode::class);

        return redirect()->to('/admin/dashboard/settings/promo-codes');
    }

    public function store(StorePromoCodeRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', PromoCode::class);

        $promo = PromoCode::query()->create($this->payload($request) + [
            'agency_id' => $this->resolveAgencyId($request),
            'created_by' => $request->user()?->id,
        ]);

        $this->writeAudit($request, $promo, 'promo_code.created');

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'promo-code-created',
                'promo_code' => $this->presentPromo($promo),
            ]);
        }

        return redirect()->route('admin.promo-codes.index')->with('status', 'promo-code-created');
    }

    public function edit(PromoCode $promoCode): View|RedirectResponse
    {
        Gate::authorize('view', $promoCode);

        return redirect()->to('/admin/dashboard/settings/promo-codes?edit='.$promoCode->id);
    }

    public function update(UpdatePromoCodeRequest $request, PromoCode $promoCode): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $promoCode);

        $promoCode->update($this->payload($request) + [
            'updated_by' => $request->user()?->id,
        ]);

        $this->writeAudit($request, $promoCode->fresh(), 'promo_code.updated');

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'promo-code-updated',
                'promo_code' => $this->presentPromo($promoCode->fresh()),
            ]);
        }

        return redirect()->route('admin.promo-codes.index')->with('status', 'promo-code-updated');
    }

    public function toggleStatus(Request $request, PromoCode $promoCode): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $promoCode);

        $next = $promoCode->status === PromoCodeStatus::Active
            ? PromoCodeStatus::Inactive
            : PromoCodeStatus::Active;

        $promoCode->update(['status' => $next]);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'promo-code-status-updated',
                'promo_code' => $this->presentPromo($promoCode->fresh()),
            ]);
        }

        return back()->with('status', 'promo-code-status-updated');
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentPromo(PromoCode $promo): array
    {
        return [
            'id' => (string) $promo->id,
            'code' => (string) $promo->code,
            'name' => (string) ($promo->name ?? ''),
            'type' => (string) ($promo->type?->value ?? $promo->type ?? ''),
            'value' => (float) $promo->value,
            'currency' => $promo->currency,
            'min_amount' => $promo->min_amount !== null ? (float) $promo->min_amount : null,
            'max_discount' => $promo->max_discount !== null ? (float) $promo->max_discount : null,
            'starts_at' => $promo->starts_at?->toIso8601String(),
            'ends_at' => $promo->ends_at?->toIso8601String(),
            'usage_limit' => $promo->usage_limit,
            'per_user_limit' => $promo->per_user_limit,
            'applies_to' => (string) ($promo->applies_to?->value ?? $promo->applies_to ?? ''),
            'status' => (string) ($promo->status?->value ?? $promo->status ?? ''),
            'internal_testing_only' => (bool) $promo->internal_testing_only,
        ];
    }

    protected function scopedQuery($user): Builder
    {
        $query = PromoCode::query();

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
        return [
            'code' => strtoupper($request->string('code')->toString()),
            'name' => $request->string('name')->toString() ?: null,
            'type' => $request->string('type')->toString(),
            'value' => $request->input('value'),
            'currency' => $request->filled('currency') ? strtoupper($request->string('currency')->toString()) : null,
            'min_amount' => $request->input('min_amount') ?: null,
            'max_discount' => $request->input('max_discount') ?: null,
            'starts_at' => $request->input('starts_at') ?: null,
            'ends_at' => $request->input('ends_at') ?: null,
            'usage_limit' => $request->input('usage_limit') ?: null,
            'per_user_limit' => $request->input('per_user_limit') ?: null,
            'applies_to' => $request->string('applies_to')->toString(),
            'status' => $request->string('status')->toString(),
            'internal_testing_only' => $request->boolean('internal_testing_only'),
        ];
    }

    protected function writeAudit(Request $request, PromoCode $promo, string $action): void
    {
        try {
            AuditLog::query()->create([
                'agency_id' => $promo->agency_id,
                'user_id' => $request->user()?->id,
                'action' => $action,
                'auditable_type' => PromoCode::class,
                'auditable_id' => $promo->id,
                'properties' => [
                    'old_values' => [],
                    'new_values' => [
                        'code' => $promo->code,
                        'type' => $promo->type?->value,
                        'value' => (float) $promo->value,
                        'status' => $promo->status?->value,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
