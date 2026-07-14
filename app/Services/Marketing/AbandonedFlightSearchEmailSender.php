<?php

namespace App\Services\Marketing;

use App\Mail\AbandonedFlightSearchMail;
use App\Models\Agency;
use App\Models\CommunicationLog;
use App\Models\FlightSearchMarketingSnapshot;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AbandonedFlightSearchEmailSender
{
    public const SKIP_MISSING_EMAIL = 'send_missing_email';

    public const SKIP_NO_TOP_OFFERS = 'send_no_top_offers';

    public const SKIP_EXPIRED = 'send_expired';

    public const SKIP_NOT_READY = 'send_not_ready';

    public const EVENT_KEY = 'abandoned_flight_search';

    /**
     * @return array{outcome: 'sent'|'skipped'|'failed', reason?: string, communication_log_id?: int}
     */
    public function send(FlightSearchMarketingSnapshot $snapshot): array
    {
        if (! $snapshot->isReady()) {
            return ['outcome' => 'skipped', 'reason' => self::SKIP_NOT_READY];
        }

        if ($snapshot->expires_at !== null && $snapshot->expires_at->lte(now())) {
            $snapshot->markExpiredFromReady(self::SKIP_EXPIRED);
            $this->logCommunication($snapshot, 'skipped', self::SKIP_EXPIRED);

            return ['outcome' => 'skipped', 'reason' => self::SKIP_EXPIRED];
        }

        $email = strtolower(trim((string) $snapshot->recipient_email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $snapshot->markSkippedFromReady(self::SKIP_MISSING_EMAIL);
            $this->logCommunication($snapshot, 'skipped', self::SKIP_MISSING_EMAIL);

            return ['outcome' => 'skipped', 'reason' => self::SKIP_MISSING_EMAIL];
        }

        if (! $snapshot->hasSendableOffers()) {
            $snapshot->markSkippedFromReady(self::SKIP_NO_TOP_OFFERS);
            $this->logCommunication($snapshot, 'skipped', self::SKIP_NO_TOP_OFFERS);

            return ['outcome' => 'skipped', 'reason' => self::SKIP_NO_TOP_OFFERS];
        }

        $criteria = is_array($snapshot->criteria) ? $snapshot->criteria : [];
        $ctaUrl = $this->buildResultsUrl($criteria);
        $offers = $this->prepareEmailOffers($snapshot);
        $subject = $this->resolveSubject();
        $brandName = $this->resolveBrandName($snapshot->agency);
        $supportEmail = (string) config('ota-brand.support_email', '');
        $supportPhone = (string) config('ota-brand.support_phone', '');

        $log = $this->logCommunication($snapshot, 'sending', null, $subject);

        try {
            Mail::to($email)->send(new AbandonedFlightSearchMail(
                subjectLine: $subject,
                brandName: $brandName,
                supportEmail: $supportEmail,
                supportPhone: $supportPhone,
                routeLabel: FlightOfferDisplayPresenter::formatCriteriaRouteLabel($criteria),
                tripTypeLabel: FlightOfferDisplayPresenter::formatCriteriaTripTypeLabel((string) ($criteria['trip_type'] ?? 'one_way')),
                departDate: (string) ($criteria['depart_date'] ?? ''),
                returnDate: filled($criteria['return_date'] ?? null) ? (string) $criteria['return_date'] : null,
                passengerSummary: $this->formatPassengerSummary($criteria),
                offers: $offers,
                ctaUrl: $ctaUrl,
                agency: $snapshot->agency,
            ));

            if (! $snapshot->markSent($log?->id)) {
                Log::notice('abandoned_flight_search.send_status_race', [
                    'snapshot_id' => $snapshot->id,
                ]);

                return ['outcome' => 'skipped', 'reason' => self::SKIP_NOT_READY];
            }

            if ($log !== null) {
                $log->forceFill([
                    'status' => 'sent',
                    'sent_at' => now(),
                ])->save();
            }

            return [
                'outcome' => 'sent',
                'communication_log_id' => $log?->id,
            ];
        } catch (Throwable $e) {
            $reason = 'send_failed: '.$e::class;
            $snapshot->markFailed($reason);

            if ($log !== null) {
                $log->forceFill([
                    'status' => 'failed',
                    'error_message' => $this->safeErrorMessage($e->getMessage()),
                ])->save();
            } else {
                $this->logCommunication($snapshot, 'failed', $reason, $subject);
            }

            Log::warning('abandoned_flight_search.send_failed', [
                'snapshot_id' => $snapshot->id,
                'search_id' => $snapshot->search_id,
                'exception' => $e::class,
            ]);
            report($e);

            return ['outcome' => 'failed', 'reason' => $reason];
        }
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function buildResultsUrl(array $criteria): string
    {
        $trip = (string) ($criteria['trip_type'] ?? 'one_way');
        $params = [
            'trip_type' => $trip,
            'cabin' => (string) ($criteria['cabin'] ?? 'economy'),
            'adults' => max(1, (int) ($criteria['adults'] ?? 1)),
            'children' => max(0, (int) ($criteria['children'] ?? 0)),
            'infants' => max(0, (int) ($criteria['infants'] ?? 0)),
        ];

        if ($trip === 'multi_city') {
            $segments = is_array($criteria['segments'] ?? null) ? $criteria['segments'] : [];
            $from = [];
            $to = [];
            $depart = [];
            foreach ($segments as $segment) {
                if (! is_array($segment)) {
                    continue;
                }
                $from[] = strtoupper(trim((string) ($segment['origin'] ?? '')));
                $to[] = strtoupper(trim((string) ($segment['destination'] ?? '')));
                $depart[] = (string) ($segment['departure_date'] ?? '');
            }

            return route('flights.results', array_merge($params, [
                'multi_from' => $from,
                'multi_to' => $to,
                'multi_depart' => $depart,
            ]));
        }

        return route('flights.results', array_merge($params, array_filter([
            'from' => strtoupper(trim((string) ($criteria['origin'] ?? ''))),
            'to' => strtoupper(trim((string) ($criteria['destination'] ?? ''))),
            'depart' => (string) ($criteria['depart_date'] ?? ''),
            'return_date' => $trip === 'round_trip' ? (string) ($criteria['return_date'] ?? '') : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function prepareEmailOffers(FlightSearchMarketingSnapshot $snapshot): array
    {
        $rows = is_array($snapshot->top_offers) ? $snapshot->top_offers : [];
        $safe = [];

        foreach (array_slice($rows, 0, 5) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $price = (float) ($row['price'] ?? 0);
            $currency = strtoupper((string) ($row['currency'] ?? 'PKR'));
            $safe[] = [
                'airline_name' => trim((string) ($row['airline_name'] ?? '')),
                'airline_code' => strtoupper(trim((string) ($row['airline_code'] ?? ''))),
                'origin' => strtoupper(trim((string) ($row['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($row['destination'] ?? ''))),
                'departure_at' => $this->formatDateTimeLabel((string) ($row['departure_at'] ?? '')),
                'arrival_at' => $this->formatDateTimeLabel((string) ($row['arrival_at'] ?? '')),
                'duration' => trim((string) ($row['duration'] ?? '')),
                'stops' => (int) ($row['stops'] ?? 0),
                'stops_label' => ((int) ($row['stops'] ?? 0)) === 0 ? 'Direct' : ((int) $row['stops']).' stop(s)',
                'price_label' => $currency.' '.number_format($price, 0, '.', ','),
            ];
        }

        return $safe;
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function formatPassengerSummary(array $criteria): string
    {
        $adults = max(1, (int) ($criteria['adults'] ?? 1));
        $children = max(0, (int) ($criteria['children'] ?? 0));
        $infants = max(0, (int) ($criteria['infants'] ?? 0));
        $cabin = ucfirst(str_replace('_', ' ', (string) ($criteria['cabin'] ?? 'economy')));

        $parts = [];
        $parts[] = $adults.' adult'.($adults === 1 ? '' : 's');
        if ($children > 0) {
            $parts[] = $children.' child'.($children === 1 ? '' : 'ren');
        }
        if ($infants > 0) {
            $parts[] = $infants.' infant'.($infants === 1 ? '' : 's');
        }

        return implode(', ', $parts).' · '.$cabin;
    }

    protected function resolveSubject(): string
    {
        $configured = trim((string) config('ota.abandoned_search_followup.email_subject', ''));
        if ($configured !== '') {
            return $configured;
        }

        return 'Still interested in your flight search?';
    }

    protected function resolveBrandName(?Agency $agency): string
    {
        if ($agency !== null) {
            return (string) ($agency->agencySetting?->display_name ?? $agency->name ?? config('ota-brand.name', config('app.name')));
        }

        return (string) config('ota-brand.name', config('app.name'));
    }

    protected function formatDateTimeLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d M Y H:i');
        } catch (Throwable) {
            return $value;
        }
    }

    protected function logCommunication(
        FlightSearchMarketingSnapshot $snapshot,
        string $status,
        ?string $reason = null,
        ?string $subject = null,
    ): ?CommunicationLog {
        try {
            return CommunicationLog::query()->create([
                'agency_id' => $snapshot->agency_id,
                'user_id' => $snapshot->user_id,
                'channel' => 'email',
                'event' => self::EVENT_KEY,
                'recipient_email' => strtolower(trim((string) $snapshot->recipient_email)),
                'subject' => $subject,
                'status' => $status,
                'provider' => (string) config('mail.default'),
                'error_message' => $status === 'failed' ? $reason : null,
                'meta' => [
                    'snapshot_id' => $snapshot->id,
                    'search_id' => $snapshot->search_id,
                    'criteria_fingerprint' => $snapshot->criteria_fingerprint,
                    'skip_reason' => $reason,
                ],
                'sent_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('abandoned_flight_search.communication_log_failed', [
                'snapshot_id' => $snapshot->id,
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    protected function safeErrorMessage(string $message): string
    {
        return mb_substr($message, 0, 500);
    }
}
