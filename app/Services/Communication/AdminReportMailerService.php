<?php

namespace App\Services\Communication;

use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\CommunicationLog;
use App\Services\Reports\BookingReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AdminReportMailerService
{
    public function __construct(
        protected OtaNotificationService $notificationService,
        protected BookingReportService $bookingReportService,
        protected BookingEmailPayloadFactory $bookingEmailPayloadFactory,
    ) {}

    public function sendDailyReport(Agency $agency, ?CarbonImmutable $day = null): void
    {
        $date = $day ?? CarbonImmutable::now($agency->timezone ?? config('app.timezone'));
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $payload = [
            'period' => $start->toDateString(),
            'bookings_created' => Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->count(),
            'bookings_by_status' => Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->all(),
            'payment_proofs_submitted' => BookingPayment::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->count(),
            'payments_verified' => BookingPayment::query()->where('agency_id', $agency->id)->where('status', 'verified')->whereBetween('updated_at', [$start, $end])->count(),
            'unpaid_balance' => (float) Booking::query()->where('agency_id', $agency->id)->sum('balance_due'),
            'gross_sales' => (float) Booking::query()->where('agency_id', $agency->id)->sum('amount_paid'),
            'unassigned_bookings' => Booking::query()->where('agency_id', $agency->id)->whereNull('assigned_staff_id')->count(),
        ];

        $this->notificationService->send(
            agency: $agency,
            eventKey: OtaNotificationEvent::DailyAdminReport->value,
            payload: $payload,
            fallbackSubject: 'Daily OTA Admin Report',
            fallbackBody: $this->bodyFromPayload('Daily report generated.', $payload),
            templateVariables: ['period_label' => $start->toFormattedDateString()]
        );
    }

    /**
     * Read-only PNR / manual-review digest for platform admins (manual/on-demand trigger).
     */
    public function sendPnrManualReviewDigest(
        Agency $agency,
        ?CarbonImmutable $start = null,
        ?CarbonImmutable $end = null,
        bool $forceResend = false,
    ): void {
        $timezone = $agency->timezone ?? config('app.timezone');
        $end = $end ?? CarbonImmutable::now($timezone);
        $start = $start ?? $end->copy()->subDay();

        if (! $forceResend && $this->pnrDigestRecentlySent($agency, $start, $end)) {
            return;
        }

        $digest = $this->bookingReportService->buildPnrManualReviewDigestSummary($agency, $start, $end);
        $universalPayload = $this->bookingEmailPayloadFactory->pnrManualReviewDigest($agency, $digest);

        $this->notificationService->send(
            agency: $agency,
            eventKey: OtaNotificationEvent::PnrManualReviewDigest->value,
            payload: [
                'period_label' => $digest['period_label'],
                'period_start' => $digest['period_start'],
                'period_end' => $digest['period_end'],
                'total_bookings' => $digest['total_bookings'],
                'manual_review_count' => $digest['manual_review_count'],
                'supplier_failed_count' => $digest['supplier_failed_count'],
                'ticketing_failed_count' => $digest['ticketing_failed_count'],
                'ticketing_not_supported_count' => $digest['ticketing_not_supported_count'],
                'pending_ticketing_count' => $digest['pending_ticketing_count'],
                'pnr_created_count' => $digest['pnr_created_count'],
                'failed_ratio' => $digest['failed_ratio'],
                'top_failure_codes' => $digest['top_failure_codes'],
                'sample_refs' => $digest['sample_refs'],
                'universal_email' => $universalPayload,
                'routing_note' => 'Platform-admin PNR/manual-review digest; platform_admin bucket only.',
            ],
            fallbackSubject: 'PNR / manual review digest — '.$digest['period_label'],
            fallbackBody: 'Failed PNR / manual review digest for '.$digest['period_label'].'.',
            templateVariables: [
                'period_label' => $digest['period_label'],
            ],
            recipientContext: [
                'notify_buckets' => ['platform_admin'],
            ],
        );
    }

    protected function pnrDigestRecentlySent(Agency $agency, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return CommunicationLog::query()
            ->where('agency_id', $agency->id)
            ->where('event', OtaNotificationEvent::PnrManualReviewDigest->value)
            ->where('meta->notification_type', 'pnr_manual_review_digest')
            ->where('meta->payload->period_start', $start->toIso8601String())
            ->where('meta->payload->period_end', $end->toIso8601String())
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->exists();
    }

    /**
     * Read-only agency booking activity summary for agency admins (manual/on-demand or scheduled all-agencies).
     */
    public function sendAgencyBookingActivitySummary(
        Agency $agency,
        ?CarbonImmutable $start = null,
        ?CarbonImmutable $end = null,
        bool $forceResend = false,
    ): void {
        $agency->loadMissing('agencySetting');
        $timezone = $agency->timezone ?? config('app.timezone');
        $end = $end ?? CarbonImmutable::now($timezone);
        $start = $start ?? $end->copy()->subDay();

        if (! $forceResend && $this->agencyBookingActivitySummaryRecentlySent($agency, $start, $end)) {
            return;
        }

        $summary = $this->bookingReportService->buildAgencyBookingActivitySummary($agency, $start, $end);
        $universalPayload = $this->bookingEmailPayloadFactory->agencyBookingActivitySummary($agency, $summary);

        $this->notificationService->send(
            agency: $agency,
            eventKey: OtaNotificationEvent::AgencyBookingActivitySummary->value,
            payload: [
                'period_label' => $summary['period_label'],
                'period_start' => $summary['period_start'],
                'period_end' => $summary['period_end'],
                'total_bookings' => $summary['total_bookings'],
                'agent_booking_count' => $summary['agent_booking_count'],
                'direct_customer_booking_count' => $summary['direct_customer_booking_count'],
                'agent_staff_created_count' => $summary['agent_staff_created_count'],
                'pending_count' => $summary['pending_count'],
                'confirmed_count' => $summary['confirmed_count'],
                'ticketed_count' => $summary['ticketed_count'],
                'cancelled_count' => $summary['cancelled_count'],
                'manual_review_count' => $summary['manual_review_count'],
                'pending_payment_count' => $summary['pending_payment_count'],
                'pending_ticketing_count' => $summary['pending_ticketing_count'],
                'total_booking_value' => $summary['total_booking_value'],
                'currency' => $summary['currency'],
                'sample_refs' => $summary['sample_refs'],
                'universal_email' => $universalPayload,
                'routing_note' => 'Agency-scoped booking activity summary; agency_admin bucket only.',
            ],
            fallbackSubject: 'Agency booking activity summary — '.$summary['period_label'],
            fallbackBody: 'Agency booking activity summary for '.$summary['period_label'].'.',
            templateVariables: [
                'period_label' => $summary['period_label'],
                'agency_name' => (string) ($agency->agencySetting?->display_name ?? $agency->name),
            ],
            recipientContext: [
                'notify_buckets' => ['agency_admin'],
            ],
        );
    }

    protected function agencyBookingActivitySummaryRecentlySent(Agency $agency, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return CommunicationLog::query()
            ->where('agency_id', $agency->id)
            ->where('event', OtaNotificationEvent::AgencyBookingActivitySummary->value)
            ->where('meta->notification_type', 'agency_booking_activity_summary')
            ->where('meta->payload->period_start', $start->toIso8601String())
            ->where('meta->payload->period_end', $end->toIso8601String())
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->exists();
    }

    public function sendWeeklyReport(Agency $agency, ?CarbonImmutable $date = null): void
    {
        $anchor = $date ?? CarbonImmutable::now($agency->timezone ?? config('app.timezone'));
        $start = $anchor->startOfWeek();
        $end = $anchor->endOfWeek();

        $payload = [
            'period' => $start->toDateString().' to '.$end->toDateString(),
            'bookings_created' => Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->count(),
            'gross_sales' => (float) Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->sum('amount_paid'),
            'top_routes' => Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->select('route', DB::raw('COUNT(*) as total'))->groupBy('route')->orderByDesc('total')->limit(5)->pluck('total', 'route')->all(),
            'top_airlines' => Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->select('airline', DB::raw('COUNT(*) as total'))->groupBy('airline')->orderByDesc('total')->limit(5)->pluck('total', 'airline')->all(),
        ];

        $this->notificationService->send(
            agency: $agency,
            eventKey: OtaNotificationEvent::WeeklyAdminReport->value,
            payload: $payload,
            fallbackSubject: 'Weekly OTA Admin Report',
            fallbackBody: $this->bodyFromPayload('Weekly report generated.', $payload),
            templateVariables: ['period_label' => $payload['period']]
        );
    }

    public function sendMonthlyReport(Agency $agency, ?CarbonImmutable $date = null): void
    {
        $anchor = $date ?? CarbonImmutable::now($agency->timezone ?? config('app.timezone'));
        $start = $anchor->startOfMonth();
        $end = $anchor->endOfMonth();

        $payload = [
            'period' => $start->format('F Y'),
            'gross_sales' => (float) Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->sum('amount_paid'),
            'unpaid_balance' => (float) Booking::query()->where('agency_id', $agency->id)->sum('balance_due'),
            'verified_payments' => BookingPayment::query()->where('agency_id', $agency->id)->where('status', 'verified')->whereBetween('updated_at', [$start, $end])->count(),
            'refunds_paid' => BookingPayment::query()->where('agency_id', $agency->id)->where('status', 'refunded')->whereBetween('updated_at', [$start, $end])->count(),
            'top_routes' => Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->select('route', DB::raw('COUNT(*) as total'))->groupBy('route')->orderByDesc('total')->limit(5)->pluck('total', 'route')->all(),
        ];

        $this->notificationService->send(
            agency: $agency,
            eventKey: OtaNotificationEvent::MonthlyAdminReport->value,
            payload: $payload,
            fallbackSubject: 'Monthly OTA Admin Report',
            fallbackBody: $this->bodyFromPayload('Monthly report generated.', $payload),
            templateVariables: ['period_label' => $payload['period']]
        );
    }

    public function sendMonthlyLedgers(Agency $agency, ?CarbonImmutable $date = null): void
    {
        $anchor = $date ?? CarbonImmutable::now($agency->timezone ?? config('app.timezone'));
        $start = $anchor->startOfMonth();
        $end = $anchor->endOfMonth();

        $attachments = [
            $this->monthlyLedgerBookingsCsvAttachment($agency, $start, $end),
            $this->monthlyLedgerPaymentsCsvAttachment($agency, $start, $end),
        ];

        $payload = [
            'period' => $start->format('F Y'),
            'bookings_count' => Booking::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->count(),
            'payments_count' => BookingPayment::query()->where('agency_id', $agency->id)->whereBetween('created_at', [$start, $end])->count(),
            'attachments' => array_column($attachments, 'name'),
        ];

        $this->notificationService->send(
            agency: $agency,
            eventKey: OtaNotificationEvent::MonthlyFinanceLedger->value,
            payload: $payload,
            fallbackSubject: 'Monthly OTA Ledger Summary',
            fallbackBody: $this->bodyFromPayload('Monthly ledger CSV exports are attached (bookings + payments).', $payload),
            templateVariables: ['period_label' => $payload['period']],
            attachments: $attachments,
        );
    }

    /**
     * @return array{name: string, mime: string, content: string}
     */
    private function monthlyLedgerBookingsCsvAttachment(Agency $agency, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $headers = ['booking_reference', 'route', 'travel_date', 'status', 'payment_status', 'amount_paid', 'balance_due', 'currency', 'created_at'];
        $rows = [$headers];
        $bookings = Booking::query()
            ->where('agency_id', $agency->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('id')
            ->get();

        foreach ($bookings as $b) {
            $rows[] = [
                (string) ($b->booking_reference ?? ''),
                (string) ($b->route ?? ''),
                $b->travel_date?->toDateString() ?? '',
                $b->status?->value ?? (string) $b->status,
                (string) $b->payment_status,
                (string) $b->amount_paid,
                (string) $b->balance_due,
                (string) $b->currency,
                $b->created_at?->toIso8601String() ?? '',
            ];
        }

        return [
            'name' => sprintf('ledger-bookings-%s.csv', $start->format('Ym')),
            'mime' => 'text/csv',
            'content' => $this->csvFromRows($rows),
        ];
    }

    /**
     * @return array{name: string, mime: string, content: string}
     */
    private function monthlyLedgerPaymentsCsvAttachment(Agency $agency, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $headers = ['booking_id', 'payment_reference', 'method', 'status', 'amount', 'currency', 'created_at'];
        $rows = [$headers];
        $payments = BookingPayment::query()
            ->where('agency_id', $agency->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('id')
            ->get();

        foreach ($payments as $p) {
            $rows[] = [
                (string) $p->booking_id,
                (string) ($p->payment_reference ?? ''),
                $p->method?->value ?? (string) $p->method,
                $p->status?->value ?? (string) $p->status,
                (string) $p->amount,
                (string) $p->currency,
                $p->created_at?->toIso8601String() ?? '',
            ];
        }

        return [
            'name' => sprintf('ledger-payments-%s.csv', $start->format('Ym')),
            'mime' => 'text/csv',
            'content' => $this->csvFromRows($rows),
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function csvFromRows(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(static function (string $cell): string {
                $escaped = str_replace('"', '""', $cell);

                return '"'.$escaped.'"';
            }, $row));
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bodyFromPayload(string $intro, array $payload): string
    {
        return $intro."\n\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
