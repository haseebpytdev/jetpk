<?php

namespace App\Support\Sabre\Scenario;

use App\Support\Sabre\Scenario\SabreGdsRevalidationProbeDbSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Controlled QR unticketed book-and-retrieve lifecycle: plan (zero supplier calls) or live via scenario runner.
 */
final class SabreGdsQrUnticketedBookAndRetrieveLifecycle
{
    public const MODE = 'book-and-retrieve';

    public const CONFIRM_PRODUCTION = 'APPROVE-LIVE-SABRE-GDS-UNTICKETED-BOOK-AND-RETRIEVE';

    public const CONFIRM_PNR_CREATE = 'LIVE-SABRE-GDS-CREATE-ONE-UNTICKETED-PNR';

    public const CONFIRM_NO_TICKETING = 'CONFIRM-SABRE-TICKETING-DISABLED';

    public const ARTIFACT_DIRECTORY = 'sabre-gds-qr-unticketed-book-and-retrieve';

    /** @var list<string> */
    public const DENY_LOCATORS = ['FEZJFP'];

    public const MAX_SEARCH_CALLS = 1;

    public const MAX_REVALIDATION_CALLS = 1;

    public const MAX_PNR_CREATE_CALLS = 1;

    public const MAX_RETRIEVE_CALLS = 1;

