<?php

namespace App\Support\Booking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Non-PII server timeline for GET/POST /booking/passengers (JP-FINAL-CLOSURE).
 * Marks S0–S8; correlatable via X-JP-Book-Now-Id when the client sends it.
 *
 * Production LOG_LEVEL is often `warning`, so INFO-only logs are discarded.
 * Timing is therefore exposed on safe response headers (Server-Timing +
 * X-JP-Passengers-Timing). Slow requests (>=3s) also emit Log::warning.
 */
final class PassengersRequestTiming
{
    public const HEADER = 'X-JP-Book-Now-Id';

    public const TIMING_HEADER = 'X-JP-Passengers-Timing';

    /** @var array<string, float> */
    private array $marks = [];

    private readonly float $t0;

    private readonly string $correlationId;

    private int $dbQueryCount = 0;

    private float $dbTotalMs = 0.0;

    private float $dbSlowestMs = 0.0;

    /** @var array<string, int> */
    private array $dbFingerprints = [];


    private function __construct(string $correlationId)
    {
        $this->correlationId = $correlationId;
        $this->t0 = microtime(true);
        $this->mark('S0_request_received');
        $this->startDbListener();
    }

    private function startDbListener(): void
    {
        DB::listen(function ($query): void {
            $ms = (float) ($query->time ?? 0);
            $this->dbQueryCount++;
            $this->dbTotalMs += $ms;
            if ($ms > $this->dbSlowestMs) {
                $this->dbSlowestMs = $ms;
            }
            $sql = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($query->sql ?? '')) ?? ''));
            $fp = strlen($sql) > 160 ? substr($sql, 0, 160) : $sql;
            if ($fp !== '') {
                $this->dbFingerprints[$fp] = ($this->dbFingerprints[$fp] ?? 0) + 1;
            }
        });
    }

    public static function start(Request $request): self
    {
        $fromHeader = trim((string) $request->headers->get(self::HEADER, ''));
        $id = $fromHeader !== '' ? substr($fromHeader, 0, 64) : ('srv-'.bin2hex(random_bytes(6)));

        $timing = new self($id);
        $request->attributes->set('jp_passengers_timing', $timing);

        return $timing;
    }

    public static function fromRequest(Request $request): ?self
    {
        $timing = $request->attributes->get('jp_passengers_timing');

        return $timing instanceof self ? $timing : null;
    }

    public function mark(string $name): void
    {
        $this->marks[$name] = microtime(true);
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toLogContext(): array
    {
        $deltas = [];
        foreach ($this->marks as $name => $at) {
            $deltas[$name.'_ms'] = (int) round(($at - $this->t0) * 1000);
        }

        $totalMs = (int) round((microtime(true) - $this->t0) * 1000);

        return array_merge([
            'correlation_id' => $this->correlationId,
            'total_ms' => $totalMs,
            'auth_gate_ms' => $this->intervalMs('S0b_auth_gate_start', 'S0c_auth_gate_end'),
            'session_hydrate_ms' => $this->intervalMs('S1_session_hydrate_start', 'S2_session_hydrate_end'),
            'offer_resolve_ms' => $this->intervalMs('S3_offer_resolve_start', 'S4_offer_resolve_end'),
            'live_search_ms' => $this->intervalMs('S3b_live_search_start', 'S3c_live_search_end'),
            'hold_validate_ms' => $this->intervalMs('S5_hold_validate_start', 'S6_hold_validate_end'),
            'serialize_ms' => $this->intervalMs('S7a_serialize_start', 'S7_payload_complete'),
            'passenger_contact_load_ms' => $this->intervalMs('S5_passenger_contact_load_start', 'S6_passenger_contact_load_end'),
            'app_internal_ms' => $totalMs,
            'db_query_count' => $this->dbQueryCount,
            'db_total_ms' => (int) round($this->dbTotalMs),
            'db_slowest_query_ms' => round($this->dbSlowestMs, 3),
            'db_duplicate_query_count' => count(array_filter($this->dbFingerprints, static fn (int $n): bool => $n > 1)),
        ], $deltas);
    }

    private function intervalMs(string $start, string $end): ?int
    {
        if (! isset($this->marks[$start], $this->marks[$end])) {
            return null;
        }

        return (int) round(($this->marks[$end] - $this->marks[$start]) * 1000);
    }

    public function finish(string $outcome = 'ok'): void
    {
        $this->mark('S8_response_dispatched');
        $context = array_merge($this->toLogContext(), [
            'outcome' => $outcome,
        ]);

        // Production often uses LOG_LEVEL=warning — keep fast path on info,
        // promote slow/outlier samples so they remain observable without
        // lowering global log level.
        if ((int) ($context['total_ms'] ?? 0) >= 3000) {
            Log::warning('jp.booking.passengers_timing', $context);
        } else {
            Log::info('jp.booking.passengers_timing', $context);
        }
    }

    /**
     * Attach non-PII timing headers to a response (Server-Timing + JSON summary).
     *
     * @template T of Response
     *
     * @param  T  $response
     * @return T
     */
    public function applyHeaders(Response $response): Response
    {
        $ctx = $this->toLogContext();
        $serverTiming = [];
        $metricMap = [
            'total' => 'total_ms',
            'auth' => 'auth_gate_ms',
            'sess' => 'session_hydrate_ms',
            'offer' => 'offer_resolve_ms',
            'livesearch' => 'live_search_ms',
            'hold' => 'hold_validate_ms',
            'serialize' => 'serialize_ms',
            'pax' => 'passenger_contact_load_ms',
            's0' => 'S0_request_received_ms',
            's7' => 'S7_payload_complete_ms',
            's8' => 'S8_response_dispatched_ms',
        ];
        foreach ($metricMap as $name => $key) {
            if (! isset($ctx[$key]) || $ctx[$key] === null) {
                continue;
            }
            $serverTiming[] = sprintf('%s;dur=%d', $name, (int) $ctx[$key]);
        }
        if ($serverTiming !== []) {
            $response->headers->set('Server-Timing', implode(', ', $serverTiming));
        }

        $payload = [
            'correlation_id' => $this->correlationId,
            'total_ms' => $ctx['total_ms'] ?? null,
            'auth_gate_ms' => $ctx['auth_gate_ms'] ?? null,
            'session_hydrate_ms' => $ctx['session_hydrate_ms'] ?? null,
            'offer_resolve_ms' => $ctx['offer_resolve_ms'] ?? null,
            'live_search_ms' => $ctx['live_search_ms'] ?? null,
            'hold_validate_ms' => $ctx['hold_validate_ms'] ?? null,
            'serialize_ms' => $ctx['serialize_ms'] ?? null,
            'passenger_contact_load_ms' => $ctx['passenger_contact_load_ms'] ?? null,
            'app_internal_ms' => $ctx['app_internal_ms'] ?? null,
            'db_query_count' => $ctx['db_query_count'] ?? null,
            'db_total_ms' => $ctx['db_total_ms'] ?? null,
            'db_slowest_query_ms' => $ctx['db_slowest_query_ms'] ?? null,
            'db_duplicate_query_count' => $ctx['db_duplicate_query_count'] ?? null,
            'S0_ms' => $ctx['S0_request_received_ms'] ?? null,
            'S7_ms' => $ctx['S7_payload_complete_ms'] ?? null,
            'S8_ms' => $ctx['S8_response_dispatched_ms'] ?? null,
        ];
        $response->headers->set(self::TIMING_HEADER, json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
        $response->headers->set(self::HEADER, $this->correlationId);
        // Expose timing headers to browser JS on same-origin /laravel proxy.
        $response->headers->set('Access-Control-Expose-Headers', implode(', ', [
            self::TIMING_HEADER,
            self::HEADER,
            'Server-Timing',
        ]));

        return $response;
    }

    /**
     * @template T of Response
     *
     * @param  T  $response
     * @return T
     */
    public function finalize(Response $response, string $outcome = 'ok'): Response
    {
        $this->finish($outcome);

        return $this->applyHeaders($response);
    }
}
