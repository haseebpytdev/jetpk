@props([
    'countries' => [],
    'selected' => '',
    'includeEmpty' => true,
    'emptyLabel' => 'Select country',
    'emptyDisabled' => true,
    'legacyValue' => null,
])

@php
    $countries = is_array($countries) ? $countries : [];
    $selectedValue = strtoupper(trim((string) $selected));
    $knownCodes = array_map(
        static fn (array $country): string => strtoupper((string) ($country['code'] ?? $country['alpha2'] ?? '')),
        $countries,
    );
    $legacy = $legacyValue ?? ($selectedValue !== '' && ! in_array($selectedValue, $knownCodes, true) ? $selectedValue : '');
@endphp

@if ($includeEmpty)
    <option value="" @if($emptyDisabled) disabled @endif @selected($selectedValue === '')>{{ $emptyLabel }}</option>
@endif
@foreach ($countries as $country)
    @php
        $code = strtoupper((string) ($country['code'] ?? $country['alpha2'] ?? ''));
        $name = (string) ($country['name'] ?? $code);
    @endphp
    <option value="{{ $code }}" @selected($selectedValue === $code)>{{ $name }} ({{ $code }})</option>
@endforeach
@if ($legacy !== '')
    <option value="{{ $legacy }}" selected>{{ $legacy }}</option>
@endif