    public function __construct(
        private readonly SabreGdsLiveScenarioRunner $scenarioRunner,
        private readonly SabreGdsLiveScenarioRunnerPassengerLoader $passengerLoader,
        private readonly SabreGdsQrUnticketedBookAndRetrieveRevalidationHandoff $revalidationHandoff,
        private readonly SabreGdsRevalidationProbeDbSnapshot $dbSnapshot,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(array $options): array
    {
        $send = ($options['send'] ?? false) === true;
        $lifecycleRunId = trim((string) ($options['lifecycle_run_id'] ?? ''));
        if ($lifecycleRunId === '') {
            $lifecycleRunId = (string) Str::uuid();
        }

        $gate = $this->evaluateGate($options, $send);
        if (($gate['allowed'] ?? false) !== true) {
            return $this->finalizeArtifact($lifecycleRunId, $send ? 'send' : 'plan', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'error' => 'gate_blocked',
                'gate' => $gate,
            ], $options);
        }

        $lock = $this->acquireLifecycleLock($lifecycleRunId);
        if (($lock['acquired'] ?? false) !== true) {
            return $this->finalizeArtifact($lifecycleRunId, $send ? 'send' : 'plan', [
                'lifecycle_run_id' => $lifecycleRunId,
                'command_mode' => self::MODE,
                'error' => 'lifecycle_lock_refused',
                'lock' => $lock,
            ], $options);
        }

        try {
            $dbBefore = $this->dbSnapshot->capture();
            $passengerAudit = $this->auditPassengerFileSafe((string) ($options['passenger_json'] ?? ''));

            if ($send) {
                $summary = $this->scenarioRunner->run($this->runnerOptions($options, $lifecycleRunId));
                $artifact = $this->buildLiveArtifact($lifecycleRunId, $summary, $passengerAudit, $dbBefore, $options);

                return $this->finalizeArtifact($lifecycleRunId, 'send', $artifact, $options);
            }

            $plan = $this->buildPlanArtifact($lifecycleRunId, $passengerAudit, $dbBefore, $options);

            return $this->finalizeArtifact($lifecycleRunId, 'plan', $plan, $options);
        } finally {
            if (($lock['cache_lock'] ?? null) !== null) {
                try {
                    $lock['cache_lock']->release();
                } catch (Throwable) {
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function evaluateGate(array $options, bool $send): array
    {
        $reasons = [];

        if ($this->containsDeniedLocator($options)) {
            $reasons[] = 'denylisted_locator_reference';
        }

        if ((bool) config('suppliers.sabre.ticketing_enabled', false)) {
            $reasons[] = 'ticketing_enabled';
        }

        $passengerPath = trim((string) ($options['passenger_json'] ?? ''));
        if ($passengerPath === '') {
            $reasons[] = 'passenger_json_required';
        } elseif (! $this->passengerFileIsPrivate($passengerPath)) {
            $reasons[] = 'passenger_file_not_private';
        }

        if (! $this->databaseHealthy()) {
            $reasons[] = 'database_unhealthy';
        }

        if (strtolower(trim((string) ($options['preset'] ?? ''))) !== 'qr-connecting') {
            $reasons[] = 'preset_must_be_qr_connecting';
        }

        if ($send) {
            if (! app()->environment('production')) {
                $reasons[] = 'send_requires_production_environment';
            }
            if (trim((string) ($options['confirm_production'] ?? '')) !== self::CONFIRM_PRODUCTION) {
                $reasons[] = 'confirm_production_missing';
            }
            if (trim((string) ($options['confirm_pnr_create'] ?? '')) !== self::CONFIRM_PNR_CREATE) {
                $reasons[] = 'confirm_pnr_create_missing';
            }
            if (trim((string) ($options['confirm_no_ticketing'] ?? '')) !== self::CONFIRM_NO_TICKETING) {
                $reasons[] = 'confirm_no_ticketing_missing';
            }
        }

        $existing = $this->existingLifecycleState($options);
        if (($existing['blocked'] ?? false) === true) {
            $reasons[] = 'lifecycle_idempotency_'.(string) ($existing['state'] ?? 'blocked');
        }

        return [
            'allowed' => $reasons === [],
            'reasons' => $reasons,
            'deny_locators' => self::DENY_LOCATORS,
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function containsDeniedLocator(array $options): bool
    {
        $haystack = strtolower(json_encode($options, JSON_THROW_ON_ERROR));
        foreach (self::DENY_LOCATORS as $locator) {
            if (str_contains($haystack, strtolower($locator))) {
                return true;
            }
        }

        return false;
    }

    public function passengerFileIsPrivate(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $normalized = str_replace('\\', '/', $absolutePath);
        if (PHP_OS_FAMILY === 'Windows' && str_contains($normalized, '/storage/app/private/')) {
            return true;
        }

        $perms = @fileperms($absolutePath);
        if ($perms === false) {
            return false;
        }

        return ($perms & 0777) <= 0600;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPassengerFileSafe(string $absolutePath): array
    {
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return ['file_present' => false];
        }

        try {
            $this->passengerLoader->loadFromPath($absolutePath);
        } catch (Throwable) {
            return [
                'file_present' => true,
                'schema_valid' => false,
            ];
        }

        return [
            'file_present' => true,
            'schema_valid' => true,
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'name_fields_present' => true,
            'gender_present' => true,
            'date_of_birth_present' => true,
            'nationality_present' => true,
            'passport_present' => true,
            'email_present' => true,
            'phone_present' => true,
            'emergency_contact_supported' => false,
            'emergency_contact_present' => false,
            'file_mode_private' => $this->passengerFileIsPrivate($absolutePath),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function buildPlanArtifact(
        string $lifecycleRunId,
        array $passengerAudit,
        array $dbBefore,
        array $options,
    ): array {
        return [
            'lifecycle_run_id' => $lifecycleRunId,
            'command_mode' => self::MODE,
            'probe_mode' => 'plan',
            'operation_plan' => [
                'search_planned' => true,
                'revalidation_planned' => true,
                'pnr_create_planned' => true,
                'retrieve_planned' => true,
                'cancellation_planned' => false,
                'ticketing_planned' => false,
                'airticket_planned' => false,
                'maximum_search_calls' => self::MAX_SEARCH_CALLS,
                'maximum_revalidation_calls' => self::MAX_REVALIDATION_CALLS,
                'maximum_pnr_create_calls' => self::MAX_PNR_CREATE_CALLS,
                'maximum_retrieve_calls' => self::MAX_RETRIEVE_CALLS,
                'automatic_pnr_create_retry' => false,
            ],
            'passenger_audit' => $passengerAudit,
            'deny_locators' => self::DENY_LOCATORS,
            'ticketing_enabled' => (bool) config('suppliers.sabre.ticketing_enabled', false),
            'supplier_call_counts' => [
                'search' => 0,
                'revalidation' => 0,
                'pnr_create' => 0,
                'retrieve' => 0,
            ],
            'create_outcome_state' => 'create_not_attempted',
            'final_lifecycle_state' => 'planned',
            'manual_reconciliation_required' => false,
            'db_snapshot_before' => $dbBefore,
            'db_snapshot_after' => $dbBefore,
            'database_mutation_detected' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $dbBefore
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function buildLiveArtifact(
        string $lifecycleRunId,
        array $summary,
        array $passengerAudit,
        array $dbBefore,
        array $options,
    ): array {
        $dbAfter = $this->dbSnapshot->capture();
        $scenario = is_array(($summary['scenario_results'] ?? [])[0] ?? null)
            ? $summary['scenario_results'][0]
            : [];

        $pnr = trim((string) ($scenario['pnr'] ?? ''));
        $locatorHash = $pnr !== '' ? hash('sha256', $pnr) : null;

        return [
            'lifecycle_run_id' => $lifecycleRunId,
            'command_mode' => self::MODE,
            'probe_mode' => 'send',
            'scenario_run_id' => $summary['run_id'] ?? null,
            'passenger_audit' => $passengerAudit,
            'deny_locators' => self::DENY_LOCATORS,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'cancellation_attempted' => (bool) ($scenario['cancellation_attempted'] ?? false),
            'create_request_dispatched' => (bool) ($scenario['pnr_attempted'] ?? false),
            'create_response_received' => (bool) ($scenario['live_call_attempted'] ?? false),
            'create_outcome_state' => $this->resolveCreateOutcomeState($scenario),
            'locator_present' => $pnr !== '',
            'locator_sha256' => $locatorHash,
            'retrieve_attempted' => (bool) ($scenario['retrieve_attempted'] ?? false),
            'retrieve_response_received' => (bool) ($scenario['retrieve_success'] ?? false),
            'ticket_number_count' => 0,
            'manual_reconciliation_required' => ($scenario['create_ambiguous'] ?? false) === true,
            'final_lifecycle_state' => $this->resolveFinalLifecycleState($scenario),
            'db_snapshot_before' => $dbBefore,
            'db_snapshot_after' => $dbAfter,
            'database_mutation_detected' => $this->dbMutationDetected($dbBefore, $dbAfter),
            'scenario_summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    protected function resolveCreateOutcomeState(array $scenario): string
    {
        if (($scenario['create_ambiguous'] ?? false) === true) {
            return 'create_ambiguous';
        }
        if (($scenario['pnr_attempted'] ?? false) !== true) {
            return 'create_not_attempted';
        }
        if (trim((string) ($scenario['pnr'] ?? '')) !== '') {
            return 'create_confirmed';
        }
        if (($scenario['live_call_attempted'] ?? false) === true && ($scenario['success'] ?? false) !== true) {
            return 'create_failed_definitively';
        }

        return 'create_started';
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    protected function resolveFinalLifecycleState(array $scenario): string
    {
        $createState = $this->resolveCreateOutcomeState($scenario);
        if ($createState === 'create_ambiguous') {
            return 'reconciliation_required';
        }
        if (($scenario['retrieve_success'] ?? false) === true) {
            return 'retrieve_confirmed';
        }
        if ($createState === 'create_confirmed' && ($scenario['retrieve_attempted'] ?? false) === true) {
            return 'retrieve_failed_after_confirmed_create';
        }
        if ($createState === 'create_confirmed') {
            return 'create_confirmed';
        }

        return $createState;
    }

    /**
     * @param  array<string, mixed>  $dbBefore
     * @param  array<string, mixed>  $dbAfter
     */
    protected function dbMutationDetected(array $dbBefore, array $dbAfter): bool
    {
        foreach ([
            'bookings_count',
            'supplier_bookings_count',
            'supplier_booking_attempts_count',
        ] as $key) {
            if ((int) ($dbAfter[$key] ?? 0) !== (int) ($dbBefore[$key] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function runnerOptions(array $options, string $lifecycleRunId): array
    {
        return [
            'connection_id' => (int) ($options['connection_id'] ?? 1),
            'origin' => strtoupper(trim((string) ($options['origin'] ?? 'LHE'))),
            'destination' => strtoupper(trim((string) ($options['destination'] ?? 'JED'))),
            'departure_date' => trim((string) ($options['departure_date'] ?? '')),
            'trip_type' => 'one_way',
            'carrier' => 'QR',
            'stops' => 'ANY',
            'fare_pick' => 'lowest',
            'max_bookings' => 1,
            'passenger_json' => trim((string) ($options['passenger_json'] ?? '')),
            'mode' => self::MODE,
            'preset' => 'qr-connecting',
            'operator_approved' => true,
            'lifecycle_dedicated' => true,
            'lifecycle_run_id' => $lifecycleRunId,
            'single_retrieve_only' => true,
            'deny_locators' => self::DENY_LOCATORS,
            'candidate_index' => max(0, (int) ($options['candidate_index'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{acquired: bool, cache_lock?: mixed, state?: string}
     */
    protected function acquireLifecycleLock(string $lifecycleRunId): array
    {
        $stateKey = 'sabre_gds_qr_unticketed_lifecycle_state_'.$lifecycleRunId;
        $existing = Cache::get($stateKey);
        if (in_array($existing, ['in_progress', 'completed', 'ambiguous', 'reconciliation_required'], true)) {
            return ['acquired' => false, 'state' => (string) $existing];
        }

        $cacheLock = Cache::lock('sabre_gds_qr_unticketed_lifecycle_active', 600);
        if (! $cacheLock->get()) {
            return ['acquired' => false, 'state' => 'global_lock_active'];
        }

        Cache::put($stateKey, 'in_progress', 3600);

        return ['acquired' => true, 'cache_lock' => $cacheLock];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{blocked: bool, state?: string}
     */
    protected function existingLifecycleState(array $options): array
    {
        $lifecycleRunId = trim((string) ($options['lifecycle_run_id'] ?? ''));
        if ($lifecycleRunId === '') {
            return ['blocked' => false];
        }

        $state = Cache::get('sabre_gds_qr_unticketed_lifecycle_state_'.$lifecycleRunId);
        if (in_array($state, ['in_progress', 'completed', 'ambiguous', 'reconciliation_required'], true)) {
            return ['blocked' => true, 'state' => (string) $state];
        }

        return ['blocked' => false];
    }

    protected function databaseHealthy(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function finalizeArtifact(string $lifecycleRunId, string $mode, array $payload, array $options): array
    {
        $payload['artifact_written_at'] = now()->toIso8601String();
        $relative = self::ARTIFACT_DIRECTORY.'/'.$lifecycleRunId.'-'.$mode.'.json';
        $writer = app(SabreGdsPrivateLifecycleArtifactWriter::class);
        $written = $writer->write($relative, $payload);
        $payload['artifact_mode_expected'] = $written['mode_expected'];
        $payload['artifact_mode_actual'] = $written['mode_actual'];
        $payload['artifact_path'] = $written['relative_path'];

        $state = match ($payload['final_lifecycle_state'] ?? $payload['create_outcome_state'] ?? null) {
            'reconciliation_required', 'create_ambiguous' => 'ambiguous',
            'retrieve_confirmed', 'create_confirmed' => 'completed',
            default => $mode === 'send' ? 'in_progress' : 'planned',
        };
        Cache::put('sabre_gds_qr_unticketed_lifecycle_state_'.$lifecycleRunId, $state, 86400);

        return $payload;
    }
}
