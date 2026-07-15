<?php

namespace App\Services\Suppliers\Sabre\Ticketing;

use App\Data\TicketingResultData;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabrePnrItinerarySyncService;
use App\Support\Bookings\SupplierBookingAttemptGuard;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sabre GDS Enhanced Air Ticket orchestration — dry-run default; live gated with duplicate protection.
 */
final class SabreGdsTicketingService
{
    public function __construct(
        private readonly SabreGdsTicketingReadiness $readiness,
        private readonly SabreGdsTicketingRequestBuilder $requestBuilder,
        private readonly SabreGdsTicketingResponseParser $responseParser,
        private readonly SabreGdsTicketingSafeErrorNormalizer $errorNormalizer,
        private readonly SabreGdsTicketingAuditLogger $auditLogger,
        private readonly SabreClient $sabreClient,
        private readonly SabreGdsTicketDocumentService $ticketDocumentService,
        private readonly SabrePnrItinerarySyncService $pnrItinerarySyncService,
        private readonly PlatformModuleEnforcer $platformModuleEnforcer,
        private readonly SupplierBookingAttemptGuard $attemptGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function issueTickets(
        Booking $booking,
        SupplierConnection $connection,
        User $actor,
        array $options = [],
    ): TicketingResultData {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $confirm = isset($options['confirm']) ? (string) $options['confirm'] : null;

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if ($this->platformModuleEnforcer->isSabreNdcDistributionChannel(
            $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta)
        )) {
            return $this->blockedResult('sabre_ndc_channel', 'Sabre NDC bookings use separate ticketing services.');
        }

        $readiness = $this->readiness->evaluate($booking, [
            'dry_run' => $dryRun,
            'require_confirmation' => ! $dryRun,
            'confirmation_provided' => SabreGdsTicketingReadiness::confirmPhraseMatches($booking, $confirm),
            'allow_unsafe_retry' => (bool) ($options['allow_unsafe_retry'] ?? false),
        ]);

        if (($readiness['ticketed'] ?? false) === true) {
            return $this->blockedResult('already_ticketed', 'Tickets have already been issued for this booking.', ! $dryRun, $booking, $connection, $actor);
        }

        if ($this->readiness->isSupplierTicketingInProgress($booking, $meta)) {
            return $this->blockedResult('ticketing_in_progress', 'Sabre ticketing is already in progress. Do not call supplier again.', ! $dryRun, $booking, $connection, $actor);
        }

        if (($readiness['cancelled'] ?? false) === true) {
            return $this->blockedResult('booking_cancelled', 'Cancelled bookings cannot be ticketed.', ! $dryRun, $booking, $connection, $actor);
        }

        if (! $this->readiness->actorMayIssue($actor) && ! $dryRun) {
            return $this->blockedResult('permission_denied', 'Staff or admin permission required for ticketing.', true, $booking, $connection, $actor);
        }

        if ($dryRun || ! ($readiness['live_supplier_call_allowed'] ?? false)) {
            $this->auditLogger->log('sabre.gds_ticketing.dry_run', $booking, $actor, [
                'blockers' => $readiness['blockers'] ?? [],
                'dry_run' => $dryRun,
            ]);

            if (! $dryRun) {
                $this->persistBlockedAttempt($booking, $connection, $actor, $readiness['blockers'] ?? [], 'ticketing_blocked');
            }

            $message = $dryRun
                ? 'Dry-run only — no supplier ticketing call attempted.'
                : 'Ticketing blocked by readiness gates.';

            return new TicketingResultData(
                success: false,
                status: $dryRun ? 'dry_run' : 'blocked',
                provider: SupplierProvider::Sabre->value,
                error_code: $dryRun ? 'dry_run' : 'ticketing_blocked',
                error_message: $message,
                safe_summary: [
                    'live_supplier_call_attempted' => false,
                    'blockers' => $readiness['blockers'] ?? [],
                    'confirm_phrase' => $readiness['confirm_phrase'] ?? '',
                ],
                warnings: $readiness['warnings'] ?? [],
            );
        }

        if ($connection->provider !== SupplierProvider::Sabre) {
            return $this->blockedResult('supplier_provider_mismatch', 'Supplier connection is not Sabre.', ! $dryRun, $booking, $connection, $actor);
        }

        $built = $this->requestBuilder->build($booking);
        if ($built['missing'] !== []) {
            return $this->blockedResult('payload_incomplete', 'Ticketing payload prerequisites are incomplete.', ! $dryRun, $booking, $connection, $actor);
        }

        $lock = Cache::lock($this->lockKey($booking), 600);
        if (! $lock->get()) {
            return $this->blockedResult('ticketing_in_progress', 'Sabre ticketing is already in progress. Do not call supplier again.', ! $dryRun, $booking, $connection, $actor);
        }

        try {
            return $this->runLockedIssue($booking, $connection, $actor, $built['payload']);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<string>  $blockers
     */
    protected function persistBlockedAttempt(
        Booking $booking,
        SupplierConnection $connection,
        User $actor,
        array $blockers,
        string $errorCode,
    ): void {
        $pnr = trim((string) ($booking->pnr ?? $booking->supplier_reference ?? ''));

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'issue_ticket',
            'status' => 'blocked',
            'supplier_reference' => $pnr !== '' ? $pnr : null,
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
            'error_code' => $errorCode,
            'error_message' => 'Ticketing blocked by readiness gates.',
            'safe_summary' => SensitiveDataRedactor::redact([
                'source' => 'sabre_gds_ticketing_workflow',
                'live_supplier_call_attempted' => false,
                'blockers' => array_values(array_slice($blockers, 0, 8)),
            ]),
        ]);
    }

