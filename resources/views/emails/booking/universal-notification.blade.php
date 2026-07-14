@php
    $payload = is_array($payload ?? null) ? $payload : [];
    $booking = is_array($payload['booking'] ?? null) ? $payload['booking'] : [];
    $segments = is_array($payload['segments'] ?? null) ? $payload['segments'] : [];
    $passengers = is_array($payload['passengers'] ?? null) ? $payload['passengers'] : [];
    $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
    $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
    $cta = is_array($payload['cta'] ?? null) ? $payload['cta'] : [];
    $notes = is_array($payload['notes'] ?? null) ? $payload['notes'] : [];
    $admin = is_array($payload['admin'] ?? null) ? $payload['admin'] : [];
    $selectedFareFamily = is_array($payload['selected_fare_family'] ?? null) ? $payload['selected_fare_family'] : null;
    $hasSelectedFareFamily = !empty($payment['has_selected_fare_family']) || $selectedFareFamily !== null;
    $tone = (string) ($payload['status_tone'] ?? 'info');
    $toneStyles = [
        'success' => ['bg' => '#ecfdf5', 'border' => '#10b981', 'text' => '#065f46'],
        'warning' => ['bg' => '#fffbeb', 'border' => '#f59e0b', 'text' => '#92400e'],
        'danger' => ['bg' => '#fef2f2', 'border' => '#ef4444', 'text' => '#991b1b'],
        'info' => ['bg' => '#eff6ff', 'border' => '#3b82f6', 'text' => '#1e40af'],
    ];
    $statusStyle = $toneStyles[$tone] ?? $toneStyles['info'];
    $summaryRows = [
        'Booking reference' => $booking['reference'] ?? null,
        'Route' => $booking['route'] ?? null,
        'Trip type' => $booking['trip_type'] ?? null,
        'Travel date' => $booking['travel_date'] ?? null,
        'Current status' => $booking['current_status'] ?? null,
        'PNR' => $booking['pnr'] ?? null,
        'Invoice/reference' => $booking['invoice_reference'] ?? null,
        'Airline' => $booking['airline'] ?? null,
    ];
    $contactRows = [
        'Name' => $contact['name'] ?? null,
        'Email' => $contact['email'] ?? null,
        'Phone' => $contact['phone'] ?? null,
        'Country' => $contact['country'] ?? null,
    ];
    $paymentRows = [
        'Payment status' => $payment['payment_status_label'] ?? $payment['status'] ?? null,
    ];
    $isAdminRecipient = in_array(($admin['recipient_type'] ?? 'customer'), ['admin', 'agent', 'staff', 'finance'], true);
    $isCustomerRecipient = ! $isAdminRecipient;
    $passengerSummary = (string) ($payload['passenger_summary'] ?? '');
    if ($hasSelectedFareFamily && !empty($payment['estimated_selected_fare'])) {
        $paymentRows[$payment['estimated_selected_fare_label'] ?? 'Estimated selected fare'] = $payment['estimated_selected_fare'];
        $paymentRows[$payment['estimated_amount_due_label'] ?? 'Estimated amount due'] = $payment['estimated_amount_due'] ?? $payment['estimated_selected_fare'];
        if (!empty($payment['base_fare_total'])) {
            $paymentRows['Base fare (search)'] = $payment['base_fare_total'];
        }
        if ($isAdminRecipient && !empty($payment['final_payable_status'])) {
            $paymentRows['Final payable'] = $payment['final_payable_status'];
        }
    } else {
        $paymentRows['Total'] = $payment['total'] ?? null;
    }
    $paymentRows = array_merge($paymentRows, [
        'Amount paid' => $payment['amount_paid'] ?? $payment['payment_amount'] ?? null,
        'Method' => $payment['payment_method'] ?? null,
        'Reference' => $payment['payment_reference'] ?? null,
        'Due at' => $payment['payment_due_at'] ?? null,
    ]);
    if (!$hasSelectedFareFamily || empty($payment['estimated_selected_fare'])) {
        $paymentRows['Balance due'] = $payment['balance_due'] ?? null;
    }
    $selectedFareRows = [];
    if ($selectedFareFamily !== null) {
        $selectedFareRows = [
            'Selected fare family' => $selectedFareFamily['fare_family_label'] ?? null,
            'Estimated selected fare' => $selectedFareFamily['estimated_fare_display'] ?? null,
            'Baggage' => $selectedFareFamily['baggage'] ?? null,
            'Cabin' => $selectedFareFamily['cabin'] ?? null,
            'Booking class' => $selectedFareFamily['booking_class'] ?? null,
            'Fare basis' => $selectedFareFamily['fare_basis'] ?? null,
            'Note' => $selectedFareFamily['validation_note'] ?? null,
        ];
    }
@endphp
@extends('emails.layouts.universal', ['company' => $payload['company'] ?? [], 'title' => $payload['title'] ?? null])

