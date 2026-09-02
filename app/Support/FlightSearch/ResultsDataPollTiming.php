<?php

namespace App\Support\FlightSearch;

/**
 * Non-PII poll-path timing for GET /flights/results/data (JP-LIVE-PERF-REG-04).
 *
 * All values are milliseconds since P0 unless named as an interval.
 */
final class ResultsDataPollTiming
{
    /** @var array<string, float> */
    private array $marks = [];

    private readonly float $t0;

    /** @var array<string, float|int|bool|null> */
    private array $storeMetrics = [];

    public function __construct()
    {
        $this->t0 = microtime(true);
        $this->mark('P0_REQUEST_RECEIVED');
    }

    public function mark(string $name): void
    {
        $this->marks[$name] = microtime(true);
    }

    /**
     * @param  array<string, float|int|bool|null>  $metrics
     */
    public function setStoreMetrics(array $metrics): void
    {
        $this->storeMetrics = $metrics;
    }

    /**
     * @return array<string, float|int|bool|null>
     */
    public function publicMeta(): array
    {
        $auth = $this->interval('P0_REQUEST_RECEIVED', 'P1_AUTH_SESSION_COMPLETE');
        $resolve = $this->interval('P1_AUTH_SESSION_COMPLETE', 'P2_SEARCH_ID_RESOLVED');
        $storeRead = $this->interval('P3_RESULT_STORE_READ_START', 'P4_RESULT_STORE_READ_COMPLETE');
        $deser = $this->interval('P4_RESULT_STORE_READ_COMPLETE', 'P5_DESERIALIZATION_COMPLETE');
        $merge = $this->interval('P5_DESERIALIZATION_COMPLETE', 'P6_PARTIAL_PAIR_MERGE_COMPLETE');
        $serialize = $this->interval('P6_PARTIAL_PAIR_MERGE_COMPLETE', 'P7_RESPONSE_SERIALIZED');
        $sent = $this->interval('P7_RESPONSE_SERIALIZED', 'P8_RESPONSE_SENT');
        $total = isset($this->marks['P8_RESPONSE_SENT'])
            ? round(($this->marks['P8_RESPONSE_SENT'] - $this->t0) * 1000, 3)
            : round((microtime(true) - $this->t0) * 1000, 3);

        return [
            'POLL_AUTH_MS' => $auth,
            'POLL_SEARCH_ID_RESOLVE_MS' => $resolve,
            'POLL_RESULT_STORE_READ_MS' => $storeRead,
            'POLL_RESULT_STORE_LOCK_WAIT_MS' => isset($this->storeMetrics['lock_wait_ms'])
                ? (float) $this->storeMetrics['lock_wait_ms']
                : null,
            'POLL_DESERIALIZATION_MS' => $deser ?? (isset($this->storeMetrics['deserialize_ms'])
                ? (float) $this->storeMetrics['deserialize_ms']
                : null),
            'POLL_PAIR_MERGE_MS' => $merge,
            'POLL_SERIALIZATION_MS' => $serialize,
            'POLL_RESPONSE_SENT_MS' => $sent,
            'POLL_TOTAL_SERVER_MS' => $total,
            'RESULT_STORE_PAYLOAD_BYTES' => isset($this->storeMetrics['bytes'])
                ? (int) $this->storeMetrics['bytes']
                : null,
            'RESULT_STORE_READ_WRITE_CONTENTION' => ((float) ($this->storeMetrics['lock_wait_ms'] ?? 0)) > 5.0
                ? 'YES'
                : 'NO',
            'COMPONENT_SUM_MS' => round(
                (float) ($auth ?? 0)
                + (float) ($resolve ?? 0)
                + (float) ($storeRead ?? 0)
                + (float) ($deser ?? 0)
                + (float) ($merge ?? 0)
                + (float) ($serialize ?? 0)
                + (float) ($sent ?? 0),
                3
            ),
        ];
    }

    private function interval(string $start, string $end): ?float
    {
        if (! isset($this->marks[$start], $this->marks[$end])) {
            return null;
        }

        return round(($this->marks[$end] - $this->marks[$start]) * 1000, 3);
    }
}
