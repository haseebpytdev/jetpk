<form method="POST" action="{{ $action }}" class="jp-card" id="promo-code-form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="jp-card__body">
        <div class="jp-alert jp-alert--info small mb-3">
            Promo codes apply to eligible checkout payable totals. Supplier fare and ticketing amounts remain unchanged.
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="jp-label">Code</label>
                <input type="text" name="code" class="jp-control text-uppercase" value="{{ old('code', $promoCode->code) }}" required maxlength="64" pattern="[A-Za-z0-9_-]+">
                <div class="form-hint">Letters, numbers, dash, underscore. Saved uppercase.</div>
            </div>
            <div class="col-md-4">
                <label class="jp-label">Name (optional)</label>
                <input type="text" name="name" class="jp-control" value="{{ old('name', $promoCode->name) }}" maxlength="255">
            </div>
            <div class="col-md-4">
                <label class="jp-label">Status</label>
                <select name="status" class="jp-control" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $promoCode->status?->value ?? 'active') === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="jp-label">Type</label>
                <select name="type" class="jp-control" required id="promo-type">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('type', $promoCode->type?->value ?? 'percent') === $type->value)>{{ ucfirst($type->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="jp-label">Value</label>
                <input type="number" step="0.01" min="0.01" name="value" class="jp-control" value="{{ old('value', $promoCode->value) }}" required id="promo-value">
                <div class="form-hint" id="promo-percent-hint">99 means customer pays 1% of payable amount.</div>
            </div>
            <div class="col-md-3" id="promo-currency-wrap">
                <label class="jp-label">Currency (fixed only)</label>
                <input type="text" name="currency" class="jp-control text-uppercase" maxlength="3" value="{{ old('currency', $promoCode->currency) }}" placeholder="PKR">
            </div>
            <div class="col-md-3">
                <label class="jp-label">Applies to</label>
                <select name="applies_to" class="jp-control" required>
                    @foreach ($appliesToOptions as $option)
                        <option value="{{ $option->value }}" @selected(old('applies_to', $promoCode->applies_to?->value ?? 'flights') === $option->value)>
                            {{ match($option->value) { 'group_ticketing' => 'Group Ticketing', 'all' => 'All', default => ucfirst($option->value) } }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="jp-label">Starts at</label>
                <input type="datetime-local" name="starts_at" class="jp-control" value="{{ old('starts_at', $promoCode->starts_at?->format('Y-m-d\TH:i')) }}">
            </div>
            <div class="col-md-3">
                <label class="jp-label">Ends at</label>
                <input type="datetime-local" name="ends_at" class="jp-control" value="{{ old('ends_at', $promoCode->ends_at?->format('Y-m-d\TH:i')) }}">
            </div>
            <div class="col-md-3">
                <label class="jp-label">Usage limit</label>
                <input type="number" min="1" name="usage_limit" class="jp-control" value="{{ old('usage_limit', $promoCode->usage_limit) }}">
            </div>
            @if ($promoCode->exists)
                <div class="col-md-3">
                    <label class="jp-label">Used count</label>
                    <input type="text" class="jp-control" value="{{ $promoCode->used_count }}" disabled readonly>
                </div>
            @endif
        </div>

        <details class="mt-4">
            <summary class="fw-semibold mb-2">Advanced settings</summary>
            <div class="row g-3 pt-2">
                <div class="col-md-3">
                    <label class="jp-label">Min order amount</label>
                    <input type="number" step="0.01" min="0" name="min_amount" class="jp-control" value="{{ old('min_amount', $promoCode->min_amount) }}">
                </div>
                <div class="col-md-3">
                    <label class="jp-label">Max discount</label>
                    <input type="number" step="0.01" min="0" name="max_discount" class="jp-control" value="{{ old('max_discount', $promoCode->max_discount) }}">
                </div>
                <div class="col-md-3">
                    <label class="jp-label">Per-user limit</label>
                    <input type="number" min="1" name="per_user_limit" class="jp-control" value="{{ old('per_user_limit', $promoCode->per_user_limit) }}">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <label class="form-check mb-0">
                        <input type="hidden" name="internal_testing_only" value="0">
                        <input type="checkbox" name="internal_testing_only" value="1" class="form-check-input" @checked((bool) old('internal_testing_only', $promoCode->internal_testing_only))>
                        <span class="form-check-label">Internal testing only (allows 100% / zero payable when enabled in config)</span>
                    </label>
                </div>
            </div>
        </details>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="jp-btn jp-btn--primary">Save promo code</button>
        <a href="{{ route('admin.promo-codes.index') }}" class="jp-btn jp-btn--ghost">Cancel</a>
    </div>
</form>
<script>
    (function () {
        const typeEl = document.getElementById('promo-type');
        const valueEl = document.getElementById('promo-value');
        const hintEl = document.getElementById('promo-percent-hint');
        const currencyWrap = document.getElementById('promo-currency-wrap');
        if (!typeEl || !valueEl) return;
        const sync = () => {
            const isPercent = typeEl.value === 'percent';
            hintEl.style.display = isPercent ? '' : 'none';
            currencyWrap.style.display = isPercent ? 'none' : '';
            valueEl.min = isPercent ? '1' : '0.01';
            valueEl.max = isPercent ? '99' : '';
        };
        typeEl.addEventListener('change', sync);
        sync();
    })();
</script>
