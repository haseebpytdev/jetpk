<?php

namespace App\Support\Dashboard;

use App\Enums\AccountType;
use App\Http\Resources\Dashboard\DashboardBookingResource;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingDocument;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use App\Support\Bookings\BookingCommunicationActionGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class DashboardBookingOperationalPresenter
{
    public function __construct(
        protected BackOfficeCapabilitiesPresenter $capabilitiesPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Booking $booking, User $viewer): array
    {
        $booking->loadMissing([
            'assignedStaff',
            'contact',
            'documents.generatedBy',
            'payments.payer',
            'payments.receiver',
            'cancellationRequests.requester',
            'cancellationRequests.approver',
            'refunds.approver',
            'communicationLogs',
            'tickets',
        ]);

        return [
            'documents' => $this->presentDocuments($booking),
            'assignment' => $this->presentAssignment($booking, $viewer),
            'paymentsHistory' => $this->presentPaymentsHistory($booking, $viewer),
            'cancellationState' => $this->presentCancellationState($booking, $viewer),
            'refundState' => $this->presentRefundState($booking, $viewer),
            'communicationLogs' => $this->presentCommunicationLogs($booking),
            'activityTimeline' => $this->presentActivityTimeline($booking),
            'operationalCapabilities' => $this->presentOperationalCapabilities($booking, $viewer),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function presentDocuments(Booking $booking): array
    {
        return $booking->documents
            ->sortByDesc('created_at')
            ->take(50)
            ->map(static function (BookingDocument $doc): array {
                $type = $doc->document_type?->value ?? (string) $doc->document_type;

                return [
                    'id' => (string) $doc->id,
                    'type' => $type,
                    'title' => str_replace('_', ' ', $type),
                    'documentNumber' => $doc->document_number,
                    'status' => (string) ($doc->status?->value ?? $doc->status ?? 'available'),
                    'generatedAt' => $doc->created_at?->toIso8601String(),
                    'generatedBy' => $doc->generatedBy?->name,
                    'downloadPath' => '/admin/bookings/documents/'.$doc->id.'/download',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentAssignment(Booking $booking, User $viewer): array
    {
        $canAssign = $viewer->isPlatformAdmin() && Gate::forUser($viewer)->allows('assignStaff', $booking);

        return [
            'staffId' => $booking->assigned_staff_id ? (string) $booking->assigned_staff_id : null,
            'staffName' => $booking->assignedStaff?->name,
            'assignedAt' => $booking->assigned_at?->toIso8601String(),
            'canAssign' => $canAssign,
            'assignableStaff' => $canAssign
                ? $this->assignableStaff($viewer, $booking)
                    ->map(static fn (User $user): array => [
                        'id' => (int) $user->id,
                        'name' => $user->name,
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function presentPaymentsHistory(Booking $booking, User $viewer): array
    {
        return $booking->payments
            ->sortByDesc('created_at')
            ->take(25)
            ->map(function (BookingPayment $payment) use ($viewer): array {
                return [
                    'id' => (string) $payment->id,
                    'amount' => (float) $payment->amount,
                    'currency' => strtoupper((string) ($payment->currency ?? 'PKR')),
                    'method' => (string) ($payment->method?->value ?? $payment->method ?? ''),
                    'status' => (string) ($payment->status?->value ?? $payment->status ?? ''),
                    'reference' => $payment->payment_reference,
                    'recordedAt' => $payment->created_at?->toIso8601String(),
                    'capabilities' => $this->capabilitiesPresenter->presentBookingPaymentCapabilities($viewer, $payment),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentCancellationState(Booking $booking, User $viewer): array
    {
        $requests = $booking->cancellationRequests
            ->sortByDesc('created_at')
            ->take(15)
            ->map(function (BookingCancellationRequest $request) use ($viewer): array {
                return [
                    'id' => (string) $request->id,
                    'status' => (string) ($request->status?->value ?? $request->status),
                    'cancellationType' => (string) ($request->cancellation_type?->value ?? $request->cancellation_type),
                    'reason' => $request->reason,
                    'requestedAt' => $request->created_at?->toIso8601String(),
                    'capabilities' => $this->capabilitiesPresenter->presentCancellationCapabilities($viewer, $request),
                ];
            })
            ->values()
            ->all();

        return [
            'bookingCancellationStatus' => (string) ($booking->cancellation_status ?? 'none'),
            'requests' => $requests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentRefundState(Booking $booking, User $viewer): array
    {
        $refunds = $booking->refunds
            ->sortByDesc('created_at')
            ->take(15)
            ->map(function (BookingRefund $refund) use ($viewer): array {
                return [
                    'id' => (string) $refund->id,
                    'amount' => (float) $refund->amount,
                    'currency' => strtoupper((string) ($refund->currency ?? 'PKR')),
                    'method' => (string) ($refund->method ?? ''),
                    'status' => (string) ($refund->status?->value ?? $refund->status),
                    'reference' => $refund->reference,
                    'requestedAt' => $refund->created_at?->toIso8601String(),
                    'capabilities' => $this->capabilitiesPresenter->presentRefundCapabilities($viewer, $refund),
                ];
            })
            ->values()
            ->all();

        return [
            'bookingRefundStatus' => (string) ($booking->refund_status ?? 'none'),
            'refunds' => $refunds,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function presentCommunicationLogs(Booking $booking): array
    {
        return $booking->communicationLogs
            ->sortByDesc('created_at')
            ->take(25)
            ->map(static function (CommunicationLog $log): array {
                return [
                    'id' => (string) $log->id,
                    'channel' => (string) ($log->channel ?? ''),
                    'event' => (string) ($log->event ?? ''),
                    'status' => (string) ($log->status ?? ''),
                    'recipientEmail' => $log->recipient_email,
                    'sentAt' => $log->sent_at?->toIso8601String() ?? $log->created_at?->toIso8601String(),
                    'errorSummary' => filled($log->error_message) ? 'delivery_failed' : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function presentActivityTimeline(Booking $booking): array
    {
        $events = [];
        $events[] = [
            'type' => 'booking_created',
            'title' => 'Booking created',
            'occurredAt' => $booking->created_at?->toIso8601String(),
            'details' => DashboardBookingResource::publicId($booking),
        ];
        if ($booking->assigned_at) {
            $events[] = [
                'type' => 'staff_assigned',
                'title' => 'Staff assigned',
                'occurredAt' => $booking->assigned_at?->toIso8601String(),
                'details' => $booking->assignedStaff?->name ?? 'Unassigned',
            ];
        }

        foreach ($booking->payments->take(10) as $payment) {
            $events[] = [
                'type' => 'payment_recorded',
                'title' => 'Payment recorded',
                'occurredAt' => $payment->created_at?->toIso8601String(),
                'details' => number_format((float) $payment->amount, 0).' '.strtoupper((string) ($payment->currency ?? 'PKR')),
            ];
        }

        usort($events, static fn (array $a, array $b): int => strcmp((string) ($b['occurredAt'] ?? ''), (string) ($a['occurredAt'] ?? '')));

        return array_slice($events, 0, 30);
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentOperationalCapabilities(Booking $booking, User $viewer): array
    {
        return [
            'canAssignStaff' => $viewer->isPlatformAdmin() && Gate::forUser($viewer)->allows('assignStaff', $booking),
            'canAddNote' => Gate::forUser($viewer)->allows('addNote', $booking),
            'canRecordPayment' => Gate::forUser($viewer)->allows('recordPayment', $booking),
            'canRequestCancellation' => Gate::forUser($viewer)->allows('request', [BookingCancellationRequest::class, $booking]),
            'canCreateRefund' => Gate::forUser($viewer)->allows('create', [BookingRefund::class, $booking]),
            'communicationActions' => BookingCommunicationActionGate::presentActionMap($booking),
            'communicationSendGated' => true,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    protected function assignableStaff(User $actor, Booking $booking): Collection
    {
        if ($actor->isPlatformAdmin()) {
            $agencyId = $booking->agency_id;
        } else {
            $agencyId = $actor->current_agency_id;
        }

        if ($agencyId === null) {
            return collect();
        }

        return User::query()
            ->where('current_agency_id', $agencyId)
            ->where('account_type', AccountType::Staff)
            ->orderBy('name')
            ->limit(200)
            ->get();
    }
}
