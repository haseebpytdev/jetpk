@php
    $isInternal = $message->visibility->value === 'internal';
@endphp

<div class="card border-0 shadow-sm {{ $isInternal ? 'border-warning' : '' }}" data-testid="support-message">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <span class="small fw-semibold">
                {{ e($message->author?->name ?? 'Support') }}
                @if ($isInternal)
                    <span class="badge bg-warning-lt text-warning ms-1">Internal</span>
                @endif
            </span>
            <span class="text-secondary small"><x-time.local :value="$message->created_at" context="operator" /></span>
        </div>
        <p class="mb-0 text-break">{{ e($message->body) }}</p>
    </div>
</div>



