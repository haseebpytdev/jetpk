@extends(client_layout('dashboard', 'admin'))

@section('title', 'Reverse adjustment #'.$transaction->id)

@section('page-header')
    <x-dashboard.section-header title="Reverse manual adjustment #{{ $transaction->id }}" subtitle="{{ $transaction->agency?->name }}">
        <x-slot name="actions">
            <a href="{{ route('admin.finance.adjustments.show', $transaction) }}" class="jp-btn jp-btn--ghost btn-sm">Cancel</a>
        </x-slot>
    </x-dashboard.section-header>
@endsection

@section('content')
    <div class="jp-alert jp-alert--warn border-0 shadow-sm mb-4" data-testid="finance-adjustment-reversal-warning">
        <strong>Warning:</strong> Reversal creates a new compensating wallet and ledger transaction. It does not delete the original.
    </div>

    @if ($errors->any())
        <div class="jp-alert jp-alert--danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="jp-card__body">
            <p class="mb-1"><span class="text-secondary">Original type:</span> {{ str_replace('_', ' ', $transaction->type->value) }}</p>
            <p class="mb-1"><span class="text-secondary">Amount:</span> Rs {{ number_format((float) $transaction->amount, 2) }}</p>
            <p class="mb-0"><span class="text-secondary">Reference:</span> {{ $transaction->reference ?? '—' }}</p>
        </div>
    </div>

    <form method="post" action="{{ route('admin.finance.adjustments.reverse', $transaction) }}"
          class="card border-0 shadow-sm"
          data-testid="finance-adjustment-reverse-form"
          x-data="{ confirmed: {{ old('confirmation') ? 'true' : 'false' }} }">
        @csrf

        <div class="card-body row g-3">
            <div class="col-12">
                <label class="jp-label" for="reversal_reason">Reversal reason <span class="text-danger">*</span></label>
                <textarea name="reversal_reason" id="reversal_reason" class="jp-control" rows="3" required>{{ old('reversal_reason') }}</textarea>
            </div>

            <div class="col-12">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="confirmation" value="1"
                           x-model="confirmed" @checked(old('confirmation'))>
                    <span class="form-check-label">I confirm this reversal is authorized and documented.</span>
                </label>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end gap-2">
            <button type="submit" class="jp-btn jp-btn--danger" :disabled="!confirmed" data-testid="finance-adjustment-reverse-submit">Post reversal</button>
        </div>
    </form>
@endsection
