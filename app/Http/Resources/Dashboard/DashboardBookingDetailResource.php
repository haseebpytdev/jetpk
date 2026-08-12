<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Booking;
use App\Support\Bookings\BookingLocalAmendmentPolicy;
use App\Support\Dashboard\DashboardMoneyPresenter;

final class DashboardBookingDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Booking $booking): array
    {
        $summary = DashboardBookingResource::fromModel($booking);
        $booking->loadMissing([
            'passengers',
            'contact',
            'fareBreakdown',
            'payments',
            'latestSupplierBooking',
            'tickets',
            'statusLogs.user',
            'bookingNotes.user',
            'communicationLogs.user',
            'documents.generatedBy',
        ]);
        $amendment = BookingLocalAmendmentPolicy::evaluate($booking);

        $passengers = $booking->passengers
            ->map(static fn ($p): array => [
                'displayName' => trim(implode(' ', array_filter([$p->title, $p->first_name, $p->last_name]))),
                'type' => (string) ($p->passenger_type ?? 'adult'),
            ])
            ->values()
            ->all();

        $fare = $booking->fareBreakdown;
        $totalMinor = (int) round((float) ($fare?->total ?? 0));
        $totalMoney = DashboardMoneyPresenter::presentBookingTotal($booking, $totalMinor);
        $baseMoney = DashboardMoneyPresenter::presentMinorUnits(
            (int) round((float) ($fare?->base_fare ?? 0)),
            $totalMoney['currency'],
            $totalMoney['currencySource'],
        );

        return [
            'summary' => $summary,
            'localContact' => [
                'email' => (string) ($booking->contact?->email ?? ''),
                'phone' => (string) ($booking->contact?->phone ?? ''),
                'country' => (string) ($booking->contact?->country ?? ''),
            ],
            'localAmendment' => $amendment,
            'itinerary' => [
                'route' => (string) ($booking->route ?? ''),
                'airline' => (string) ($booking->airline ?? ''),
                'travelDate' => $booking->travel_date?->format('Y-m-d'),
                'returnDate' => null,
            ],
            'passengers' => $passengers,
            'fareSummary' => [
                'currency' => $totalMoney['currency'],
                'currencyStatus' => $totalMoney['currencyStatus'],
                'currencySource' => $totalMoney['currencySource'],
                'baseFare' => $baseMoney['amountMinor'],
                'baseFareMoney' => $baseMoney,
                'taxes' => (int) round((float) ($fare?->taxes ?? 0)),
                'fees' => (int) round((float) ($fare?->fees ?? 0)),
                'markup' => (int) round((float) ($fare?->markup ?? 0)),
                'total' => $totalMoney['amountMinor'],
                'totalMoney' => $totalMoney,
            ],
            'paymentSummary' => [
                'status' => $summary['paymentStatus'],
                'amountPaid' => $summary['amountPaid'],
                'amountPaidMoney' => $summary['amountPaidMoney'],
                'totalAmount' => $summary['totalAmount'],
                'totalMoney' => $summary['totalMoney'],
                'currency' => $summary['currency'],
                'currencyStatus' => $summary['currencyStatus'],
            ],
            'pnrSummary' => [
                'pnr' => $summary['pnr'] ?: null,
                'supplierReference' => $summary['supplierReference'],
                'channel' => $summary['channel'],
                'supplier' => $summary['supplier'],
                'supplierStatus' => (string) ($booking->supplier_booking_status ?? 'not_started'),
            ],
            'ticketReadiness' => [
                'ticketingStatus' => $summary['ticketingStatus'],
                'ticketCount' => $booking->tickets->count(),
            ],
            'auditMetadata' => [
                'createdAt' => $booking->created_at?->toIso8601String(),
                'updatedAt' => $booking->updated_at?->toIso8601String(),
                'bookingStatus' => $summary['bookingStatus'],
            ],
            'statusTimeline' => $booking->statusLogs
                ->sortByDesc('created_at')
                ->take(50)
                ->values()
                ->map(static fn ($log): array => [
                    'occurredAt' => $log->created_at?->toIso8601String() ?? '',
                    'eventType' => 'status_changed',
                    'actorName' => (string) ($log->user?->name ?? 'System'),
                    'fromStatus' => (string) ($log->from_status ?? ''),
                    'toStatus' => (string) ($log->to_status ?? ''),
                    'summary' => trim(((string) $log->from_status).' → '.((string) $log->to_status)),
                    'note' => filled($log->note) ? (string) $log->note : null,
                ])
                ->all(),
            'internalNotes' => $booking->bookingNotes
                ->sortByDesc('created_at')
                ->take(30)
                ->values()
                ->map(static fn ($note): array => [
                    'createdAt' => $note->created_at?->toIso8601String() ?? '',
                    'authorName' => (string) ($note->user?->name ?? 'Staff'),
                    'noteType' => (string) ($note->note_type ?? 'internal'),
                    'note' => (string) $note->note,
                    'customerVisible' => (bool) $note->is_customer_visible,
                ])
                ->all(),
            'communications' => $booking->communicationLogs
                ->sortByDesc(fn ($log) => $log->sent_at ?? $log->created_at)
                ->take(30)
                ->values()
                ->map(static fn ($log): array => [
                    'sentAt' => ($log->sent_at ?? $log->created_at)?->toIso8601String() ?? '',
                    'channel' => (string) ($log->channel ?? ''),
                    'event' => (string) ($log->event ?? ''),
                    'status' => (string) ($log->status ?? ''),
                    'recipient' => (string) ($log->recipient_email ?: $log->recipient_phone ?: $log->recipient_name ?: '—'),
                    'subject' => filled($log->subject) ? (string) $log->subject : null,
                ])
                ->all(),
            'documents' => $booking->documents
                ->sortByDesc('generated_at')
                ->take(30)
                ->values()
                ->map(static fn ($document): array => [
                    'documentId' => (string) $document->id,
                    'documentType' => (string) ($document->document_type?->value ?? $document->document_type ?? 'document'),
                    'title' => (string) ($document->title ?: $document->document_number ?: 'Document'),
                    'status' => (string) ($document->status?->value ?? $document->status ?? 'generated'),
                    'generatedAt' => $document->generated_at?->toIso8601String(),
                    'generatedBy' => (string) ($document->generatedBy?->name ?? 'System'),
                ])
                ->all(),
        ];
    }
}
