<?php

namespace App\Services\Suppliers\OneApi\Reservation;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use App\Support\OneApi\OneApiWorkflowContextGuard;

/**
 * Reservation read with ownership enforcement and safe parsing.
 */
class OneApiReservationReadOrchestrator
{
    public function __construct(
        private readonly OneApiReadRequestBuilder $requestBuilder,
        private readonly OneApiRetrieveService $retrieveService,
        private readonly OneApiReadResponseParser $responseParser,
        private readonly OneApiWorkflowContextStore $workflowContextStore,
        private readonly OneApiWorkflowContextGuard $workflowGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $diagnosticContext
     * @return array<string, mixed>
     */
    public function readForActor(
        User $actor,
        SupplierConnection $connection,
        string $pnr,
        ?Booking $booking = null,
        array $diagnosticContext = [],
    ): array {
        if ($booking !== null) {
            if ((int) $booking->agency_id !== (int) ($actor->current_agency_id ?? 0)
                && ! $actor->isPlatformAdmin()) {
                throw new OneApiValidationException('forbidden', 403, 'Reservation read is not allowed.');
            }
            if ((int) ($connection->agency_id ?? 0) > 0
                && (int) ($actor->current_agency_id ?? 0) !== (int) $connection->agency_id
                && ! $actor->isPlatformAdmin()) {
                throw new OneApiValidationException('forbidden', 403, 'Reservation read is not allowed.');
            }
        }

        $contextId = (string) ($diagnosticContext['workflow_session_key'] ?? 'read:'.$pnr);
        $meta = is_array($booking?->meta) ? $booking->meta : [];
        $workflowId = (string) ($meta['one_api_context']['workflow_context_id'] ?? '');
        if ($workflowId !== '') {
            $context = $this->workflowContextStore->get($workflowId);
            if ($context !== null) {
                $this->workflowGuard->authorizeHttp($actor, $connection, $context, $contextId, $booking?->id);
            }
        }

        $xml = (string) ($diagnosticContext['read_request_xml'] ?? '');
        if ($xml === '') {
            $xml = $this->requestBuilder->build($connection, $pnr);
            $diagnosticContext['read_request_xml'] = $xml;
        }

        $parsed = $this->retrieveService->getReservationByPnr($connection, $pnr, $contextId, $diagnosticContext);

        return $this->responseParser->parse($parsed);
    }
}
