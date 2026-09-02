<?php

namespace App\Support\FlightSearch;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sanitized progressive-search performance spans (JP-LARAVEL-PERF-01).
 *
 * Timers are request-scoped wall clocks in milliseconds. Never stores PII,
 * credentials, tokens, cookies, or raw supplier payloads.
 */
final class SearchPerfTrace
{
    /** @var array<string, self> in-process registry so afterResponse can resume without serializing the trace */
    private static array $live = [];

    private string $searchPerfId;

    private float $t0;

    /** @var array<string, float> */
    private array $marks = [];

    /** @var array<string, int> */
    private array $counters = [];

    /** @var list<array{sql_fingerprint: string, ms: float}> */
    private array $queries = [];

    private int $queryCount = 0;

    private float $queryTotalMs = 0.0;

    private bool $dbListening = false;

    /** @var list<array<string, mixed>> */
    private array $providers = [];

    private ?float $firstProviderNetworkStartMs = null;

    private ?float $lastEligibleProviderNetworkStartMs = null;

    private ?float $firstProviderResponseMs = null;

    private ?float $firstValidOutboundMs = null;

    private ?float $firstValidPairMs = null;

    /** Offset from T0 when return_split with combo_count>0 was Cache::put (poll-readable). */
    private ?float $firstValidPairPersistedMs = null;

    private ?float $firstResultExposedMs = null;

    private float $pairingTotalMs = 0.0;

    private ?float $lastPairingMs = null;

    private int $decryptCount = 0;

    private float $decryptTotalMs = 0.0;

    private int $settingsLookupCount = 0;

    private float $settingsLookupTotalMs = 0.0;

    private int $markupRowsScanned = 0;

    private float $markupDbMs = 0.0;

    private float $markupResolutionMs = 0.0;

    private float $loggingMs = 0.0;

    private float $filesystemMs = 0.0;

    private int $filesystemReads = 0;

    private float $phpCpuMs = 0.0;

    private float $lockWaitMs = 0.0;

    private float $dbTransactionMs = 0.0;

    private float $supplierAuthNetworkMs = 0.0;

    private string $dispatchMode = 'SEQUENTIAL';

    public function __construct(?string $searchPerfId = null)
    {
        $this->searchPerfId = $searchPerfId !== null && $searchPerfId !== ''
            ? $searchPerfId
            : (string) Str::uuid();
        $this->t0 = microtime(true);
        $this->mark('T0_LARAVEL_REQUEST_RECEIVED');
    }

    public static function remember(self $trace): void
    {
        self::$live[$trace->id()] = $trace;
    }

    public static function resume(string $searchPerfId): ?self
    {
        return self::$live[$searchPerfId] ?? null;
    }

    public static function forget(string $searchPerfId): void
    {
        unset(self::$live[$searchPerfId]);
    }

    public function id(): string
    {
        return $this->searchPerfId;
    }

    public function mark(string $name): void
    {
        $this->marks[$name] = microtime(true);
    }

    public function elapsedMsSinceT0(?float $at = null): float
    {
        return round((($at ?? microtime(true)) - $this->t0) * 1000, 3);
    }

    public function intervalMs(string $from, string $to): float
    {
        if (! isset($this->marks[$from], $this->marks[$to])) {
            return 0.0;
        }

        return max(0.0, round(($this->marks[$to] - $this->marks[$from]) * 1000, 3));
    }

    public function bump(string $counter, int $by = 1): void
    {
        $this->counters[$counter] = (int) ($this->counters[$counter] ?? 0) + $by;
    }

    public function startDbListener(): void
    {
        if ($this->dbListening) {
            return;
        }

        $this->dbListening = true;
        DB::listen(function ($query): void {
            $ms = (float) ($query->time ?? 0);
            $this->queryCount++;
            $this->queryTotalMs += $ms;
            $sql = preg_replace('/\s+/', ' ', (string) ($query->sql ?? '')) ?? '';
            // Strip bound values — Laravel already parameterizes; keep fingerprint only.
            $fingerprint = substr($sql, 0, 240);
            $this->queries[] = [
                'sql_fingerprint' => $fingerprint,
                'ms' => round($ms, 3),
            ];
            if (count($this->queries) > 80) {
                array_shift($this->queries);
            }
        });
    }

    public function recordSettingsLookup(float $ms): void
    {
        $this->settingsLookupCount++;
        $this->settingsLookupTotalMs += max(0.0, $ms);
    }