    /**
     * Post-booking persistence: sync, documents, meta, audit (TicketingService owns ticket rows).
     *
     * @param  array<string, mixed>  $parsed
     */
    public function finalizeSuccessfulIssue(Booking $booking, User $actor, array $parsed, array $connectionContext = []): void
    {
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if ($this->readiness->isTicketed($booking, $meta)) {
            $ticketingMeta = is_array($meta[SabreGdsTicketingReadiness::META_KEY] ?? null)
                ? $meta[SabreGdsTicketingReadiness::META_KEY]
                : [];
            if (in_array((string) ($ticketingMeta['status'] ?? ''), ['ticketed', 'issued'], true)) {
                return;
            }
        }

        $syncSlice = $this->postIssueSync($booking);
        $documentsSlice = $this->retrieveTicketDocuments($booking, $connectionContext);

        DB::transaction(function () use ($booking, $actor, $parsed, $syncSlice, $documentsSlice): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $tickets = is_array($parsed['tickets'] ?? null) ? $parsed['tickets'] : [];

            $meta[SabreGdsTicketingReadiness::META_KEY] = SensitiveDataRedactor::redact([
                'status' => 'ticketed',
                'ticketed_at' => now()->toIso8601String(),
                'ticket_count' => count($tickets),
                'ticket_numbers' => array_values(array_filter(array_map(
                    static fn ($row) => is_array($row) ? (string) ($row['ticket_number'] ?? '') : '',
                    $tickets,
                ))),
                'post_issue_sync_status' => (string) ($syncSlice['status'] ?? 'not_attempted'),
                'post_issue_synced' => (bool) ($syncSlice['synced'] ?? false),
                'ticket_documents_retrieved' => (bool) ($documentsSlice['retrieved'] ?? false),
            ]);
            $booking->forceFill([
                'meta' => $meta,
                'supplier_booking_status' => 'ticketed',
            ])->save();

            AuditLog::query()->create([
                'agency_id' => $booking->agency_id,
                'user_id' => $actor->id,
                'action' => 'booking.sabre_gds_ticket_issued',
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'properties' => [
                    'old_values' => [],
                    'new_values' => [
                        'ticket_count' => count($tickets),
                        'post_issue_sync_status' => $syncSlice['status'] ?? null,
                        'ticket_documents_retrieved' => $documentsSlice['retrieved'] ?? false,
                    ],
                ],
            ]);
        });

