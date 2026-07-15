{{-- Account created welcome email. CTA (Go to dashboard) via $ctaUrl / $ctaText. --}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand   = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $meta    = (isset($meta) && is_array($meta)) ? $meta : [];
        $brandName   = $brand['brand_name'] ?? 'JetPakistan';
        $accountType = $meta['account_type'] ?? null;
        $email       = $meta['email'] ?? null;
    @endphp

    @include('emails.themes.jetpakistan.partials.alert-box', [
        'type' => 'success',
        'title' => 'Welcome aboard!',
        'message' => 'Your '.$brandName.' account is ready. Sign in any time to search flights, manage bookings and track payments.',
    ])

    @if(!empty($accountType) || !empty($email))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px; background-color:#ffffff;">
            <tr>
                <td style="padding:16px 18px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Account email', 'value' => $email, 'emailBrand' => $brand])
                        @include('emails.themes.jetpakistan.partials.info-row', ['label' => 'Account type', 'value' => $accountType, 'emailBrand' => $brand])
                    </table>
                </td>
            </tr>
        </table>
    @endif

    @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
@endsection
