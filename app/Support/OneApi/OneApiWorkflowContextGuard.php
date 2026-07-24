<?php

namespace App\Support\OneApi;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\OneApi\Exceptions\OneApiValidationException;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContext;
use App\Services\Suppliers\OneApi\Workflow\OneApiWorkflowContextStore;
use Carbon\CarbonImmutable;

/**
 * Enforces multi-dimensional ownership for One API workflow contexts.
 */
class OneApiWorkflowContextGuard
{
    public function __construct(
        private readonly OneApiWorkflowContextStore $store,
    ) {}

    /**
     * @throws OneApiValidationException
     */
    public function authorizeHttp(
        User $user,
        SupplierConnection $connection,
        OneApiWorkflowContext $context,
        string $rawSessionId,
        ?int $bookingId = null,
    ): OneApiWorkflowContext {
        $this->assertConnection($connection, $context);
        $this->assertNotExpired($context);
        $this->assertLifecycleActive($context);
        if ($context->ownerUserId !== null && (int) $context->ownerUserId !== (int) $user->id) {
            $this->deny();
        }
        $this->assertSignedOfferIntegrity($context);
        $this->assertAgencyScope($user, $connection, $context);
        $this->assertSessionBinding($context, $rawSessionId);
        $this->assertSoapCookieContext($context);
        $this->assertBookingBinding($context, $bookingId);
        $context = $this->assertOrClaimUser($user, $context);
        $this->store->put($context);

        return $context;
    }

    /**
     * @throws OneApiValidationException
     */
    public function authorizeBookingMutation(
        Booking $booking,
        User $actor,
        SupplierConnection $connection,
        OneApiWorkflowContext $context,
    ): void {
        $this->assertConnection($connection, $context);
        $this->assertSoapCookieContext($context);
        $this->assertNotExpired($context);
        if ($context->lifecycleStatus === 'locked_ambiguous') {
            $this->deny();
        }
        if ($context->lifecycleStatus === 'completed') {
            $this->deny();
        }
        if ($context->ownerUserId !== null && (int) $context->ownerUserId !== (int) $actor->id) {
            $this->deny();
        }
        if ($context->agencyId !== null && (int) $context->agencyId !== (int) $booking->agency_id) {
            $this->deny();
        }
        if ($context->bookingId !== null && (int) $context->bookingId !== (int) $booking->id) {
            $this->deny();
        }
        if ($context->bookingId === null) {
            $context->bookingId = (int) $booking->id;
            $this->store->put($context);
        }
    }

    /**
     * @throws OneApiValidationException
     */
    public function authorizeInternalFixtureRunner(
        SupplierConnection $connection,
        OneApiWorkflowContext $context,
    ): void {
        if (! $this->allowsInternalBypass()) {
            $this->deny();
        }
        $this->assertConnection($connection, $context);
        $this->assertNotExpired($context);
        $this->assertLifecycleActive($context);
    }

    private function allowsInternalBypass(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        if (! OneApiFixtureTransportScope::isExplicitlyEnabled()) {
            return false;
        }

        $reason = OneApiFixtureTransportScope::reason();

        return $reason === 'matrix_command' || $reason === 'fixture_command';
    }

    /**
     * Reject client-submitted transaction identifiers that do not match the workflow context (stale TID).
     *
     * @throws OneApiValidationException
     */
    public function assertTransactionIdentifierMatches(OneApiWorkflowContext $context, ?string $submittedTransactionIdentifier): void
    {
        $submitted = trim((string) $submittedTransactionIdentifier);
        $current = trim((string) ($context->transactionIdentifier ?? ''));
        if ($submitted === '' || $current === '') {
            return;
        }
        if (! hash_equals($current, $submitted)) {
            $this->deny();
        }
    }

    /**
     * SOAP price/book requires a persisted cookie jar once a supplier transaction identifier exists.
     *
     * @throws OneApiValidationException
     */
    public function assertSoapCookieContext(OneApiWorkflowContext $context): void
    {
        $tid = trim((string) ($context->transactionIdentifier ?? ''));
        if ($tid === '') {
            return;
        }
        if ($context->cookieJar === []) {
            $this->deny();
        }
    }

    /**
     * @throws OneApiValidationException
     */
    private function assertConnection(SupplierConnection $connection, OneApiWorkflowContext $context): void
    {
        if ((int) $connection->id !== (int) $context->connectionId) {
            $this->deny();
        }
    }

    /**
     * @throws OneApiValidationException
     */
    private function assertNotExpired(OneApiWorkflowContext $context): void
    {
        $expires = $context->expiresAtIso;
        if ($expires === null || $expires === '') {
            return;
        }
        if (CarbonImmutable::parse($expires)->isPast()) {
            $this->deny();
        }
    }

    /**
     * @throws OneApiValidationException
     */
    private function assertLifecycleActive(OneApiWorkflowContext $context): void
    {
        if (! in_array($context->lifecycleStatus, ['active'], true)) {
            $this->deny();
        }
    }

    /**
     * @throws OneApiValidationException
     */
    private function assertSignedOfferIntegrity(OneApiWorkflowContext $context): void
    {
        if ($context->signedOfferFingerprint === '') {
            return;
        }
        $current = OneApiWorkflowFingerprint::signedOffer($context->signedOfferPayload);
        if (! hash_equals($context->signedOfferFingerprint, $current)) {
            $this->deny();
        }
    }

    /**
     * @throws OneApiValidationException
     */
    private function assertAgencyScope(User $user, SupplierConnection $connection, OneApiWorkflowContext $context): void
    {
        $connectionAgencyId = (int) ($connection->agency_id ?? 0);
        if ($connectionAgencyId > 0 && (int) ($user->current_agency_id ?? 0) !== $connectionAgencyId) {
            $this->deny();
        }
        if ($context->agencyId !== null && $connectionAgencyId > 0 && (int) $context->agencyId !== $connectionAgencyId) {
            $this->deny();
        }
    }

    /**
     * @throws OneApiValidationException
     */
    private function assertSessionBinding(OneApiWorkflowContext $context, string $rawSessionId): void
    {
        $fingerprint = OneApiWorkflowFingerprint::session($rawSessionId);
        if ($context->sessionFingerprint === null || $context->sessionFingerprint === '') {
            $context->sessionFingerprint = $fingerprint;

            return;
        }
        if (! hash_equals($context->sessionFingerprint, $fingerprint)) {
            $this->deny();
        }
    }

    /**
     * @throws OneApiValidationException
     */
    private function assertBookingBinding(OneApiWorkflowContext $context, ?int $bookingId): void
    {
        if ($bookingId === null) {
            return;
        }
        if ($context->bookingId !== null && (int) $context->bookingId !== $bookingId) {
            $this->deny();
        }
    }

    /**
     * @throws OneApiValidationException
     */
    private function assertOrClaimUser(User $user, OneApiWorkflowContext $context): OneApiWorkflowContext
    {
        if ($context->ownerUserId === null) {
            $context->ownerUserId = (int) $user->id;
            if ($context->agencyId === null) {
                $context->agencyId = (int) ($user->current_agency_id ?? 0) ?: null;
            }

            return $context;
        }
        if ((int) $context->ownerUserId !== (int) $user->id) {
            $this->deny();
        }

        return $context;
    }

    /**
     * @throws OneApiValidationException
     */
    private function deny(): never
    {
        throw new OneApiValidationException('workflow_not_found', 404, 'Workflow is not available.');
    }
}
