<form method="POST" action="{{ $action }}" class="jp-card">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="jp-card__body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="jp-label">Name</label>
                <input type="text" name="name" class="jp-control" value="{{ old('name', $rule->name) }}" required>
            </div>
            <div class="col-md-3">
                <label class="jp-label">Rule type</label>
                <select name="rule_type" class="jp-control" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('rule_type', $rule->rule_type?->value) === $type->value)>{{ str_replace('_', ' ', $type->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="jp-label">Status</label>
                <select name="status" class="jp-control" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $rule->status?->value ?? 'active') === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="jp-label">Value</label>
                <input type="number" step="0.0001" min="0" name="value" class="jp-control" value="{{ old('value', $rule->value) }}" required>
            </div>
            <div class="col-md-3">
                <label class="jp-label">Value type</label>
                <select name="value_type" class="jp-control" required>
                    @foreach ($valueTypes as $valueType)
                        <option value="{{ $valueType->value }}" @selected(old('value_type', $rule->value_type?->value ?? 'percentage') === $valueType->value)>{{ ucfirst($valueType->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="jp-label">Priority</label>
                <input type="number" min="1" name="priority" class="jp-control" value="{{ old('priority', $rule->priority ?: 100) }}">
            </div>
            <div class="col-md-3">
                <label class="jp-label">Starts at</label>
                <input type="date" name="starts_at" class="jp-control" value="{{ old('starts_at', $rule->starts_at?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label class="jp-label">Ends at</label>
                <input type="date" name="ends_at" class="jp-control" value="{{ old('ends_at', $rule->ends_at?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-9">
                <label class="jp-label">Applies to (JSON)</label>
                <input type="text" name="applies_to" class="jp-control" value="{{ old('applies_to', $rule->applies_to ? json_encode($rule->applies_to) : '') }}" placeholder='{"route":"LHE-DXB"}'>
            </div>
            <div class="col-12">
                <label class="jp-label">Notes</label>
                <textarea name="meta_notes" class="jp-control" rows="3">{{ old('meta_notes', $rule->meta['notes'] ?? '') }}</textarea>
            </div>
        </div>
    </div>
    <div class="jp-card__footer">
        <div class="jp-action-bar jp-action-bar--between">
            <a href="{{ route('admin.markups') }}" class="jp-btn jp-btn--ghost">Cancel</a>
            <button type="submit" class="jp-btn jp-btn--primary">Save rule</button>
        </div>
    </div>
</form>