@section('content')
    <h1 style="margin:0 0 6px;font-size:19px;line-height:1.25;color:#0f172a;font-weight:700;">{{ $payload['title'] ?? 'Booking update' }}</h1>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;border-left:3px solid {{ $statusStyle['border'] }};background-color:{{ $statusStyle['bg'] }};border-radius:6px;">
        <tr>
            <td style="padding:7px 10px;color:{{ $statusStyle['text'] }};font-size:12px;font-weight:700;">
                {{ $payload['status_label'] ?? 'Update' }}
            </td>
        </tr>
    </table>

    <p style="margin:0 0 4px;color:#334155;font-size:13px;">Hello {{ $payload['greeting_name'] ?? 'Customer' }},</p>
    <p style="margin:0 0 14px;color:#475569;font-size:13px;line-height:1.45;">{{ $payload['intro'] ?? 'There is an update for your booking.' }}</p>

    <h2 style="margin:0 0 6px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Booking snapshot</h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
        @foreach($summaryRows as $label => $value)
            @if($value !== null && $value !== '')
                <tr>
                    <td class="stack-column" style="padding:7px 10px;background-color:#f8fafc;font-weight:600;font-size:12px;color:#64748b;width:36%;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $label }}</td>
                    <td class="stack-column" style="padding:7px 10px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    @if($segments !== [])
        <h2 style="margin:0 0 6px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Flight summary</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
            @foreach($segments as $segment)
                <tr>
                    <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#334155;">
                        <strong style="color:#0f172a;font-size:12px;">{{ $segment['label'] ?? 'Segment' }}</strong><br>
                        <span style="font-size:12px;line-height:1.4;">{{ $segment['summary'] ?? '' }}</span>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($isCustomerRecipient && $passengerSummary !== '')
        <h2 style="margin:0 0 6px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Passengers</h2>
        <p style="margin:0 0 14px;color:#334155;font-size:13px;">{{ $passengerSummary }}</p>
    @elseif($passengers !== [])
        <h2 style="margin:0 0 6px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Passengers</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
            @foreach($passengers as $passenger)
                <tr>
                    <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#334155;font-size:12px;">
                        {{ $passenger['index'] ?? $loop->iteration }}. {{ $passenger['name'] ?? 'Passenger' }}
                        <span style="color:#64748b;">({{ $passenger['type'] ?? 'Passenger' }}{{ !empty($passenger['is_lead']) ? ', lead' : '' }})</span>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if($contactRows !== [] && array_filter($contactRows) !== [])
        <h2 style="margin:0 0 6px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Contact</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;">
        @foreach($contactRows as $label => $value)
            @if($value !== null && $value !== '')
                <tr>
                    <td class="stack-column" style="padding:7px 10px;background-color:#f8fafc;font-weight:600;font-size:12px;color:#64748b;width:36%;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $label }}</td>
                    <td class="stack-column" style="padding:7px 10px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>
    @endif

    @if($selectedFareFamily !== null && array_filter($selectedFareRows) !== [])
        <h2 style="margin:0 0 6px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Selected fare</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
            @foreach($selectedFareRows as $label => $value)
                @if($value !== null && $value !== '')
                    <tr>
                        <td class="stack-column" style="padding:7px 10px;background-color:#f8fafc;font-weight:600;font-size:12px;color:#64748b;width:36%;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $label }}</td>
                        <td class="stack-column" style="padding:7px 10px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $value }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif

    @if(array_filter($payment) !== [])
        <h2 style="margin:0 0 6px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Payment</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
            @foreach($paymentRows as $label => $value)
                @if($value !== null && $value !== '')
                    <tr>
                        <td class="stack-column" style="padding:7px 10px;background-color:#f8fafc;font-weight:600;font-size:12px;color:#64748b;width:36%;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $label }}</td>
                        <td class="stack-column" style="padding:7px 10px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $value }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
        @if($hasSelectedFareFamily && !empty($payment['payable_disclaimer']))
            <p style="margin:0 0 18px;color:#64748b;font-size:12px;line-height:1.45;">{{ $payment['payable_disclaimer'] }}</p>
        @endif
    @endif

    @if($cta !== [])
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
            <tr>
                @foreach($cta as $button)
                    @if(!empty($button['url']) && !empty($button['label']))
                        <td style="padding:0 7px 7px 0;">
                            <a href="{{ $button['url'] }}" style="display:inline-block;padding:9px 16px;background-color:#0f766e;color:#ffffff;text-decoration:none;border-radius:7px;font-weight:700;font-size:13px;">{{ $button['label'] }}</a>
                        </td>
                    @endif
                @endforeach
            </tr>
        </table>
    @endif

    @if($notes !== [])
        <h2 style="margin:0 0 7px;font-size:14px;color:#0f172a;">Next Steps</h2>
        <ul style="margin:0 0 18px;padding-left:18px;color:#475569;font-size:13px;line-height:1.45;">
            @foreach($notes as $note)
                <li style="margin:0 0 4px;">{{ $note }}</li>
            @endforeach
        </ul>
    @endif

    @if($isAdminRecipient && $admin !== [] && array_filter($admin, fn ($value): bool => ! is_array($value) && $value !== null && $value !== '') !== [])
        <h2 style="margin:0 0 6px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Operational</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0;border:1px solid #e2e8f0;border-radius:7px;overflow:hidden;">
            @foreach($admin as $label => $value)
                @if($value !== null && $value !== '' && !is_array($value))
                    <tr>
                        <td class="stack-column" style="padding:7px 10px;background-color:#f8fafc;font-weight:600;font-size:12px;color:#64748b;width:36%;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ \Illuminate\Support\Str::headline((string) $label) }}</td>
                        <td class="stack-column" style="padding:7px 10px;font-size:13px;color:#0f172a;border-bottom:1px solid #e2e8f0;vertical-align:top;">{{ $value }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif
@endsection