    public function recordDecrypt(float $ms): void
    {
        $this->decryptCount++;
        $this->decryptTotalMs += max(0.0, $ms);
    }

    public function recordMarkup(int $rowsScanned, float $dbMs, float $resolutionMs): void
    {
        $this->markupRowsScanned = max($this->markupRowsScanned, $rowsScanned);
        $this->markupDbMs += max(0.0, $dbMs);
        $this->markupResolutionMs += max(0.0, $resolutionMs);
    }

    public function recordLogging(float $ms): void
    {
        $this->loggingMs += max(0.0, $ms);
    }

    public function recordFilesystem(float $ms, int $reads = 1): void
    {
        $this->filesystemMs += max(0.0, $ms);
        $this->filesystemReads += max(0, $reads);
    }

    public function recordPhpCpu(float $ms): void
    {
        $this->phpCpuMs += max(0.0, $ms);
    }

    public function recordLockWait(float $ms): void
    {
        $this->lockWaitMs += max(0.0, $ms);
    }

    public function recordDbTransaction(float $ms): void
    {
        $this->dbTransactionMs += max(0.0, $ms);
    }

    public function recordSupplierAuthNetwork(float $ms): void
    {
        $this->supplierAuthNetworkMs += max(0.0, $ms);
    }

    public function setDispatchMode(string $mode): void
    {
        $this->dispatchMode = $mode;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function recordProvider(array $row): void
    {
        $offset = isset($row['network_start_offset_ms'])
            ? (float) $row['network_start_offset_ms']
            : null;
        if ($offset !== null && (bool) ($row['eligible'] ?? false)) {
            if ($this->firstProviderNetworkStartMs === null || $offset < $this->firstProviderNetworkStartMs) {
                $this->firstProviderNetworkStartMs = $offset;
            }
            if ($this->lastEligibleProviderNetworkStartMs === null || $offset > $this->lastEligibleProviderNetworkStartMs) {
                $this->lastEligibleProviderNetworkStartMs = $offset;
            }
        }

        $this->providers[] = [
            'provider' => (string) ($row['provider'] ?? ''),
            'eligible' => (bool) ($row['eligible'] ?? false),
            'eligibility_decision_ms' => (float) ($row['eligibility_decision_ms'] ?? 0),
            'request_build_ms' => (float) ($row['request_build_ms'] ?? 0),
            'queue_wait_ms' => (float) ($row['queue_wait_ms'] ?? 0),
            'network_start_offset_ms' => $offset,
            'dispatch_start_offset_ms' => isset($row['dispatch_start_offset_ms'])
                ? (float) $row['dispatch_start_offset_ms']
                : null,
            'network_duration_ms' => isset($row['network_duration_ms'])
                ? (float) $row['network_duration_ms']
                : null,
            'response_offset_ms' => isset($row['response_offset_ms'])
                ? (float) $row['response_offset_ms']
                : null,
            'accepted_offer_count' => isset($row['accepted_offer_count'])
                ? (int) $row['accepted_offer_count']
                : null,
            'token_cache_lookup_ms' => isset($row['token_cache_lookup_ms'])
                ? (float) $row['token_cache_lookup_ms']
                : null,
            'token_refresh_ms' => isset($row['token_refresh_ms'])
                ? (float) $row['token_refresh_ms']
                : null,
            'token_network_calls' => (int) ($row['token_network_calls'] ?? 0),
            'skip_reason' => isset($row['skip_reason']) ? (string) $row['skip_reason'] : null,
        ];
    }

    /**
     * Patch the most recent eligible provider row after its network call returns.
     */
    public function recordProviderResponse(string $provider, float $networkDurationMs, int $acceptedOfferCount = 0): void
    {
        $responseOffset = $this->elapsedMsSinceT0();
        if ($this->firstProviderResponseMs === null) {
            $this->firstProviderResponseMs = $responseOffset;
            $this->mark('T_FIRST_PROVIDER_RESPONSE');
        }

        for ($i = count($this->providers) - 1; $i >= 0; $i--) {
            $row = $this->providers[$i];
            if ((string) ($row['provider'] ?? '') !== $provider) {
                continue;
            }
            if (! (bool) ($row['eligible'] ?? false)) {
                continue;
            }
            $this->providers[$i]['network_duration_ms'] = round(max(0.0, $networkDurationMs), 3);
            $this->providers[$i]['response_offset_ms'] = $responseOffset;
            $this->providers[$i]['accepted_offer_count'] = max(0, $acceptedOfferCount);
            break;
        }
    }

    public function recordFirstValidOutbound(): void
    {
        if ($this->firstValidOutboundMs !== null) {
            return;
        }
        $this->firstValidOutboundMs = $this->elapsedMsSinceT0();
        $this->mark('T_FIRST_VALID_OUTBOUND');
        if ($this->firstResultExposedMs === null) {
            $this->firstResultExposedMs = $this->firstValidOutboundMs;
            $this->mark('T_FIRST_RESULT_EXPOSED');
        }
    }

    public function recordFirstValidPair(float $pairingMs): void
    {
        $pairing = max(0.0, $pairingMs);
        $this->lastPairingMs = round($pairing, 3);
        $this->pairingTotalMs += $pairing;

        if ($this->firstValidPairMs !== null) {
            return;
        }
        // R2: pair index built in-memory (not yet guaranteed poll-readable).
        $this->firstValidPairMs = $this->elapsedMsSinceT0();
        $this->mark('T_FIRST_VALID_PAIR');
        if ($this->firstResultExposedMs === null) {
            $this->firstResultExposedMs = $this->firstValidPairMs;
            $this->mark('T_FIRST_RESULT_EXPOSED');
        }
    }

    /**
     * R3: first time a usable return_split was persisted to the result store.
     * Call only after Cache::put of that payload succeeds.
     */
    public function recordFirstValidPairPersisted(): void
    {
        if ($this->firstValidPairPersistedMs !== null) {
            return;
        }
        $this->firstValidPairPersistedMs = $this->elapsedMsSinceT0();
        $this->mark('T_FIRST_VALID_PAIR_PERSISTED');
    }

    public function firstValidPairMs(): ?float
    {
        return $this->firstValidPairMs;
    }

    public function firstValidPairPersistedMs(): ?float
    {
        return $this->firstValidPairPersistedMs;
    }

    /**
     * Component intervals for the progressive init + afterResponse pre-network path.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $requestValidation = $this->intervalMs('T0_LARAVEL_REQUEST_RECEIVED', 'T1_REQUEST_VALIDATION_COMPLETE');
        $searchContext = $this->intervalMs('T1_REQUEST_VALIDATION_COMPLETE', 'T2_SEARCH_CONTEXT_READY');
        $authContext = $this->intervalMs('T2_SEARCH_CONTEXT_READY', 'T3_AUTH_CONTEXT_READY');
        $featureFlags = $this->intervalMs('T3_AUTH_CONTEXT_READY', 'T4_FEATURE_FLAGS_READY');
        $routeNorm = $this->intervalMs('T4_FEATURE_FLAGS_READY', 'T5_AIRPORT_ROUTE_NORMALIZATION_READY');
        $providerRegistry = $this->intervalMs('T5_AIRPORT_ROUTE_NORMALIZATION_READY', 'T6_PROVIDER_REGISTRY_READY');
        $providerEligibility = $this->intervalMs('T6_PROVIDER_REGISTRY_READY', 'T7_PROVIDER_ELIGIBILITY_COMPLETE');
        $commercial = $this->intervalMs('T7_PROVIDER_ELIGIBILITY_COMPLETE', 'T8_COMMERCIAL_RULES_READY');
        $markups = $this->intervalMs('T8_COMMERCIAL_RULES_READY', 'T9_MARKUPS_READY');
        $currency = $this->intervalMs('T9_MARKUPS_READY', 'T10_CURRENCY_CONTEXT_READY');
        $requestBuild = $this->intervalMs('T10_CURRENCY_CONTEXT_READY', 'T11_PROVIDER_REQUEST_BUILD_COMPLETE');
        $orchestratorPre = $this->intervalMs('T11_PROVIDER_REQUEST_BUILD_COMPLETE', 'T12_ORCHESTRATOR_DISPATCH_START');
        $dispatchToFirst = $this->intervalMs('T12_ORCHESTRATOR_DISPATCH_START', 'T13_FIRST_SUPPLIER_NETWORK_CALL_START');

        $totalPre = $this->firstProviderNetworkStartMs
            ?? (isset($this->marks['T13_FIRST_SUPPLIER_NETWORK_CALL_START'])
                ? $this->elapsedMsSinceT0($this->marks['T13_FIRST_SUPPLIER_NETWORK_CALL_START'])
                : $this->elapsedMsSinceT0());

        $componentSum = $requestValidation + $searchContext + $authContext + $featureFlags
            + $routeNorm + $providerRegistry + $providerEligibility + $commercial
            + $markups + $currency + $requestBuild + $orchestratorPre + $dispatchToFirst;

        $fingerprints = [];
        foreach ($this->queries as $q) {
            $fp = $q['sql_fingerprint'];
            $fingerprints[$fp] = ($fingerprints[$fp] ?? 0) + 1;
        }
        $duplicateQueryCount = 0;
        foreach ($fingerprints as $count) {
            if ($count > 1) {
                $duplicateQueryCount += $count - 1;
            }
        }

        $slowest = 0.0;
        foreach ($this->queries as $q) {
            $slowest = max($slowest, (float) $q['ms']);
        }

        $spread = null;
        if ($this->firstProviderNetworkStartMs !== null && $this->lastEligibleProviderNetworkStartMs !== null) {
            $spread = max(0.0, round($this->lastEligibleProviderNetworkStartMs - $this->firstProviderNetworkStartMs, 3));
        }

        $initCompleteMs = isset($this->marks['T_INIT_RESPONSE_READY'])
            ? $this->elapsedMsSinceT0($this->marks['T_INIT_RESPONSE_READY'])
            : null;
        $afterResponseStartMs = isset($this->marks['T_AFTER_RESPONSE_START'])
            ? $this->elapsedMsSinceT0($this->marks['T_AFTER_RESPONSE_START'])
            : null;

        return [
            'search_perf_id' => $this->searchPerfId,
            'REQUEST_VALIDATION_MS' => $requestValidation,
            'SEARCH_CONTEXT_MS' => $searchContext,
            'AUTH_CONTEXT_MS' => $authContext,
            'FEATURE_FLAG_MS' => $featureFlags,
            'ROUTE_NORMALIZATION_MS' => $routeNorm,
            'PROVIDER_REGISTRY_MS' => $providerRegistry,
            'PROVIDER_ELIGIBILITY_MS' => $providerEligibility,
            'COMMERCIAL_RULES_MS' => $commercial,
            'MARKUP_LOADING_MS' => $markups,
            'CURRENCY_CONTEXT_MS' => $currency,
            'PROVIDER_REQUEST_BUILD_MS' => $requestBuild,
            'ORCHESTRATOR_PRE_DISPATCH_MS' => $orchestratorPre,
            'DISPATCH_TO_FIRST_NETWORK_MS' => $dispatchToFirst,
            'TOTAL_PRE_SUPPLIER_MS' => round((float) $totalPre, 3),
            'INIT_RESPONSE_MS' => $initCompleteMs,
            'AFTER_RESPONSE_START_OFFSET_MS' => $afterResponseStartMs,
            'COMPONENT_SUM_MS' => round($componentSum, 3),
            'COMPONENT_RECONCILE_DELTA_MS' => round(((float) $totalPre) - $componentSum, 3),
            'PRE_SUPPLIER_DB_QUERY_COUNT' => $this->queryCount,
            'PRE_SUPPLIER_DB_TOTAL_MS' => round($this->queryTotalMs, 3),
            'PRE_SUPPLIER_SLOWEST_QUERY_MS' => round($slowest, 3),
            'PRE_SUPPLIER_DUPLICATE_QUERY_COUNT' => $duplicateQueryCount,
            'SETTINGS_LOOKUP_COUNT' => $this->settingsLookupCount,
            'SETTINGS_LOOKUP_TOTAL_MS' => round($this->settingsLookupTotalMs, 3),
            'SUPPLIER_CONFIG_DECRYPT_COUNT' => $this->decryptCount,
            'SUPPLIER_CONFIG_DECRYPT_MS' => round($this->decryptTotalMs, 3),
            'MARKUP_RULE_ROWS_SCANNED' => $this->markupRowsScanned,
            'MARKUP_DB_MS' => round($this->markupDbMs, 3),
            'MARKUP_RESOLUTION_MS' => round($this->markupResolutionMs, 3),
            'PRE_SUPPLIER_LOGGING_MS' => round($this->loggingMs, 3),
            'PRE_SUPPLIER_FILESYSTEM_MS' => round($this->filesystemMs, 3),
            'PRE_SUPPLIER_FILESYSTEM_READS' => $this->filesystemReads,
            'PRE_SUPPLIER_PHP_CPU_MS' => round($this->phpCpuMs, 3),
            'PRE_SUPPLIER_LOCK_WAIT_MS' => round($this->lockWaitMs, 3),
            'PRE_SUPPLIER_DB_TRANSACTION_MS' => round($this->dbTransactionMs, 3),
            'PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS' => round($this->supplierAuthNetworkMs, 3),
            'SUPPLIER_DISPATCH_MODE' => $this->dispatchMode,
            'FIRST_PROVIDER_NETWORK_START_MS' => $this->firstProviderNetworkStartMs,
            'LAST_ELIGIBLE_PROVIDER_NETWORK_START_MS' => $this->lastEligibleProviderNetworkStartMs,
            'PROVIDER_START_SPREAD_MS' => $spread,
            'FIRST_PROVIDER_RESPONSE_MS' => $this->firstProviderResponseMs,
            'FIRST_VALID_OUTBOUND_MS' => $this->firstValidOutboundMs,
            'FIRST_VALID_PAIR_MS' => $this->firstValidPairMs,
            'FIRST_VALID_PAIR_PERSISTED_MS' => $this->firstValidPairPersistedMs,
            'PAIR_CREATE_TO_PERSIST_MS' => ($this->firstValidPairMs !== null && $this->firstValidPairPersistedMs !== null)
                ? round(max(0.0, $this->firstValidPairPersistedMs - $this->firstValidPairMs), 3)
                : null,
            'FIRST_RESULT_EXPOSED_MS' => $this->firstResultExposedMs,
            'PAIRING_MS' => $this->lastPairingMs,
            /** All *_MS offsets are ms since search-worker T0 unless named as a duration (PAIR_CREATE_TO_PERSIST_MS). */
            'CLOCK_BASE' => 'SEARCH_WORKER_T0',
            'PAIRING_TOTAL_MS' => round($this->pairingTotalMs, 3),
            'providers' => $this->providers,
            'counters' => $this->counters,
        ];
    }

    public function noticeLog(string $stage): void
    {
        $summary = $this->summary();
        Log::notice('flight_search.search_perf', [
            'stage' => $stage,
            'search_perf_id' => $this->searchPerfId,
            'TOTAL_PRE_SUPPLIER_MS' => $summary['TOTAL_PRE_SUPPLIER_MS'],
            'INIT_RESPONSE_MS' => $summary['INIT_RESPONSE_MS'],
            'SUPPLIER_DISPATCH_MODE' => $summary['SUPPLIER_DISPATCH_MODE'],
            'FIRST_PROVIDER_NETWORK_START_MS' => $summary['FIRST_PROVIDER_NETWORK_START_MS'],
            'PROVIDER_START_SPREAD_MS' => $summary['PROVIDER_START_SPREAD_MS'],
            'PRE_SUPPLIER_DB_QUERY_COUNT' => $summary['PRE_SUPPLIER_DB_QUERY_COUNT'],
            'PRE_SUPPLIER_DB_TOTAL_MS' => $summary['PRE_SUPPLIER_DB_TOTAL_MS'],
            'PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS' => $summary['PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS'],
            'REQUEST_VALIDATION_MS' => $summary['REQUEST_VALIDATION_MS'],
            'AUTH_CONTEXT_MS' => $summary['AUTH_CONTEXT_MS'],
            'PROVIDER_REGISTRY_MS' => $summary['PROVIDER_REGISTRY_MS'],
            'PROVIDER_ELIGIBILITY_MS' => $summary['PROVIDER_ELIGIBILITY_MS'],
            'providers' => array_map(static fn (array $p): array => [
                'provider' => $p['provider'],
                'eligible' => $p['eligible'],
                'network_start_offset_ms' => $p['network_start_offset_ms'],
                'dispatch_start_offset_ms' => $p['dispatch_start_offset_ms'],
                'network_duration_ms' => $p['network_duration_ms'] ?? null,
                'response_offset_ms' => $p['response_offset_ms'] ?? null,
                'accepted_offer_count' => $p['accepted_offer_count'] ?? null,
                'skip_reason' => $p['skip_reason'],
            ], $summary['providers']),
            'FIRST_PROVIDER_RESPONSE_MS' => $summary['FIRST_PROVIDER_RESPONSE_MS'],
            'FIRST_VALID_PAIR_MS' => $summary['FIRST_VALID_PAIR_MS'],
            'PAIRING_MS' => $summary['PAIRING_MS'],
        ]);
    }

    /**
     * Customer-safe subset for progressive JSON / poll payload.
     *
     * @return array<string, mixed>
     */
    public function publicMeta(): array
    {
        $s = $this->summary();

        return [
            'search_perf_id' => $s['search_perf_id'],
            'TOTAL_PRE_SUPPLIER_MS' => $s['TOTAL_PRE_SUPPLIER_MS'],
            'INIT_RESPONSE_MS' => $s['INIT_RESPONSE_MS'],
            'AFTER_RESPONSE_START_OFFSET_MS' => $s['AFTER_RESPONSE_START_OFFSET_MS'],
            'REQUEST_VALIDATION_MS' => $s['REQUEST_VALIDATION_MS'],
            'AUTH_CONTEXT_MS' => $s['AUTH_CONTEXT_MS'],
            'FEATURE_FLAG_MS' => $s['FEATURE_FLAG_MS'],
            'ROUTE_NORMALIZATION_MS' => $s['ROUTE_NORMALIZATION_MS'],
            'PROVIDER_REGISTRY_MS' => $s['PROVIDER_REGISTRY_MS'],
            'PROVIDER_ELIGIBILITY_MS' => $s['PROVIDER_ELIGIBILITY_MS'],
            'PROVIDER_REQUEST_BUILD_MS' => $s['PROVIDER_REQUEST_BUILD_MS'],
            'ORCHESTRATOR_PRE_DISPATCH_MS' => $s['ORCHESTRATOR_PRE_DISPATCH_MS'],
            'DISPATCH_TO_FIRST_NETWORK_MS' => $s['DISPATCH_TO_FIRST_NETWORK_MS'],
            'PRE_SUPPLIER_DB_QUERY_COUNT' => $s['PRE_SUPPLIER_DB_QUERY_COUNT'],
            'PRE_SUPPLIER_DB_TOTAL_MS' => $s['PRE_SUPPLIER_DB_TOTAL_MS'],
            'PRE_SUPPLIER_SLOWEST_QUERY_MS' => $s['PRE_SUPPLIER_SLOWEST_QUERY_MS'],
            'PRE_SUPPLIER_DUPLICATE_QUERY_COUNT' => $s['PRE_SUPPLIER_DUPLICATE_QUERY_COUNT'],
            'SETTINGS_LOOKUP_COUNT' => $s['SETTINGS_LOOKUP_COUNT'],
            'SETTINGS_LOOKUP_TOTAL_MS' => $s['SETTINGS_LOOKUP_TOTAL_MS'],
            'SUPPLIER_CONFIG_DECRYPT_COUNT' => $s['SUPPLIER_CONFIG_DECRYPT_COUNT'],
            'SUPPLIER_CONFIG_DECRYPT_MS' => $s['SUPPLIER_CONFIG_DECRYPT_MS'],
            'PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS' => $s['PRE_SEARCH_SUPPLIER_AUTH_NETWORK_MS'],
            'SUPPLIER_DISPATCH_MODE' => $s['SUPPLIER_DISPATCH_MODE'],
            'FIRST_PROVIDER_NETWORK_START_MS' => $s['FIRST_PROVIDER_NETWORK_START_MS'],
            'LAST_ELIGIBLE_PROVIDER_NETWORK_START_MS' => $s['LAST_ELIGIBLE_PROVIDER_NETWORK_START_MS'],
            'PROVIDER_START_SPREAD_MS' => $s['PROVIDER_START_SPREAD_MS'],
            'FIRST_PROVIDER_RESPONSE_MS' => $s['FIRST_PROVIDER_RESPONSE_MS'],
            'FIRST_VALID_OUTBOUND_MS' => $s['FIRST_VALID_OUTBOUND_MS'],
            'FIRST_VALID_PAIR_MS' => $s['FIRST_VALID_PAIR_MS'],
            'FIRST_VALID_PAIR_PERSISTED_MS' => $s['FIRST_VALID_PAIR_PERSISTED_MS'],
            'PAIR_CREATE_TO_PERSIST_MS' => $s['PAIR_CREATE_TO_PERSIST_MS'],
            'FIRST_RESULT_EXPOSED_MS' => $s['FIRST_RESULT_EXPOSED_MS'],
            'PAIRING_MS' => $s['PAIRING_MS'],
            'CLOCK_BASE' => $s['CLOCK_BASE'],
            'providers' => $s['providers'],
        ];
    }
}
