<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromoCodeAppliesTo;
use App\Enums\PromoCodeStatus;
use App\Enums\PromoCodeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromoCodeRequest;
use App\Http\Requests\Admin\UpdatePromoCodeRequest;
use App\Models\AuditLog;
use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PromoCodeController extends Controller
{
    public function index(Request $request): View
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

        return view('dashboard.admin.promo-codes.index', [
            'promoCodes' => $promoCodes,
            'filters' => $request->only(['status', 'q']),
            'statuses' => PromoCodeStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PromoCode::class);

        return view('dashboard.admin.promo-codes.create', [
            'promoCode' => new PromoCode,
            'types' => PromoCodeType::cases(),
            'statuses' => PromoCodeStatus::cases(),
            'appliesToOptions' => PromoCodeAppliesTo::cases(),
            'method' => 'POST',
            'action' => route('admin.promo-codes.store'),
        ]);
    }

    public function store(StorePromoCodeRequest $request): RedirectResponse
    {
        Gate::authorize('create', PromoCode::class);

        $promo = PromoCode::query()->create($this->payload($request) + [
            'agency_id' => $this->resolveAgencyId($request),
            'created_by' => $request->user()?->id,
        ]);

        $this->writeAudit($request, $promo, 'promo_code.created');

        return redirect()->route('admin.promo-codes.index')->with('status', 'promo-code-created');
    }

    public function edit(PromoCode $promoCode): View
    {
        Gate::authorize('view', $promoCode);

        return view('dashboard.admin.promo-codes.edit', [
            'promoCode' => $promoCode,
            'types' => PromoCodeType::cases(),
            'statuses' => PromoCodeStatus::cases(),
            'appliesToOptions' => PromoCodeAppliesTo::cases(),
            'method' => 'PATCH',
            'action' => route('admin.promo-codes.update', $promoCode),
        ]);
    }

    public function update(UpdatePromoCodeRequest $request, PromoCode $promoCode): RedirectResponse
    {
        Gate::authorize('update', $promoCode);

        $promoCode->update($this->payload($request) + [
            'updated_by' => $request->user()?->id,
        ]);

        $this->writeAudit($request, $promoCode->fresh(), 'promo_code.updated');

        return redirect()->route('admin.promo-codes.index')->with('status', 'promo-code-updated');
    }

    public function toggleStatus(Request $request, PromoCode $promoCode): RedirectResponse
    {
        Gate::authorize('update', $promoCode);

        $next = $promoCode->status === PromoCodeStatus::Active
            ? PromoCodeStatus::Inactive
            : PromoCodeStatus::Active;

        $promoCode->update(['status' => $next]);

        return back()->with('status', 'promo-code-status-updated');
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
