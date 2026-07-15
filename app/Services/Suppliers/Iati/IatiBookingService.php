<?php

namespace App\Services\Suppliers\Iati;

use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Iati\Exceptions\IatiException;
use App\Services\Suppliers\Iati\Exceptions\IatiUnavailableException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\Bookings\IatiPersistedContextResolver;
use App\Support\Bookings\IatiReservationLifecycleService;
use App\Support\Bookings\IatiSelectedOfferReadiness;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IATI booking orchestrator: fare → option/book → persist → immediate order sync.
 */
class IatiBookingService
{
    public function __construct(
        private readonly IatiClient $client,
        private readonly IatiConfigResolver $configResolver,
        private readonly IatiPayloadBuilder $payloadBuilder,
        private readonly IatiResponseNormalizer $normalizer,
        private readonly IatiRetrieveService $retrieveService,
        private readonly IatiSelectedOfferKeyResolver $offerKeyResolver,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
        private readonly IatiReservationLifecycleService $reservationLifecycle,
        private readonly IatiPassengerNormalizer $passengerNormalizer,
    ) {}

    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData
    {
        if ($connection->provider !== SupplierProvider::Iati) {
            return $this->failure('supplier_provider_mismatch', 'Supplier provider mismatch for IATI booking.', $connection);
        }

        $booking->loadMissing(['passengers', 'contact', 'supplierBookings']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        $providerContext = $this->resolveProviderContext($booking, $meta);
        $config = $this->configResolver->resolve($connection);
        $existingOrderId = trim((string) ($iatiContext['order_id'] ?? $booking->supplier_reference ?? ''));
        $convertingOptionToBook = $existingOrderId !== ''
            && $this->shouldBookImmediately($booking)
            && in_array((string) ($iatiContext['mode'] ?? ''), ['option', ''], true);

        if (! $convertingOptionToBook && $this->hasSuccessfulCreateAttempt($booking)) {
            return $this->failure('duplicate_booking_guard', 'An IATI booking already exists for this reservation.', $connection);
        }

        $lifecycleGate = $this->reservationLifecycle->assertSupplierBookAllowed($booking, false);
        if (! ($lifecycleGate['allowed'] ?? false)) {
            return $this->failure(
                (string) ($lifecycleGate['error_code'] ?? 'lifecycle_blocked'),
                (string) ($lifecycleGate['error_message'] ?? 'IATI supplier booking is not allowed for this reservation state.'),
                $connection,
                is_array($lifecycleGate['safe_summary'] ?? null) ? $lifecycleGate['safe_summary'] : [],
            );
        }

        try {
            $this->passengerNormalizer->assertBookingPassengersReady($booking);
        } catch (IatiException $exception) {
            return $this->failure(
                $exception->normalizedCode,
                $exception->safeMessage,
                $connection,
                $exception->context,
            );
        }

        $shouldBook = $this->shouldBookImmediately($booking);
        $skipPreBookRevalidation = $existingOrderId !== '' && $convertingOptionToBook;
        if ($shouldBook && ! $skipPreBookRevalidation) {
            $revalidation = $this->reservationLifecycle->runPreBookRevalidation($booking, $connection);
            if (! ($revalidation['ok'] ?? false)) {
                $code = (string) ($revalidation['error_code'] ?? 'revalidation_failed');
                if ($code === 'fare_changed') {
                    return $this->failure(
                        'fare_change_pending',
                        (string) ($revalidation['error_message'] ?? 'Fare changed — acceptance required before supplier booking.'),
                        $connection,
                    );
                }

                return $this->failure(
                    $code,
                    (string) ($revalidation['error_message'] ?? 'IATI pre-book revalidation failed.'),
                    $connection,
                );
            }
        }

        $this->reservationLifecycle->markSupplierBookingInProgress($booking);

        $fare = null;
        try {
            if ($existingOrderId !== '') {
                return $this->handleExistingOrder($booking, $connection, $actor, $existingOrderId, $iatiContext);
            }

            $farePayload = $this->payloadBuilder->buildFarePayload($providerContext);
            $fareResponse = $this->client->post($connection, '/fare', $farePayload, [
                'request_context' => 'fare_before_book',
                'booking_id' => $booking->id,
                'user_id' => $actor->id,
            ]);
            $fare = $this->normalizer->normalizeFareResponse($fareResponse, $providerContext);

            $passengers = $this->payloadBuilder->buildPassengersFromBooking($booking);
            $contact = $this->payloadBuilder->buildContactFromBooking($booking, $config['organization_id']);
            $selectedOffer = $this->resolveSelectedOffer($fare, $providerContext, $meta);
            $offerKeys = [$selectedOffer['offer_key']];
            $shouldBook = $this->shouldBookImmediately($booking);

            $bookPayload = $shouldBook
                ? $this->payloadBuilder->buildBookPayload($fare, $fare['provider_context'], $passengers, $contact, $offerKeys, true)
                : $this->payloadBuilder->buildOptionPayload($fare, $fare['provider_context'], $passengers, $contact, $offerKeys);

            $path = $shouldBook ? '/book' : '/option';
            $mode = $shouldBook ? 'book' : 'option';

            try {
                $response = $this->client->post($connection, $path, $bookPayload, [
                    'request_context' => $mode,
                    'booking_id' => $booking->id,
                    'user_id' => $actor->id,
                ]);
            } catch (IatiUnavailableException $exception) {
                if ($path === '/option' && $this->isDirectBookRequiredVa009($exception, $selectedOffer)) {
                    return $this->handleDirectBookRequired(
                        $booking,
                        $connection,
                        $actor,
                        $fare,
                        $selectedOffer,
                        $exception,
                    );
                }

                throw $exception;
            }

            $normalized = $this->normalizer->normalizeBookingResponse($response, $mode, $fare['provider_context']);
            $this->persistBookingResult($booking, $connection, $normalized, $actor, $mode, $selectedOffer);
            $booking->refresh();
            if ($mode === 'book') {
                $this->reservationLifecycle->markSupplierBookingConfirmed(
                    $booking,
                    trim((string) ($normalized['provider_booking_reference'] ?? '')),
                    trim((string) ($normalized['pnr'] ?? '')) ?: null,
                );
            } else {
                $this->reservationLifecycle->applySupplierHoldOutcome(
                    $booking,
                    new SupplierBookingResultData(
                        success: true,
                        status: 'pending_ticketing',
                        provider: SupplierProvider::Iati->value,
                        supplier_reference: trim((string) ($normalized['provider_booking_reference'] ?? '')),
                        pnr: trim((string) ($normalized['pnr'] ?? '')) ?: null,
                    ),
                );
            }
            $synced = $this->syncOrderAfterMutation($booking, $connection, $actor, $normalized);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'create_order',
                status: 'success',
                safeMessage: 'IATI '.$mode.' completed.',
                meta: [
                    'booking_id' => $booking->id,
                    'order_id' => $normalized['provider_booking_reference'],
                    'pnr' => $synced['pnr'] ?? $normalized['pnr'],
                ],
            );

            return new SupplierBookingResultData(
                success: true,
                status: $mode === 'book' ? 'created' : 'pending_ticketing',
                provider: SupplierProvider::Iati->value,
                supplier_reference: $normalized['provider_booking_reference'],
                pnr: ($synced['pnr'] ?? $normalized['pnr']) !== '' ? ($synced['pnr'] ?? $normalized['pnr']) : null,
                safe_summary: [
                    'mode' => $mode,
                    'order_id' => $normalized['provider_booking_reference'],
                    'pnr' => $synced['pnr'] ?? $normalized['pnr'],
                    'selected_offer_index' => $selectedOffer['offer_index'],
                ],
            );
        } catch (IatiException $exception) {
            $this->reservationLifecycle->markSupplierBookingFailed($booking, $exception->safeMessage);
            $safeSummary = array_merge(
                ['error_code' => $exception->normalizedCode],
                $exception->context,
            );
            if ($exception->normalizedCode === 'selected_offer_unresolved') {
                $safeSummary = array_merge(
                    $safeSummary,
                    IatiSelectedOfferReadiness::fareConfirmationDiagnostics($booking, is_array($fare) ? $fare : null),
                );
            }
            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'create_order',
                status: 'failed',
                safeMessage: $exception->safeMessage,
                meta: SensitiveDataRedactor::redact([
                    'error_code' => $exception->normalizedCode,
                    'booking_id' => $booking->id,
                    'safe_summary' => $safeSummary,
                ]),
            );

            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Iati->value,
                error_code: $exception->normalizedCode,
                error_message: $exception->safeMessage,
                safe_summary: $safeSummary,
            );
        } catch (\Throwable $exception) {
            $this->reservationLifecycle->markSupplierBookingFailed($booking, 'IATI booking failed. Admin review required.');
            Log::channel('iati')->error('iati.booking.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
            ]);

            return $this->failure('supplier_provider_error', 'IATI booking failed. Admin review required.', $connection);
        }
    }

    protected function handleExistingOrder(
        Booking $booking,
        SupplierConnection $connection,
        User $actor,
        string $orderId,
        array $iatiContext,
    ): SupplierBookingResultData {
        if (! $this->shouldBookImmediately($booking)) {
            return new SupplierBookingResultData(
                success: true,
                status: 'pending_ticketing',
                provider: SupplierProvider::Iati->value,
                supplier_reference: $orderId,
                pnr: trim((string) ($booking->pnr ?? $iatiContext['pnr'] ?? '')) ?: null,
                safe_summary: ['mode' => 'option', 'order_id' => $orderId],
            );
        }

        $path = '/option/'.rawurlencode($orderId).'/book';
        $response = $this->client->post($connection, $path, [], [
            'request_context' => 'option_book',
            'booking_id' => $booking->id,
            'user_id' => $actor->id,
        ]);
        $normalized = $this->normalizer->normalizeBookingResponse($response, 'book', $iatiContext);
        $this->persistBookingResult($booking, $connection, $normalized, $actor, 'book');
        $booking->refresh();
        $synced = $this->syncOrderAfterMutation($booking, $connection, $actor, $normalized);

        return new SupplierBookingResultData(
            success: true,
            status: 'created',
            provider: SupplierProvider::Iati->value,
            supplier_reference: $normalized['provider_booking_reference'] ?: $orderId,
            pnr: ($synced['pnr'] ?? $normalized['pnr']) !== '' ? ($synced['pnr'] ?? $normalized['pnr']) : null,
            safe_summary: ['mode' => 'book', 'order_id' => $orderId],
        );
    }

    /**
     * @param  array<string, mixed>  $fare
     * @param  array<string, mixed>  $providerContext
     * @param  array<string, mixed>  $bookingMeta
     * @return array{offer_key: string, offer_index: int, can_book: bool, selection_reason: string}
     */
    protected function resolveSelectedOffer(array $fare, array $providerContext, array $bookingMeta): array
    {
        return $this->offerKeyResolver->resolve($fare, $providerContext, $bookingMeta);
    }

    /**
     * @param  array<string, mixed>  $fare
     * @param  array{offer_key: string, offer_index: int, can_book: bool, selection_reason: string}  $selectedOffer
     */
    protected function handleDirectBookRequired(
        Booking $booking,
        SupplierConnection $connection,
        User $actor,
        array $fare,
        array $selectedOffer,
        IatiUnavailableException $exception,
    ): SupplierBookingResultData {
        $fareDetailKey = trim((string) ($fare['fare_detail_key'] ?? ''));
        $providerContext = is_array($fare['provider_context'] ?? null) ? $fare['provider_context'] : [];

        DB::transaction(function () use ($booking, $connection, $actor, $fareDetailKey, $selectedOffer, $providerContext, $exception): void {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $meta['iati_context'] = array_merge(
                is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [],
                [
                    'mode' => 'deferred_book',
                    'deferred_book_after_payment' => true,
                    'deferred_error_code' => 'VA009',
                    'deferred_reason' => 'Direct book required after payment/admin approval.',
                    'fare_detail_key' => $fareDetailKey,
                    'selected_offer_index' => $selectedOffer['offer_index'],
                    'selected_offer_key' => $selectedOffer['offer_key'],
                    'last_provider_error' => $exception->safeMessage,
                    'last_sync_at' => now()->toIso8601String(),
                ],
                array_filter([
                    'fare_offers' => $providerContext['fare_offers'] ?? null,
                ], fn ($value) => $value !== null),
            );
            $meta['supplier_provider'] = SupplierProvider::Iati->value;
            $meta['supplier_connection_id'] = $connection->id;

            $booking->update([
                'supplier' => SupplierProvider::Iati->value,
                'meta' => $meta,
            ]);

            SupplierBooking::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'provider' => SupplierProvider::Iati->value,
                ],
                [
                    'agency_id' => $booking->agency_id,
                    'supplier_connection_id' => $connection->id,
                    'supplier_reference' => null,
                    'supplier_api_booking_id' => null,
                    'pnr' => null,
                    'status' => 'direct_book_required',
                    'raw_summary' => SensitiveDataRedactor::redact([
                        'mode' => 'deferred_book',
                        'deferred_error_code' => 'VA009',
                        'selected_offer_index' => $selectedOffer['offer_index'],
                    ]),
                    'created_by' => $actor->id,
                    'created_at_supplier' => now(),
                ],
            );
        });

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'create_order',
            status: 'deferred',
            safeMessage: 'IATI direct book required after payment/admin approval.',
            meta: ['booking_id' => $booking->id, 'deferred_error_code' => 'VA009'],
        );

        return new SupplierBookingResultData(
            success: true,
            status: 'direct_book_required',
            provider: SupplierProvider::Iati->value,
            error_code: 'direct_book_required',
            error_message: 'Direct book required after payment/admin approval.',
            safe_summary: [
                'mode' => 'deferred_book',
                'deferred_error_code' => 'VA009',
                'selected_offer_index' => $selectedOffer['offer_index'],
            ],
        );
    }

    /**
     * @param  array{offer_key: string, offer_index: int, can_book: bool, selection_reason: string}  $selectedOffer
     */
    protected function isDirectBookRequiredVa009(IatiUnavailableException $exception, array $selectedOffer): bool
    {
        $providerCode = strtoupper(trim((string) ($exception->context['provider_code'] ?? '')));

        return $providerCode === 'VA009' && ($selectedOffer['can_book'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    protected function syncOrderAfterMutation(
        Booking $booking,
        SupplierConnection $connection,
        User $actor,
        array $normalized,
    ): array {
        $orderId = trim((string) ($normalized['provider_booking_reference'] ?? ''));
        if ($orderId === '') {
            return [];
        }

        try {
            return $this->retrieveService->syncBooking($booking, $connection, $actor, $normalized);
        } catch (\Throwable $exception) {
            Log::channel('iati')->warning('iati.booking.post_mutation_sync_failed', [
                'booking_id' => $booking->id,
                'order_id' => $orderId,
                'exception' => $exception::class,
            ]);

            return [];
        }
    }

    protected function shouldBookImmediately(Booking $booking): bool
    {
        return (string) ($booking->payment_status ?? 'unpaid') === 'paid';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function resolveProviderContext(Booking $booking, array $meta): array
    {
        return IatiPersistedContextResolver::resolveProviderContext($meta, $booking);
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array{offer_key: string, offer_index: int, can_book: bool, selection_reason: string}|null  $selectedOffer
     */
    protected function persistBookingResult(
        Booking $booking,
        SupplierConnection $connection,
        array $normalized,
        User $actor,
        string $mode,
        ?array $selectedOffer = null,
    ): void {
        DB::transaction(function () use ($booking, $connection, $normalized, $actor, $mode, $selectedOffer): void {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $iatiContext = array_merge(
                is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [],
                is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [],
                [
                    'mode' => $mode,
                    'order_id' => trim((string) ($normalized['provider_booking_reference'] ?? '')),
                    'last_sync_at' => now()->toIso8601String(),
                    'last_provider_error' => null,
                    'deferred_book_after_payment' => false,
                    'deferred_error_code' => null,
                    'deferred_reason' => null,
                ],
            );
            if ($selectedOffer !== null) {
                $iatiContext['selected_offer_index'] = $selectedOffer['offer_index'];
                $iatiContext['selected_offer_key'] = $selectedOffer['offer_key'];
            }
            if (($normalized['last_ticketing_date'] ?? '') !== '') {
                $iatiContext['last_ticketing_date'] = $normalized['last_ticketing_date'];
            }
            if (($normalized['ticketing_status'] ?? '') !== '') {
                $iatiContext['ticketing_status'] = $normalized['ticketing_status'];
            }
            if (($normalized['status'] ?? '') !== '') {
                $iatiContext['status'] = $normalized['status'];
            }

            $meta['iati_context'] = $iatiContext;
            $meta['supplier_provider'] = SupplierProvider::Iati->value;
            $meta['supplier_connection_id'] = $connection->id;

            $booking->update([
                'supplier' => SupplierProvider::Iati->value,
                'supplier_reference' => $normalized['provider_booking_reference'] ?: $booking->supplier_reference,
                'supplier_api_booking_id' => $normalized['provider_booking_reference'] ?: $booking->supplier_api_booking_id,
                'pnr' => $normalized['pnr'] !== '' ? $normalized['pnr'] : $booking->pnr,
                'meta' => $meta,
            ]);

            SupplierBooking::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'provider' => SupplierProvider::Iati->value,
                ],
                [
                    'agency_id' => $booking->agency_id,
                    'supplier_connection_id' => $connection->id,
                    'supplier_reference' => $normalized['provider_booking_reference'],
                    'supplier_api_booking_id' => $normalized['provider_booking_reference'],
                    'pnr' => $normalized['pnr'] !== '' ? $normalized['pnr'] : null,
                    'status' => $mode === 'book' ? 'created' : 'pending_ticketing',
                    'raw_summary' => SensitiveDataRedactor::redact([
                        'mode' => $mode,
                        'order_id' => $normalized['provider_booking_reference'],
                        'pnr' => $normalized['pnr'],
                        'status' => $normalized['status'],
                    ]),
                    'created_by' => $actor->id,
                    'created_at_supplier' => now(),
                ],
            );
        });
    }

    protected function hasSuccessfulCreateAttempt(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        $orderId = trim((string) ($iatiContext['order_id'] ?? $booking->supplier_reference ?? ''));

        if (($iatiContext['mode'] ?? '') === 'deferred_book' && $orderId === '') {
            return false;
        }

        $hasBlockingSupplierBooking = $booking->supplierBookings()
            ->where('provider', SupplierProvider::Iati->value)
            ->whereIn('status', ['created', 'pending_ticketing', 'ticketed'])
            ->exists();

        if ($orderId !== '' && $hasBlockingSupplierBooking) {
            return true;
        }

        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Iati->value)
            ->where('action', 'create_pnr')
            ->whereIn('status', ['success', 'created'])
            ->exists()
            || $hasBlockingSupplierBooking;
    }

    protected function failure(string $code, string $message, SupplierConnection $connection, array $safeSummary = []): SupplierBookingResultData
    {
        return new SupplierBookingResultData(
            success: false,
            status: 'failed',
            provider: SupplierProvider::Iati->value,
            error_code: $code,
            error_message: $message,
            safe_summary: array_merge(['error_code' => $code], $safeSummary),
        );
    }
}
