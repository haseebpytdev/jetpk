@props([
    'status' => null,
    'label' => null,
    'tone' => null,
    'type' => null,
    'variant' => null,
    'size' => null,
    'value' => null,
    'title' => null,
])

@php
    $resolveText = static function (mixed $input): string {
        if ($input === null) {
            return '';
        }

        if (is_bool($input)) {
            return $input ? 'Yes' : 'No';
        }

        if (is_scalar($input)) {
            return trim((string) $input);
        }

        if (is_object($input)) {
            if (enum_exists($input::class)) {
                return trim((string) ($input->value ?? $input->name ?? ''));
            }

            if (method_exists($input, '__toString')) {
                return trim((string) $input);
            }

            if (isset($input->label)) {
                return trim((string) $input->label);
            }

            if (isset($input->value)) {
                return trim((string) $input->value);
            }
        }

        if (is_array($input)) {
            if (isset($input['label'])) {
                return trim((string) $input['label']);
            }

            if (isset($input['value'])) {
                return trim((string) $input['value']);
            }
        }

        return '';
    };

    $display = $resolveText($status);
    if ($display === '') {
        $display = $resolveText($label);
    }
    if ($display === '') {
        $display = $resolveText($value);
    }
    if ($display === '') {
        $display = $resolveText($type);
    }
    if ($display === '') {
        $display = $resolveText($variant);
    }

    $normalized = strtolower(str_replace(['_', '-'], ' ', $display));
    if ($normalized === '' || in_array($normalized, ['—', '-', 'null', 'n/a', 'none'], true)) {
        $display = 'Unknown';
        $normalized = 'unknown';
    }

    $explicitTone = is_scalar($tone ?? null) ? strtolower(trim((string) $tone)) : '';

    $toneClass = match (true) {
        in_array($explicitTone, ['green', 'success'], true) => 'jp-badge-pill--green',
        in_array($explicitTone, ['amber', 'warn', 'warning'], true) => 'jp-badge-pill--amber',
        in_array($explicitTone, ['blue', 'info'], true) => 'jp-badge-pill--blue',
        in_array($explicitTone, ['danger', 'error', 'red'], true) => 'jp-badge-pill--danger',
        in_array($normalized, ['confirmed', 'completed', 'paid', 'ticketed', 'active', 'approved', 'success'], true)
            || str_contains($normalized, 'confirm')
            || str_contains($normalized, 'complet')
            || str_contains($normalized, 'ticket')
            || (str_contains($normalized, 'paid') && ! str_contains($normalized, 'unpaid'))
            || (str_contains($normalized, 'active') && ! str_contains($normalized, 'inactive')) => 'jp-badge-pill--green',
        in_array($normalized, ['pending', 'processing', 'unpaid', 'review'], true)
            || str_contains($normalized, 'pending')
            || str_contains($normalized, 'process')
            || str_contains($normalized, 'unpaid') => 'jp-badge-pill--amber',
        in_array($normalized, ['cancelled', 'canceled', 'failed', 'rejected', 'error'], true)
            || str_contains($normalized, 'cancel')
            || str_contains($normalized, 'fail')
            || str_contains($normalized, 'reject') => 'jp-badge-pill--danger',
        str_contains($normalized, 'supplier') || str_contains($normalized, 'info') => 'jp-badge-pill--blue',
        default => '',
    };

    $titleAttr = is_scalar($title ?? null) && trim((string) $title) !== '' ? trim((string) $title) : null;
    $showLabel = ucwords(str_replace('_', ' ', $display));
    $sizeClass = is_scalar($size ?? null) && in_array(strtolower(trim((string) $size)), ['sm', 'small'], true) ? 'jp-badge-pill--sm' : '';
@endphp

<span
    @if ($titleAttr) title="{{ $titleAttr }}" @endif
    {{ $attributes->merge(['class' => trim('jp-badge-pill '.$toneClass.' '.$sizeClass)]) }}
>{{ $showLabel }}</span>
