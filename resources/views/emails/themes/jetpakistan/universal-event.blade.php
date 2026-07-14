{{--
    Universal JetPK event content — the only @section('content') for transactional emails.
    View key: emails.themes.jetpakistan.universal-event

    Rendered inside emails.themes.jetpakistan.layouts.base (canonical shell).
    $eventContent drives status alert, block list, and detail schema.
--}}
@extends('emails.themes.jetpakistan.layouts.base')

@section('content')
    @php
        $brand = (isset($emailBrand) && is_array($emailBrand)) ? $emailBrand : [];
        $eventContent = (isset($eventContent) && is_array($eventContent)) ? $eventContent : [];
        $blocks = is_array($eventContent['content_blocks'] ?? null) ? $eventContent['content_blocks'] : [];
        $detailRows = is_array($detailFieldValues ?? null) ? $detailFieldValues : [];
        $meta = (isset($meta) && is_array($meta)) ? $meta : [];
        $textColor = $brand['text_color'] ?? '#0f2435';
        $statusType = $eventContent['status_type'] ?? 'info';
        $alertTitle = $eventContent['alert_title'] ?? ($eventContent['status_label'] ?? null);
        $alertMessage = $eventContent['alert_message'] ?? null;
    @endphp

    @foreach($blocks as $block)
        @switch($block)
            @case('status-alert')
                @if(!empty($alertTitle) || !empty($alertMessage))
                    @include('emails.themes.jetpakistan.partials.alert-box', [
                        'type' => $statusType,
                        'title' => $alertTitle,
                        'message' => $alertMessage,
                    ])
                @endif
                @break

            @case('otp')
                @include('emails.themes.jetpakistan.partials.blocks.otp')
                @break

            @case('security-details')
                @include('emails.themes.jetpakistan.partials.blocks.security-details')
                @break

            @case('booking-summary')
                @if(!empty($booking))
                    @include('emails.themes.jetpakistan.partials.booking-summary', ['booking' => $booking, 'emailBrand' => $brand])
                @endif
                @break

            @case('payment-summary')
                @if(!empty($payment))
                    @include('emails.themes.jetpakistan.partials.payment-summary', ['payment' => $payment, 'emailBrand' => $brand])
                @endif
                @break

            @case('itinerary')
                @if(!empty($itinerary))
                    @include('emails.themes.jetpakistan.partials.flight-itinerary', ['itinerary' => $itinerary, 'emailBrand' => $brand])
                @endif
                @break

            @case('passengers')
                @if(!empty($passengers))
                    @include('emails.themes.jetpakistan.partials.passenger-summary', ['passengers' => $passengers, 'emailBrand' => $brand])
                @endif
                @break

            @case('invoice')
                @include('emails.themes.jetpakistan.partials.blocks.invoice')
                @break

            @case('payment-instructions')
                @include('emails.themes.jetpakistan.partials.blocks.payment-instructions')
                @break

            @case('change-summary')
                @if(!empty($meta['change_summary']))
                    @include('emails.themes.jetpakistan.partials.alert-box', [
                        'type' => 'info',
                        'title' => 'What changed',
                        'message' => $meta['change_summary'],
                    ])
                @endif
                @break

            @case('refund-info')
                @php $refundNote = $meta['refund_info'] ?? ($meta['refund_note'] ?? null); @endphp
                @if(!empty($refundNote))
                    @include('emails.themes.jetpakistan.partials.alert-box', [
                        'type' => 'info',
                        'title' => 'Refund information',
                        'message' => $refundNote,
                    ])
                @endif
                @break

            @case('pnr-note')
                @if(!empty($meta['pnr_note']))
                    @include('emails.themes.jetpakistan.partials.alert-box', [
                        'type' => 'info',
                        'title' => 'PNR note',
                        'message' => $meta['pnr_note'],
                    ])
                @endif
                @break

            @case('group-reservation')
                @include('emails.themes.jetpakistan.partials.blocks.group-reservation')
                @break

            @case('agent-application')
                @include('emails.themes.jetpakistan.partials.blocks.agent-application')
                @break

            @case('message')
                @php $message = $meta['message'] ?? ($message ?? null); @endphp
                @if(!empty($message))
                    <p style="font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:24px; color:{{ $textColor }}; margin:0 0 12px 0; white-space:pre-line;">{{ $message }}</p>
                @endif
                @break

            @case('detail-fields')
                @if($detailRows !== [])
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:6px 0 14px 0; border:1px solid {{ $brand['border_color'] ?? '#d9e6ee' }}; border-radius:12px;">
                        <tr>
                            <td style="padding:16px 18px;">
                                @foreach($detailRows as $row)
                                    @include('emails.themes.jetpakistan.partials.info-row', [
                                        'label' => $row['label'],
                                        'value' => $row['value'],
                                        'emailBrand' => $brand,
                                    ])
                                @endforeach
                            </td>
                        </tr>
                    </table>
                @endif
                @break

            @case('support-card')
                @include('emails.themes.jetpakistan.partials.support-card', ['emailBrand' => $brand, 'support' => $support ?? null])
                @break
        @endswitch
    @endforeach
@endsection
