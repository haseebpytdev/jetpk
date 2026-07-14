@php
    $type = $type ?? 'info';
    $message = $message ?? '';
@endphp

<div
    class="ota-mobile-auth__alert ota-mobile-auth__alert--{{ $type }}"
    role="alert"
>
    {{ $message }}
</div>