        $this->auditLogger->log('sabre.gds_ticketing.finalized', $booking, $actor, [
            'ticket_count' => count($parsed['tickets'] ?? []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function previewReadiness(Booking $booking, ?User $actor = null): array
    {
        return $this->readiness->evaluate($booking, ['dry_run' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function runLockedIssue(
        Booking $booking,
        SupplierConnection $connection,
        User $actor,
        array $payload,
    ): TicketingResultData {
        $attemptId = $this->markInProgress($booking, $connection, $actor);

        $pnr = trim((string) ($booking->pnr ?? $booking->supplier_reference ?? ''));
        $path = (string) config('suppliers.sabre.ticketing_path', '/v1.3.0/air/ticket');

        try {
            $this->auditLogger->log('sabre.gds_ticketing.live_attempt', $booking, $actor, [
                'endpoint_path' => $path,
            ]);

            /** @var Response $response */
            $response = $this->sabreClient->postAuthenticatedJson($connection, $path, $payload);
            $json = $response->json();
            $json = is_array($json) ? $json : [];

            if (! $response->successful()) {
                $this->clearInProgress($booking, 'failed');
                $error = $this->errorNormalizer->fromHttpResponse($response->status(), $json);
                $safeSummary = array_merge($error['safe_summary'], [
                    'endpoint_path' => $path,
                    'live_supplier_call_attempted' => true,
                ]);

                $this->finalizeIssueTicketAttempt(
                    $attemptId,
                    'failed',
                    $pnr,
                    $error['error_code'],
                    $error['error_message'],
                    $safeSummary,
                    SensitiveDataRedactor::redact($payload),
                    SensitiveDataRedactor::redact($json),
                );

                return new TicketingResultData(
                    success: false,
                    status: 'failed',
                    provider: SupplierProvider::Sabre->value,
                    error_code: $error['error_code'],
                    error_message: $error['error_message'],
                    safe_summary: $safeSummary,
                    request_payload: SensitiveDataRedactor::redact($payload),
                    response_payload: SensitiveDataRedactor::redact($json),
                );
            }

            $parsed = $this->responseParser->parse($json, $pnr);
            $safeSummary = array_merge($parsed['safe_summary'] ?? [], [
                'endpoint_path' => $path,
                'http_status' => $response->status(),
                'live_supplier_call_attempted' => true,
            ]);

            if (! $parsed['success']) {
                $this->clearInProgress($booking, 'failed');
                $errorCode = (string) ($parsed['error_code'] ?? 'ticketing_parse_failed');
                $errorMessage = (string) ($parsed['error_message'] ?? 'Sabre ticketing response could not be parsed.');

                $this->finalizeIssueTicketAttempt(
                    $attemptId,
                    'failed',
                    $pnr,
                    $errorCode,
                    $errorMessage,
                    $safeSummary,
                    SensitiveDataRedactor::redact($payload),
                    SensitiveDataRedactor::redact($json),
                );

                return new TicketingResultData(
                    success: false,
                    status: 'failed',
                    provider: SupplierProvider::Sabre->value,
                    error_code: $errorCode,
                    error_message: $errorMessage,
                    tickets: [],
                    safe_summary: $safeSummary,
                    request_payload: SensitiveDataRedactor::redact($payload),
                    response_payload: SensitiveDataRedactor::redact($json),
                );
            }

            $this->finalizeIssueTicketAttempt(
                $attemptId,
                'success',
                $pnr,
                null,
                null,
                $safeSummary,
                SensitiveDataRedactor::redact($payload),
                SensitiveDataRedactor::redact($json),
            );

            return new TicketingResultData(
                success: true,
                status: $parsed['status'],
                provider: SupplierProvider::Sabre->value,
                tickets: $parsed['tickets'],
                safe_summary: $safeSummary,
                request_payload: SensitiveDataRedactor::redact($payload),
                response_payload: SensitiveDataRedactor::redact($json),
            );
        } catch (\Throwable $exception) {
            $this->clearInProgress($booking, 'failed');
            $error = $this->errorNormalizer->fromThrowable($exception);
            $safeSummary = array_merge($error['safe_summary'], [
                'endpoint_path' => $path,
                'live_supplier_call_attempted' => true,
            ]);
            $this->auditLogger->log('sabre.gds_ticketing.unexpected', $booking, $actor, [
                'exception' => $exception::class,
            ]);

            $this->finalizeIssueTicketAttempt(
                $attemptId,
                'failed',
                $pnr,
                $error['error_code'],
                $error['error_message'],
                $safeSummary,
                SensitiveDataRedactor::redact($payload),
                null,
            );

            return new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Sabre->value,
                error_code: $error['error_code'],
                error_message: $error['error_message'],
                safe_summary: $safeSummary,
                request_payload: SensitiveDataRedactor::redact($payload),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $connectionContext
     * @return array<string, mixed>
     */
    protected function retrieveTicketDocuments(Booking $booking, array $connectionContext): array
    {
        $connectionId = (int) ($connectionContext['supplier_connection_id'] ?? data_get($booking->meta, 'supplier_connection_id') ?? 0);
        if ($connectionId <= 0) {
            return ['retrieved' => false, 'status' => 'connection_missing'];
        }

        $connection = SupplierConnection::query()->find($connectionId);
        if ($connection === null) {
            return ['retrieved' => false, 'status' => 'connection_missing'];
        }

        try {
            $result = $this->ticketDocumentService->retrieve($booking, $connection, false);

            return [
                'retrieved' => is_array($result['documents'] ?? null) && $result['documents'] !== [],
                'status' => (string) ($result['status'] ?? 'retrieved'),
            ];
        } catch (\Throwable) {
            return ['retrieved' => false, 'status' => 'retrieve_failed'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function postIssueSync(Booking $booking): array
    {
        try {
            $sync = $this->pnrItinerarySyncService->sync($booking, false);

            return [
                'attempted' => true,
                'synced' => (bool) ($sync['synced'] ?? false),
                'status' => (string) ($sync['reason_code'] ?? ($sync['synced'] ?? false ? 'synced' : 'not_synced')),
            ];
        } catch (\Throwable) {
            return [
                'attempted' => true,
                'synced' => false,
                'status' => 'sync_failed',
            ];
        }
    }

    protected function markInProgress(Booking $booking, SupplierConnection $connection, User $actor): int
    {
        DB::transaction(function () use ($booking): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $meta[SabreGdsTicketingReadiness::META_KEY] = array_merge(
                is_array($meta[SabreGdsTicketingReadiness::META_KEY] ?? null) ? $meta[SabreGdsTicketingReadiness::META_KEY] : [],
                [
                    'status' => 'in_progress',
                    'started_at' => now()->toIso8601String(),
                ],
            );
            $booking->forceFill(['meta' => $meta])->save();
        });

        $pnr = trim((string) ($booking->pnr ?? $booking->supplier_reference ?? ''));
        $attempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'issue_ticket',
            'status' => 'started',
            'supplier_reference' => $pnr !== '' ? $pnr : null,
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'safe_summary' => [
                'source' => 'sabre_gds_ticketing_workflow',
                'phase' => 'started',
                'live_supplier_call_attempted' => false,
            ],
        ]);

        $this->attemptGuard->setInFlightAttemptId($attempt->id);

        return (int) $attempt->id;
    }

    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     */
    protected function finalizeIssueTicketAttempt(
        int $attemptId,
        string $terminalStatus,
        string $pnr,
        ?string $errorCode,
        ?string $errorMessage,
        array $safeSummary,
        ?array $requestPayload = null,
        ?array $responsePayload = null,
    ): void {
        $status = match ($terminalStatus) {
            'success' => 'success',
            'failed' => 'failed',
            default => 'failed',
        };

        SupplierBookingAttempt::query()
            ->whereKey($attemptId)
            ->update([
                'status' => $status,
                'supplier_reference' => $pnr !== '' ? $pnr : null,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'request_payload' => $requestPayload,
                'response_payload' => $responsePayload,
                'safe_summary' => SensitiveDataRedactor::redact(array_merge($safeSummary, [
                    'source' => 'sabre_gds_ticketing_workflow',
                    'phase' => 'completed',
                    'terminal_status' => $terminalStatus,
                ])),
                'completed_at' => now(),
            ]);

        SupplierBookingAttemptGuard::resetInFlightAttemptId();
    }

    protected function clearInProgress(Booking $booking, string $terminalStatus): void
    {
        DB::transaction(function () use ($booking, $terminalStatus): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $existing = is_array($meta[SabreGdsTicketingReadiness::META_KEY] ?? null)
                ? $meta[SabreGdsTicketingReadiness::META_KEY]
                : [];

            if (($existing['status'] ?? '') !== 'ticketed') {
                $meta[SabreGdsTicketingReadiness::META_KEY] = array_merge($existing, [
                    'status' => $terminalStatus,
                    'ended_at' => now()->toIso8601String(),
                ]);
                $booking->forceFill(['meta' => $meta])->save();
            }
        });
    }

    protected function lockKey(Booking $booking): string
    {
        return 'ota:sabre-gds-ticketing:'.$booking->id;
    }

    private function blockedResult(
        string $code,
        string $message,
        bool $persistAttempt = false,
        ?Booking $booking = null,
        ?SupplierConnection $connection = null,
        ?User $actor = null,
    ): TicketingResultData {
        if ($persistAttempt && $booking !== null && $connection !== null && $actor !== null) {
            $this->persistBlockedAttempt($booking, $connection, $actor, [$code], $code);
        }

        return new TicketingResultData(
            success: false,
            status: 'blocked',
            provider: SupplierProvider::Sabre->value,
            error_code: $code,
            error_message: $message,
            safe_summary: ['live_supplier_call_attempted' => false],
        );
    }
}
