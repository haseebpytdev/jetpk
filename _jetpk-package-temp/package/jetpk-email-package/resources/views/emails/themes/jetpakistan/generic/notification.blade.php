{{-- Generic notification email. Inputs: $headline/$introText (via base), $meta['message'], CTA optional. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand   = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $meta    = (isset($meta) && is_array($meta)) ? $meta : [];
        $message = $meta['message'] ?? ($message ?? null);
        $type    = $meta['alert_type'] ?? null; // optional info|success|warning|error
        $textColor = $brand['text_color'] ?? '#0f2435';
    @endphp

    @if(!empty($type) && !empty($message))
        @include('emails.themes.jetpakistan.partials.alert-box', ['type' => $type, 'title' => $meta['alert_title'] ?? null, 'message' => $message])
    @elseif(!empty($message))
        <p style="font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:24px; color:{{ $textColor }}; margin:0 0 12px 0; white-space:pre-line;">{{ $message }}</p>
    @endif

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
