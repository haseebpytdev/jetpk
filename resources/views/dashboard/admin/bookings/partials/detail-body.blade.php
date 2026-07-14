@php
    use App\Http\Controllers\Admin\BookingManagementController;
    use App\Support\Bookings\BookingItineraryOverviewPresenter;
    use App\Support\Bookings\BookingOperationalStatus;
    use App\Support\Bookings\DocumentOperationalState;
    use App\Support\Bookings\PaymentOperationalStatus;
    use App\Support\Bookings\PiaNdcVoidLocalReconciliation;
    use App\Support\Bookings\SabreOfferRefreshAcceptance;
    use App\Support\Bookings\SabrePassengerRecordsItineraryGuardDigest;
    use App\Support\Bookings\SupplierOperationalStatus;
    use App\Support\Bookings\TicketingOperationalStatus;
    use App\Support\Bookings\TicketingReadinessPresenter;
    use App\Support\Identity\ActorIdentifier;
@endphp
    @if (session('status'))
        @php
            $statusMessage = match (session('status')) {
                'manual-pnr-marked' => 'Manual PNR saved. Supplier reference is on file; ticketing remains manual/disabled until separately actioned.',
                'staff-assigned' => 'Staff assignment updated.',
                'supplier-booking-created' => 'Supplier booking action completed.',
                'supplier-pnr-context-prepared' => 'Sabre pricing context prepared (no live PNR created).',
                'cancellation-processed-manual-review' => session('cancellation_warning') ?: 'Cancellation was not confirmed by supplier. Booking status was not changed.',
                default => session('status'),
            };
        @endphp
        <div class="jp-alert jp-alert--success alert-dismissible" role="alert">
            {{ $statusMessage }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="jp-alert jp-alert--danger">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $booking->loadMissing('latestSupplierBookingAttempt');
        $hasPnrOrReference = ((string) ($booking->pnr ?? '')) !== ''
            || ((string) ($booking->supplier_reference ?? '')) !== '';
        $latestSupplierAttempt = $booking->latestSupplierBookingAttempt;
        $latestAttemptSafe = is_array($latestSupplierAttempt?->safe_summary) ? $latestSupplierAttempt->safe_summary : null;
        $latestAttemptHttpStatus = $latestAttemptSafe !== null
            ? (int) ($latestAttemptSafe['http_status'] ?? 0)
            : null;
        $tooManyRequestsByMessage = BookingOperationalStatus::safeSummaryIndicatesTooManyRequests($latestAttemptSafe);
        $pipelineBooking = str_replace('_', ' ', $booking->status->value);
        $operationalStatus = BookingOperationalStatus::fromValues(
            $booking->status->value,
            (string) ($booking->payment_status ?? ''),
            (string) ($booking->supplier_booking_status ?? ''),
            (string) ($booking->ticketing_status ?? ''),
            $hasPnrOrReference,
            (string) ($booking->cancellation_status ?? ''),
            (string) ($latestSupplierAttempt?->status ?? ''),
            $latestSupplierAttempt?->error_code,
            $latestAttemptHttpStatus > 0 ? $latestAttemptHttpStatus : null,
            $tooManyRequestsByMessage,
        );
        $paymentOperational = PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
        $pipelinePayment = $paymentOperational['label'];
        $supplierOperational = SupplierOperationalStatus::fromValues(
            (string) ($booking->supplier_booking_status ?? 'not_started'),
            (string) (($booking->meta['supplier_provider'] ?? null) ?: ($booking->latestSupplierBooking?->provider ?? $booking->supplier ?? '')),
            $hasPnrOrReference,
            is_array($booking->meta) ? $booking->meta : null,
        );
        $pipelineSupplier = $supplierOperational['label'];
        $piaProviderEarly = strtolower(trim((string) (($booking->meta['supplier_provider'] ?? null) ?: ($booking->latestSupplierBooking?->provider ?? $booking->supplier ?? '')))) === 'pia_ndc';
        $piaNdcTicketVoidedEarly = $piaProviderEarly && PiaNdcVoidLocalReconciliation::isVoided($booking);
        $piaNdcVoidRequiresReviewEarly = $piaProviderEarly && PiaNdcVoidLocalReconciliation::requiresVoidReview($booking);
        $hasActiveIssuedTickets = $booking->tickets->contains(fn ($ticket) => strtolower((string) ($ticket->status ?? '')) !== 'voided');
        $ticketingOperational = TicketingOperationalStatus::fromValues(
            (string) ($booking->ticketing_status ?? 'not_started'),
            (string) ($booking->payment_status ?? 'unpaid'),
            $hasPnrOrReference,
            $hasActiveIssuedTickets && ! $piaNdcTicketVoidedEarly,
            (string) (($booking->meta['supplier_provider'] ?? null) ?: ($booking->latestSupplierBooking?->provider ?? $booking->supplier ?? '')),
            (string) ($booking->cancellation_status ?? '')
        );
        $pipelineTicket = $ticketingOperational['label'];
        $sabrePassengerRecordsGuard = SabrePassengerRecordsItineraryGuardDigest::fromBooking($booking);
        $itineraryOverview = BookingItineraryOverviewPresenter::fromBookingMeta(
            is_array($booking->meta) ? $booking->meta : null,
            $hasPnrOrReference,
        );
        $bookingRef = $booking->booking_reference ?: 'Draft #'.$booking->id;
        $travelDateLabel = $booking->travel_date?->format('d M Y') ?? display_unknown();
        $paxCount = $booking->passengers->count();
        $fareTotal = (float) ($booking->fareBreakdown?->total ?? 0);
        $totalDue = \App\Support\Payments\BookingPayableResolver::customerPayableTotal($booking);
        $promoDiscountAmount = (float) ($booking->promo_discount_amount ?? 0);
        $paidAmount = (float) ($booking->amount_paid ?? 0);
        $balanceAmount = $booking->balance_due !== null ? (float) $booking->balance_due : max(0, $totalDue - $paidAmount);
        $paymentStoreUrl = $p === 'staff' ? route('staff.bookings.payments.store', $booking) : route('admin.bookings.payments.store', $booking);
        $bookingRoute = $p === 'staff' ? route('staff.bookings.supplier-booking', $booking) : route('admin.bookings.supplier-booking', $booking);
        $ticketRoute = $p === 'staff' ? route('staff.bookings.issue-ticket', $booking) : route('admin.bookings.issue-ticket', $booking);
        $provider = (string) (($booking->meta['supplier_provider'] ?? null) ?: ($booking->latestSupplierBooking?->provider ?? $booking->supplier ?? ''));
        $isPiaNdcProvider = strtolower($provider) === 'pia_ndc';
        $providerSupportsPnr = in_array($provider, ['duffel', 'sabre', 'pia_ndc', 'airblue', 'airline_direct', 'amadeus', 'travelport', 'iati'], true);
        $providerSupportsTicketing = in_array($provider, ['sabre', 'pia_ndc', 'airblue', 'airline_direct', 'iati'], true);
        $hasSupplierPnr = ((string) ($booking->pnr ?? '')) !== '';
        $hasSupplierBooking = $booking->supplierBookings->contains(fn ($item) => in_array($item->status, ['created', 'pending_ticketing', 'ticketed'], true));
        $verifiedPayment = $booking->payments->firstWhere('status.value', 'verified');
        $isPaidForActions = (string) ($booking->payment_status ?? 'unpaid') === 'paid';
        $isTicketedForActions = ! $piaNdcTicketVoidedEarly && ($hasActiveIssuedTickets || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true));
        $canCreateSupplierBooking = $providerSupportsPnr && ($supplierBookingEligible ?? false) && ! $hasSupplierBooking;
        $canIssueTicket = $providerSupportsTicketing && ($ticketingEligible ?? false);
        $canGenerateItinerary = $booking->tickets->isNotEmpty();
        $canCreatePnrNow = $canCreateSupplierBooking && $isPaidForActions && ! $isTicketedForActions;
        $canIssueTicketNow = $canIssueTicket && $isPaidForActions && $hasSupplierPnr && ! $isTicketedForActions;
        $supplierReason = !$providerSupportsPnr
            ? 'Reason: Supplier provider does not support automated PNR creation yet.'
            : (($supplierBookingEligible ?? false) ? ($hasSupplierBooking ? 'Reason: Supplier booking already exists for this booking.' : '') : 'Reason: Offer validation and booking prerequisites are not complete.');
        $ticketReason = !$providerSupportsTicketing
            ? 'Reason: Ticketing for this provider is not integrated yet.'
            : (($ticketingEligible ?? false) ? '' : 'Reason: Payment must be verified and supplier PNR must exist.');
        $itineraryReason = $canGenerateItinerary ? '' : 'Reason: No issued ticket found yet.';
        $leadPax = $booking->passengers->firstWhere('is_lead_passenger', true) ?? $booking->passengers->sortBy('passenger_index')->first();
        $leadPaxName = $leadPax ? trim(implode(' ', array_filter([$leadPax->title, $leadPax->first_name, $leadPax->last_name]))) : display_unknown();
        $contactLine = $booking->contact ? (($booking->contact->phone ?? display_unknown()).' / '.($booking->contact->email ?? display_unknown())) : display_unknown();
        $hasContact = $booking->contact !== null && (((string) ($booking->contact->email ?? '')) !== '' || ((string) ($booking->contact->phone ?? '')) !== '');
        $isCancelledOrRefunded = in_array((string) $booking->status->value, ['cancelled'], true)
            || in_array((string) ($booking->refund_status ?? ''), ['refunded'], true);
        $hasFareSnapshot = $booking->fareBreakdown !== null;
        $iatiOfferValidation = (($iatiDiagnostic['show'] ?? false) && is_array($iatiDiagnostic['offer_validation'] ?? null))
            ? $iatiDiagnostic['offer_validation']
            : null;
        $offerValid = $iatiOfferValidation !== null
            ? (bool) ($iatiOfferValidation['show_as_valid'] ?? false)
            : in_array((string) (($booking->meta['offer_validation_status'] ?? 'unknown')), ['valid', 'validated', 'ok', 'pass'], true);
        $adminOverrideAllowed = $p === 'admin';
        $alreadyTicketed = ! $piaNdcTicketVoidedEarly && ($hasActiveIssuedTickets || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true));
        $canRecordPaymentAction = ! $isCancelledOrRefunded;
        $canGenerateInvoiceAction = $hasFareSnapshot;
        $canCreatePnrAction = ($isPaidForActions || $adminOverrideAllowed) && $offerValid && $providerSupportsPnr && ($supplierBookingEligible ?? false);
        $canIssueTicketAction = $isPaidForActions && $hasSupplierPnr && ! $alreadyTicketed && $providerSupportsTicketing && ($ticketingEligible ?? false);
        $canGenerateItineraryAction = $canGenerateItinerary;
        $canSendUpdateAction = $hasContact;
        $canAssignStaffAction = $assignUrl !== null && $p === 'admin';
        $canChangeStatusAction = count($allowedTransitions) > 0;
        $canAddNoteAction = true;
        $hasUnpaidOrPartialBalance = in_array((string) ($booking->payment_status ?? 'unpaid'), ['unpaid', 'partial'], true)
            || (float) ($booking->balance_due ?? 0) > 0;
        $hasInvoiceDocument = $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'invoice');
        $hasReceiptDocument = $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'payment_receipt');
        $hasItineraryDocument = $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'ticket_itinerary') || $booking->tickets->isNotEmpty();
        $hasCancellationDocument = $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'cancellation_confirmation')
            || $booking->cancellationRequests->isNotEmpty();
        $hasRefundDocument = $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'refund_note')
            || $booking->refunds->isNotEmpty();
        $nextActionLabel = $canCreateSupplierBooking
            ? ((string) ($actionState['create_supplier_booking_label'] ?? 'Create supplier booking / PNR'))
            : ($canIssueTicket
                ? ((string) ($actionState['issue_ticket_label'] ?? 'Issue ticket'))
                : (($booking->payment_status ?? 'unpaid') !== 'paid' ? 'Record payment and verify it' : 'Review booking and update status'));
        $actionState = $actionState ?? [
            'next_action' => $nextActionLabel,
            'enabled_actions' => [],
            'disabled_actions' => [],
            'disabled_reasons' => [],
            'workflow_step_statuses' => [],
        ];
        $stateEnabled = is_array($actionState['enabled_actions'] ?? null) ? $actionState['enabled_actions'] : [];
        $stateDisabledReasons = is_array($actionState['disabled_reasons'] ?? null) ? $actionState['disabled_reasons'] : [];
        $stateWorkflow = is_array($actionState['workflow_step_statuses'] ?? null) ? $actionState['workflow_step_statuses'] : [];
        $nextActionText = (string) ($actionState['next_action'] ?? $nextActionLabel);
        $bookingShowUrl = $p === 'staff' ? route('staff.bookings.show', $booking) : route('admin.bookings.show', $booking);
        $overviewPrimaryActionLabel = (string) ($actionState['next_action'] ?? 'Open full record');
        $primaryTab = (string) ($actionState['primary_cta_tab'] ?? 'overview');
        $primaryHash = (string) ($actionState['primary_cta_hash'] ?? '');
        $overviewPrimaryActionUrl = $primaryHash !== ''
            ? $bookingShowUrl.'?tab='.$primaryTab.'#'.$primaryHash
            : ($primaryTab !== 'overview' ? $bookingShowUrl.'?tab='.$primaryTab : $bookingShowUrl);
        $sa = $supplierActions ?? null;
    @endphp
    @include('dashboard.admin.bookings.partials.detail-header')

    @include('dashboard.admin.bookings.partials.detail-tabs-nav')

    <div class="row g-4 booking-detail" data-booking-tab-container>
        <div class="col-12 col-xl-10 mx-auto ota-booking-detail">
            <div class="jp-card" data-tab-section="overview">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Operational summary</h3></div>
                <div class="jp-card__body">
                    <div class="overview-summary-grid">
                        <div class="overview-kv"><span class="label">Booking status</span><span class="value text-capitalize">{{ $pipelineBooking }}</span></div>
                        <div class="overview-kv"><span class="label">Operational status</span><span class="value text-capitalize">{{ $operationalStatus['label'] }}</span></div>
                        <div class="overview-kv"><span class="label">Payment status</span><span class="value text-capitalize">{{ $pipelinePayment }}</span></div>
                        <div class="overview-kv"><span class="label">Supplier status</span><span class="value text-capitalize">{{ $pipelineSupplier }}</span></div>
                        <div class="overview-kv"><span class="label">Ticketing status</span><span class="value text-capitalize">{{ $pipelineTicket }}</span></div>
                        @php $offerRefreshAdmin = SabreOfferRefreshAcceptance::adminSummary($booking); @endphp
                        @if ($offerRefreshAdmin['label'] !== '')
                            <div class="overview-kv"><span class="label">{{ $offerRefreshAdmin['label'] }}</span><span class="value">
                                @if ($offerRefreshAdmin['old_amount'] !== null && $offerRefreshAdmin['new_amount'] !== null)
                                    {{ $offerRefreshAdmin['currency'] }} {{ number_format($offerRefreshAdmin['old_amount'], 0) }}
                                    {{ display_sep_dot() }}{{ number_format($offerRefreshAdmin['new_amount'], 0) }}
                                    @if ($offerRefreshAdmin['delta'] !== null)
                                        ({{ display_sep_dot() }}{{ number_format($offerRefreshAdmin['delta'], 0) }})
                                    @endif
                                @else
                                    --
                                @endif
                            </span></div>
                            <div class="overview-kv"><span class="label">Customer acceptance</span><span class="value">{{ $offerRefreshAdmin['accepted'] ? 'Accepted' : 'Pending acceptance' }}</span></div>
                        @endif
                        <div class="overview-kv"><span class="label">Route</span><span class="value">{{ $booking->route ?? display_unknown() }}</span></div>
                        @if ($itineraryOverview)
                            <div class="overview-kv"><span class="label">Itinerary source</span><span class="value">{{ $itineraryOverview['itinerary_source_label'] ?? display_unknown() }}</span></div>
                        @endif
                        <div class="overview-kv"><span class="label">Journey (airports)</span><span class="value">{{ $itineraryOverview['journey_od'] ?? display_unknown() }}</span></div>
                        <div class="overview-kv"><span class="label">Connections</span><span class="value">{{ $itineraryOverview['stops_label'] ?? display_unknown() }}</span></div>
                        <div class="overview-kv"><span class="label">Itinerary detail</span><span class="value text-end" style="white-space: pre-line;">{{ $itineraryOverview ? implode("\n", $itineraryOverview['segment_lines']) : display_unknown() }}</span></div>
                        <div class="overview-kv"><span class="label">Fare</span><span class="value">Rs {{ number_format($totalDue, 0) }}</span></div>
                        <div class="overview-kv"><span class="label">Passenger count</span><span class="value">{{ $paxCount }}</span></div>
                        <div class="overview-kv"><span class="label">Lead passenger</span><span class="value">{{ $leadPaxName }}</span></div>
                        <div class="overview-kv"><span class="label">Contact</span><span class="value">{{ $contactLine }}</span></div>
                        <div class="overview-kv"><span class="label">Internal ID</span><span class="value">#{{ $booking->id }}</span></div>
                        <div class="overview-kv"><span class="label">Created at</span><span class="value"><x-time.local :value="$booking->created_at" context="operator" /></span></div>
                        <div class="overview-kv"><span class="label">Updated at</span><span class="value"><x-time.local :value="$booking->updated_at" context="operator" /></span></div>
                        <div class="overview-kv"><span class="label">Supplier API booking ID</span><span class="value">{{ display_unknown($booking->supplier_api_booking_id) }}</span></div>
                        @php
                            $overviewPrs = is_array($sa['pnr_retrieve_safety'] ?? null)
                                ? $sa['pnr_retrieve_safety']
                                : \App\Support\Bookings\PnrItinerarySyncSafetyPresenter::forBooking($booking);
                            $overviewIsSabre = ($sa['is_sabre'] ?? false)
                                || strtolower((string) (data_get($booking->meta, 'supplier_provider') ?: ($booking->supplier ?? ''))) === 'sabre';
                        @endphp
                        <div class="overview-kv">
                            <span class="label">{{ $overviewIsSabre ? 'Sabre / GDS PNR' : 'PNR' }}</span>
                            <span class="value">{{ display_unknown($overviewPrs['sabre_pnr_label'] ?? $booking->pnr ?? null) }}</span>
                        </div>
                        @if ($overviewIsSabre)
                            <div class="overview-kv">
                                <span class="label">Airline / carrier locator</span>
                                <span class="value">{{ $overviewPrs['airline_locator_display'] ?? 'Not recorded yet' }}</span>
                            </div>
                            <div class="overview-kv">
                                <span class="label">Sabre retrieve ticketing</span>
                                <span class="value">{{ $overviewPrs['ticketing_status_label'] ?? 'Pending / not ticketed' }}</span>
                            </div>
                            @if (! empty($overviewPrs['verification_note']))
                                <div class="overview-kv">
                                    <span class="label">PNR sync note</span>
                                    <span class="value text-end text-warning">{{ $overviewPrs['verification_note'] }}</span>
                                </div>
                            @endif
                        @endif
                        <div class="overview-kv"><span class="label">PNR / payment deadline</span><span class="value"><x-time.local :value="$booking->pnr_expires_at ?? $booking->payment_required_by" context="operator" /></span></div>
                        <div class="overview-kv"><span class="label">Fare revalidated at</span><span class="value"><x-time.local :value="$booking->fare_revalidated_at" context="operator" /></span></div>
                        <div class="overview-kv"><span class="label">Fare change accepted</span><span class="value"><x-time.local :value="$booking->fare_change_accepted_at" context="operator" /></span></div>
                        <div class="overview-kv"><span class="label">Selected / revalidated fare</span><span class="value">{{ $booking->selected_fare_total !== null || $booking->revalidated_fare_total !== null ? number_format((float) ($booking->selected_fare_total ?? 0), 0) . display_sep_dot() . number_format((float) ($booking->revalidated_fare_total ?? 0), 0).' '.strtoupper((string) ($booking->currency ?? 'PKR')) : display_unknown() }}</span></div>
                        <div class="overview-kv"><span class="label">Confirmation / payment mode</span><span class="value">{{ str_replace('_', ' ', (string) ($booking->confirmation_method ?? data_get($booking->meta, 'booking_method', '--'))) }}</span></div>
                        <div class="overview-kv"><span class="label">Assigned staff</span><span class="value">{{ $booking->assignedStaff?->name ?? 'Unassigned' }}</span></div>
                        <div class="overview-kv"><span class="label">Next recommended action</span><span class="value">{{ $actionState['next_action'] ?? $nextActionLabel }}</span></div>
                    </div>
                    @if ($itineraryOverview && ($itineraryOverview['show_snapshot_itinerary_warning'] ?? false))
                        <div class="jp-alert jp-alert--info mt-3 mb-0" role="alert">
                            <p class="small mb-2">PNR is created, but displayed itinerary is based on the selected offer snapshot. Verify final airline schedule before ticketing.</p>
                            @if ($sa !== null && ($sa['can_sync_pnr_itinerary'] ?? false))
                                <form method="post" action="{{ $syncPnrItineraryRoute }}" class="mb-0">
                                    @csrf
                                    <button type="submit" class="jp-btn jp-btn--outline btn-sm">{{ $sa['sync_pnr_itinerary_label'] ?? 'Sync PNR itinerary' }}</button>
                                </form>
                            @endif
                        </div>
                    @endif
                    @if ($sabrePassengerRecordsGuard)
                        <div class="jp-alert jp-alert--warn mt-3 mb-0" role="alert">
                            <div class="fw-semibold">{{ $sabrePassengerRecordsGuard['headline'] }}: {{ $sabrePassengerRecordsGuard['reason'] }}</div>
                            <ul class="small mb-2 mt-2">
                                <li><strong>{{ __('Reason') }}:</strong> {{ $sabrePassengerRecordsGuard['reason'] }}</li>
                                <li><strong>{{ __('Trigger') }}:</strong> {{ $sabrePassengerRecordsGuard['guard_trigger'] }}</li>
                                <li><strong>{{ __('Segment count') }}:</strong> {{ $sabrePassengerRecordsGuard['segment_count'] }}</li>
                                <li><strong>{{ __('Segment order corrected') }}:</strong> {{ $sabrePassengerRecordsGuard['segment_order_corrected'] }}</li>
                                <li><strong>{{ __('Live Sabre call attempted') }}:</strong> {{ $sabrePassengerRecordsGuard['live_call_attempted'] }}</li>
                                <li><strong>{{ __('PNR') }}:</strong> {{ $sabrePassengerRecordsGuard['pnr'] }}</li>
                                <li><strong>{{ __('Ticketing') }}:</strong> {{ $sabrePassengerRecordsGuard['ticketing'] }}</li>
                            </ul>
                            <p class="small mb-0"><strong>{{ __('Suggested action') }}:</strong> {{ $sabrePassengerRecordsGuard['suggested_action'] }}</p>
                        </div>
                    @endif
                    @php
                        $overviewLatestSupplierAttempt = $booking->supplierBookingAttempts->sortByDesc('id')->first();
                        $overviewAttemptSafe = is_array($overviewLatestSupplierAttempt?->safe_summary ?? null) ? $overviewLatestSupplierAttempt->safe_summary : [];
                        $overviewSabreAppErrors = strtolower((string) $provider) === 'sabre'
                            && isset($overviewAttemptSafe['response_error_codes'])
                            && is_array($overviewAttemptSafe['response_error_codes'])
                            && count($overviewAttemptSafe['response_error_codes']) > 0;
                    @endphp
                    @if ($overviewLatestSupplierAttempt && (filled($overviewLatestSupplierAttempt->error_code) || $overviewSabreAppErrors) && ! ($sa['has_pnr_or_reference'] ?? false))
                        <div class="jp-alert jp-alert--warn mt-3 mb-0" role="alert">
                            <div class="fw-semibold">{{ __('Latest supplier booking attempt') }}</div>
                            <div class="small mb-0">
                                {{ __('Status') }}: <span class="text-capitalize">{{ str_replace('_', ' ', (string) $overviewLatestSupplierAttempt->status) }}</span>
                                @if (filled($overviewLatestSupplierAttempt->error_code))
                                   {{ display_sep_dot() }}{{ __('Error') }}: <code class="user-select-all">{{ $overviewLatestSupplierAttempt->error_code }}</code>
                                @endif
                                @if (filled($overviewLatestSupplierAttempt->error_message))
                                    <span class="d-block mt-1">{{ $overviewLatestSupplierAttempt->error_message }}</span>
                                @endif
                                @if ($overviewSabreAppErrors)
                                    <span class="d-block mt-1"><strong>{{ __('Sabre application response') }}:</strong>
                                        {{ implode(', ', array_map(static fn ($c) => (string) $c, $overviewAttemptSafe['response_error_codes'])) }}</span>
                                    @if (! empty($overviewAttemptSafe['response_error_messages']) && is_array($overviewAttemptSafe['response_error_messages']))
                                        <span class="d-block mt-1">{{ implode(display_sep_dot(), array_map(static fn ($m) => (string) $m, array_slice($overviewAttemptSafe['response_error_messages'], 0, 3))) }}</span>
                                    @endif
                                    @if (! empty($overviewAttemptSafe['response_missing_fields']) && is_array($overviewAttemptSafe['response_missing_fields']))
                                        <span class="d-block mt-1"><strong>{{ __('Reported missing fields') }}:</strong>
                                            {{ implode(display_sep_dot(), array_map(static fn ($m) => (string) $m, array_slice($overviewAttemptSafe['response_missing_fields'], 0, 8))) }}</span>
                                    @endif
                                    @if (! empty($overviewAttemptSafe['response_error_fields']) && is_array($overviewAttemptSafe['response_error_fields']))
                                        <span class="d-block mt-1"><strong>{{ __('Field hints') }}:</strong>
                                            {{ implode(display_sep_dot(), array_map(static fn ($m) => (string) $m, array_slice($overviewAttemptSafe['response_error_fields'], 0, 8))) }}</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endif
                    @if ($stateWorkflow !== [])
                        <div class="small text-secondary mt-2">
                            @foreach ($stateWorkflow as $step => $status)
                                <div><strong>{{ str_replace('_', ' ', (string) $step) }}:</strong> <span class="text-capitalize">{{ str_replace('_', ' ', (string) $status) }}</span></div>
                            @endforeach
                        </div>
                    @endif
                    @php
                        $hasLeadContact = $booking->contact !== null && ((string) ($booking->contact->email ?? '') !== '' || (string) ($booking->contact->phone ?? '') !== '');
                        $hasPaxDetails = $booking->passengers->isNotEmpty() && $hasLeadContact;
                        $offerValidated = $iatiOfferValidation !== null
                            ? (bool) ($iatiOfferValidation['show_as_valid'] ?? false)
                            : in_array((string) (($booking->meta['offer_validation_status'] ?? 'unknown')), ['valid', 'validated', 'ok', 'pass'], true);
                        $invoiceGenerated = $booking->documents->contains(fn ($d) => (string) $d->document_type->value === 'invoice');
                        $paymentRecorded = $booking->payments->isNotEmpty();
                        $paymentVerified = in_array((string) ($booking->payment_status ?? 'unpaid'), ['paid', 'partial'], true);
                        $pnrCreated = ((string) ($booking->supplier_booking_status ?? 'not_started') !== 'not_started') || ((string) ($booking->pnr ?? '') !== '');
                        $ticketIssued = in_array((string) ($booking->ticketing_status ?? 'not_started'), ['ticketed', 'issued', 'completed'], true) || $booking->tickets->isNotEmpty();
                        $documentsGenerated = $booking->documents->isNotEmpty();
                        $customerNotified = $booking->communicationLogs->contains(function ($log) {
                            $status = strtolower((string) ($log->status ?? ''));
                            return ($status === 'sent' || $status === 'delivered' || $status === 'success')
                                && (!empty($log->recipient_email) || !empty($log->recipient_phone));
                        });
                        $postBookingSupport = ((string) ($booking->cancellation_status ?? 'none') !== 'none')
                            || ((string) ($booking->refund_status ?? 'none') !== 'none')
                            || $booking->cancellationRequests->isNotEmpty()
                            || $booking->refunds->isNotEmpty();
                        $bookingClosed = in_array((string) $booking->status->value, ['completed', 'closed', 'ticketed', 'cancelled', 'refunded'], true);
                        $lifecycleDone = [
                            'step_1_request_created' => true,
                            'step_2_pax_contact_captured' => $hasPaxDetails,
                            'step_3_fare_offer_validated' => $offerValidated,
                            'step_4_invoice_generated' => $invoiceGenerated,
                            'step_5_payment_submitted_or_recorded' => $paymentRecorded,
                            'step_6_payment_verified' => $paymentVerified,
                            'step_7_pnr_created' => $pnrCreated,
                            'step_8_ticket_issued' => $ticketIssued,
                            'step_9_docs_generated' => $documentsGenerated,
                            'step_10_customer_notified' => $customerNotified,
                            'step_11_post_booking_support' => $postBookingSupport,
                            'step_12_booking_closed' => $bookingClosed,
                        ];
                    @endphp
                    <div class="small text-secondary fw-semibold mt-3">Lifecycle progress</div>
                    <div class="lifecycle-track">
                        <div class="lifecycle-step {{ $lifecycleDone['step_1_request_created'] ? 'is-done' : '' }}">1. Request/draft</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_2_pax_contact_captured'] ? 'is-done' : '' }}">2. Pax/contact</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_3_fare_offer_validated'] ? 'is-done' : '' }}">3. Fare/offer</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_4_invoice_generated'] ? 'is-done' : '' }}">4. Invoice/pay req</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_5_payment_submitted_or_recorded'] ? 'is-done' : '' }}">5. Payment submit</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_6_payment_verified'] ? 'is-done' : '' }}">6. Payment verify</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_7_pnr_created'] ? 'is-done' : '' }}">7. Supplier/PNR</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_8_ticket_issued'] ? 'is-done' : '' }}">8. Ticket issued</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_9_docs_generated'] ? 'is-done' : '' }}">9. Documents</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_10_customer_notified'] ? 'is-done' : '' }}">10. Notified</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_11_post_booking_support'] ? 'is-done' : '' }}">11. Support</div>
                        <div class="lifecycle-step {{ $lifecycleDone['step_12_booking_closed'] ? 'is-done' : '' }}">12. Closed</div>
                    </div>
                </div>
            </div>

            <div class="jp-card" data-tab-section="passengers">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Passengers &amp; contact</h3></div>
                <div class="jp-card__body">
                    @php
                        $hasPassengers = $booking->passengers->isNotEmpty();
                        $isTicketedForPassengerOps = $booking->tickets->isNotEmpty() || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true);
                        $canEditPassengerDetails = ! $isTicketedForPassengerOps || $p === 'admin';
                        $canValidatePassengerData = $hasPassengers;
                        $canAddPassengerNote = in_array($p, ['admin', 'staff'], true);
                    @endphp
                    @if ($canAddPassengerNote)
                        <h4 class="mb-2">Actions</h4>
                        <div class="d-grid gap-2 mb-3">
                            <a href="{{ route('admin.bookings.show', $booking) }}?tab=communication#add-note-panel" class="jp-btn jp-btn--outline w-100">Add internal passenger note</a>
                        </div>
                    @endif

                    <details class="mb-3" data-testid="admin-booking-planned-passenger-actions">
                        <summary class="small text-secondary">Planned passenger actions (not wired)</summary>
                        <div class="d-grid gap-2 mt-2">
                            <button type="button" class="jp-btn jp-btn--ghost btn-sm w-100" disabled>Edit passenger details</button>
                            <button type="button" class="jp-btn jp-btn--ghost btn-sm w-100" disabled>Validate passenger data</button>
                            <button type="button" class="jp-btn jp-btn--ghost btn-sm w-100" disabled>Mark lead passenger</button>
                            <p class="small text-secondary mb-0">Passenger edit, validation, and lead-passenger tools are planned{{ display_sep_dot() }}checkout validation remains the source of truth today.</p>
                        </div>
                    </details>

                    <h4 class="mb-2">Passenger records</h4>
                    @foreach ($booking->passengers->sortBy('passenger_index')->values() as $index => $pax)
                        <div class="passenger-item">
                            <div class="passenger-head">
                                <span class="passenger-name">Passenger {{ $index + 1 }}</span>
                                <span class="badge bg-secondary-lt text-capitalize">{{ $pax->passenger_type ?? 'adult' }}</span>
                                @if($pax->is_lead_passenger)
                                    <span class="badge bg-info-lt">Lead passenger</span>
                                @endif
                                <span class="badge bg-danger-lt">Sensitive</span>
                            </div>
                            <div class="passenger-grid">
                                <div class="passenger-kv"><span class="label">Name</span><span class="value">{{ trim(($pax->title.' '.$pax->first_name.' '.$pax->last_name)) }}</span></div>
                                <div class="passenger-kv"><span class="label">DOB</span><span class="value">{{ $pax->date_of_birth?->format('Y-m-d') ?? display_unknown() }}</span></div>
                                <div class="passenger-kv"><span class="label">Gender</span><span class="value">{{ display_unknown($pax->gender) }}</span></div>
                                <div class="passenger-kv"><span class="label">Nationality</span><span class="value">{{ $pax->nationality ? strtoupper($pax->nationality) : display_unknown() }}</span></div>
                                <div class="passenger-kv"><span class="label">Passport</span><span class="value">{{ display_unknown($pax->passport_number) }}</span></div>
                                <div class="passenger-kv"><span class="label">Passport expiry</span><span class="value">{{ $pax->passport_expiry_date?->format('Y-m-d') ?? display_unknown() }}</span></div>
                                <div class="passenger-kv"><span class="label">Document type</span><span class="value">{{ $pax->document_type ? str_replace('_', ' ', $pax->document_type) : display_unknown() }}</span></div>
                                <div class="passenger-kv"><span class="label">Passport issuing country</span><span class="value">{{ $pax->passport_issuing_country ? strtoupper($pax->passport_issuing_country) : display_unknown() }}</span></div>
                                <div class="passenger-kv"><span class="label">Passport issued</span><span class="value">{{ $pax->passport_issue_date?->format('Y-m-d') ?? display_unknown() }}</span></div>
                                <div class="passenger-kv"><span class="label">National ID</span><span class="value">{{ display_unknown($pax->national_id_number) }}</span></div>
                                <div class="passenger-kv"><span class="label">Country of residence</span><span class="value">{{ display_unknown($pax->country_of_residence) }}</span></div>
                                <div class="passenger-kv"><span class="label">Place of birth</span><span class="value">{{ display_unknown($pax->place_of_birth) }}</span></div>
                                @if ($booking->tickets->isNotEmpty())
                                    @php
                                        $paxTickets = $booking->tickets->where('passenger_id', $pax->id);
                                    @endphp
                                    <div class="passenger-kv"><span class="label">Passenger-ticket mapping</span><span class="value">{{ $paxTickets->isNotEmpty() ? $paxTickets->pluck('ticket_number')->filter()->implode(', ') : 'No ticket mapped' }}</span></div>
                                @endif
                                @if($pax->is_lead_passenger && $booking->contact)
                                    <div class="passenger-kv"><span class="label">Lead contact</span><span class="value">{{ $booking->contact->phone ?? display_unknown() }} / {{ $booking->contact->email ?? display_unknown() }}</span></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @if($booking->contact)
                        <ul class="list-unstyled small text-secondary mb-0 mt-2">
                            <li><i class="ti ti-mail me-1"></i>{{ $booking->contact->email }}</li>
                            <li><i class="ti ti-phone me-1"></i>{{ $booking->contact->phone ?? display_unknown() }}</li>
                            @if($booking->contact->country)
                                <li><i class="ti ti-map-pin me-1"></i>{{ $booking->contact->country }}</li>
                            @endif
                        </ul>
                    @endif
                </div>
            </div>

            <div class="jp-card" data-tab-section="payments">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Fare</h3></div>
                <div class="jp-card__body">
                    @if($booking->fareBreakdown)
                        @php
                            $f = $booking->fareBreakdown;
                            $metaPricing = is_array($booking->meta['pricing_snapshot'] ?? null) ? $booking->meta['pricing_snapshot'] : [];
                            $metaPassengerPricing = is_array($booking->meta['passenger_pricing'] ?? null) ? $booking->meta['passenger_pricing'] : [];
                            $supplierTotal = (float) ($booking->meta['supplier_total'] ?? 0);
                            $fareLineItemsUnreliable = \App\Support\Bookings\BookingItineraryOverviewPresenter::adminStoredFareLineItemsLookUnreliable(
                                (float) $f->base_fare,
                                (float) $f->taxes,
                                $supplierTotal,
                                (float) $f->total,
                            );
                            $passengerFareMissing = $supplierTotal > 0 && empty($metaPassengerPricing);
                            $fxRate = $metaPricing['fx_rate'] ?? null;
                            $holdStatusLabel = (string) ($booking->supplier_hold_status ?? ($booking->meta['supplier_hold_status'] ?? 'not_started'));
                            $priceGuaranteeExpiry = (string) ($booking->price_guarantee_expires_at ?? ($booking->meta['price_guarantee_expires_at'] ?? ''));
                        @endphp
                        @if ($itineraryOverview && ($itineraryOverview['show_fare_snapshot_note'] ?? false))
                            <div class="jp-alert jp-alert--info py-2 px-3 mb-2" role="alert">
                                <p class="small mb-0">Fare breakdown is based on booking snapshot; verify final fare before ticketing.</p>
                            </div>
                        @endif
                        @if ($fareLineItemsUnreliable || $passengerFareMissing)
                            <div class="jp-alert jp-alert--warn" role="alert">
                                @if ($fareLineItemsUnreliable)
                                    <div class="small mb-1">{{ __('Stored base/tax line items may not reflect the full supplier quote; use supplier total and customer totals as the reliable amounts.') }}</div>
                                @endif
                                @if ($passengerFareMissing)
                                    <div class="small mb-0">{{ __('Per-passenger supplier fare breakdown is not stored on this booking.') }}</div>
                                @endif
                            </div>
                        @endif
                        <div class="d-flex justify-content-between"><span>Supplier total</span><span>Rs {{ number_format($supplierTotal, 0) }}</span></div>
                        <div class="d-flex justify-content-between"><span>FX rate</span><span>{{ $fxRate !== null ? number_format((float) $fxRate, 4) : display_unknown() }}</span></div>
                        @if ($fareLineItemsUnreliable)
                            <div class="d-flex justify-content-between small text-secondary mt-1"><span>Base / taxes / markup</span><span>{{ __('Omitted - line items look incomplete vs supplier snapshot') }}</span></div>
                        @else
                            <div class="d-flex justify-content-between"><span>Base</span><span>Rs {{ number_format((float) $f->base_fare, 0) }}</span></div>
                            <div class="d-flex justify-content-between"><span>Taxes</span><span>Rs {{ number_format((float) $f->taxes, 0) }}</span></div>
                            <div class="d-flex justify-content-between"><span>Markup/service fee</span><span>Rs {{ number_format(((float) $f->markup + (float) $f->fees), 0) }}</span></div>
                        @endif
                        <div class="d-flex justify-content-between"><span>Hold status</span><span class="text-capitalize">{{ str_replace('_', ' ', $holdStatusLabel) }}</span></div>
                        <div class="d-flex justify-content-between"><span>Price guarantee expiry</span><span>{{ $priceGuaranteeExpiry !== '' ? \Illuminate\Support\Carbon::parse($priceGuaranteeExpiry)->format('Y-m-d H:i') : display_unknown() }}</span></div>
                        <div class="d-flex justify-content-between fw-bold mt-2 pt-2 border-top"><span>Total customer price</span><span>Rs {{ number_format((float) $f->total, 0) }}</span></div>
                        @if (filled($booking->promo_code))
                            <div class="d-flex justify-content-between text-success"><span>Promo {{ e($booking->promo_code) }}</span><span>−Rs {{ number_format((float) $booking->promo_discount_amount, 0) }}</span></div>
                            <div class="d-flex justify-content-between fw-bold"><span>Final payable</span><span>Rs {{ number_format(\App\Support\Payments\BookingPayableResolver::customerPayableTotal($booking), 0) }}</span></div>
                        @endif
                        <div class="mt-2 pt-2 border-top">
                            <div class="small text-secondary mb-1">Passenger fare breakdown</div>
                            @if (! empty($metaPassengerPricing))
                                @foreach ($metaPassengerPricing as $idx => $pp)
                                    <div class="small d-flex justify-content-between">
                                        <span>Passenger {{ $idx + 1 }} {{ !empty($pp['passenger_type']) ? '('.strtoupper((string) $pp['passenger_type']).')' : '' }}</span>
                                        <span>Rs {{ number_format((float) ($pp['total_amount'] ?? 0), 0) }}</span>
                                    </div>
                                @endforeach
                            @else
                                <div class="small text-secondary">Passenger fare breakdown unavailable from supplier.</div>
                            @endif
                        </div>
                    @else
                        <p class="text-secondary mb-0">No fare breakdown.</p>
                    @endif
                </div>
            </div>

            <div class="jp-card" data-tab-section="audit">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="jp-card__title mb-0">Audit timeline</h3>
                    @if ($p === 'admin')
                        <a href="{{ route('admin.bookings.audit.export', $booking) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Export audit</a>
                    @endif
                </div>
                <div class="jp-card__body">
                    @php
                        $timelineEvents = collect();
                        $timelineEvents->push([
                            'time' => $booking->created_at,
                            'type' => 'booking_created',
                            'title' => 'Booking created',
                            'actor' => 'System',
                            'status' => 'done',
                            'details' => 'Initial booking record created.',
                        ]);
                        if ($booking->passengers->isNotEmpty()) {
                            $timelineEvents->push([
                                'time' => $booking->passengers->max('created_at'),
                                'type' => 'passenger_details_submitted',
                                'title' => 'Passenger details submitted',
                                'actor' => 'Customer/Agent',
                                'status' => 'done',
                                'details' => 'Passenger manifest captured ('.$booking->passengers->count().' pax).',
                            ]);
                        }
                        if (! empty($booking->meta['validated_at'])) {
                            $timelineEvents->push([
                                'time' => \Illuminate\Support\Carbon::parse((string) $booking->meta['validated_at']),
                                'type' => 'offer_validated',
                                'title' => 'Offer validated',
                                'actor' => 'System',
                                'status' => (string) ($booking->meta['offer_validation_status'] ?? 'ok'),
                                'details' => 'Supplier offer validation completed.',
                            ]);
                        }
                        foreach ($booking->documents as $doc) {
                            $docType = (string) $doc->document_type->value;
                            $docTitle = match ($docType) {
                                'invoice' => 'Invoice generated',
                                default => 'Document generated',
                            };
                            $timelineEvents->push([
                                'time' => $doc->generated_at ?? $doc->created_at,
                                'type' => 'document_generated',
                                'title' => $docTitle,
                                'actor' => ActorIdentifier::forUser($doc->generatedBy),
                                'status' => (string) $doc->status->value,
                                'details' => str_replace('_', ' ', $docType).display_sep_dot().($doc->document_number ?? 'No number'),
                            ]);
                        }
                        foreach ($booking->payments as $payment) {
                            $paymentStatus = (string) $payment->status->value;
                            $timelineEvents->push([
                                'time' => $payment->created_at,
                                'type' => 'payment_recorded',
                                'title' => 'Payment recorded',
                                'actor' => ActorIdentifier::forUser($payment->payer),
                                'status' => $paymentStatus,
                                'details' => 'Amount '.$payment->amount.' '.strtoupper((string) ($payment->currency ?? 'PKR')),
                            ]);
                            if (in_array($paymentStatus, ['verified', 'rejected'], true)) {
                                $timelineEvents->push([
                                    'time' => $payment->updated_at,
                                    'type' => 'payment_reviewed',
                                    'title' => 'Payment '.($paymentStatus === 'verified' ? 'verified' : 'rejected'),
                                    'actor' => ActorIdentifier::forUser($payment->receiver),
                                    'status' => $paymentStatus,
                                    'details' => 'Payment review completed.',
                                ]);
                            }
                        }
                        foreach ($booking->supplierBookings as $supplierBooking) {
                            $timelineEvents->push([
                                'time' => $supplierBooking->created_at,
                                'type' => 'supplier_booking_created',
                                'title' => 'Supplier booking created',
                                'actor' => ActorIdentifier::forUser($supplierBooking->createdBy),
                                'status' => (string) $supplierBooking->status,
                                'details' => 'PNR: '.display_unknown($supplierBooking->pnr ?? $booking->pnr ?? null),
                            ]);
                        }
                        foreach ($booking->tickets as $ticket) {
                            $timelineEvents->push([
                                'time' => $ticket->created_at,
                                'type' => 'ticket_issued',
                                'title' => 'Ticket issued',
                                'actor' => ActorIdentifier::forUser($ticket->issuedBy),
                                'status' => (string) (is_object($ticket->status) ? $ticket->status->value : $ticket->status),
                                'details' => 'Ticket number: '.((string) ($ticket->ticket_number ?? display_unknown())),
                            ]);
                        }
                        foreach ($booking->communicationLogs as $comm) {
                            $timelineEvents->push([
                                'time' => $comm->sent_at ?? $comm->created_at,
                                'type' => 'notification_sent',
                                'title' => 'Notification sent',
                                'actor' => ActorIdentifier::forUser($comm->user),
                                'status' => (string) $comm->status,
                                'details' => str_replace('_', ' ', (string) $comm->event).display_sep_dot().strtoupper((string) $comm->channel),
                            ]);
                        }
                        foreach ($booking->statusLogs as $log) {
                            $timelineEvents->push([
                                'time' => $log->created_at,
                                'type' => 'status_changed',
                                'title' => 'Status changed',
                                'actor' => ActorIdentifier::forUser($log->user),
                                'status' => 'done',
                                'details' => str_replace('_', ' ', (string) $log->from_status).' -> '.str_replace('_', ' ', (string) $log->to_status),
                            ]);
                        }
                        if ($booking->assigned_at) {
                            $timelineEvents->push([
                                'time' => $booking->assigned_at,
                                'type' => 'staff_assigned',
                                'title' => 'Staff assigned',
                                'actor' => 'System',
                                'status' => 'done',
                                'details' => 'Assigned to '.ActorIdentifier::forUser($booking->assignedStaff),
                            ]);
                        }
                        foreach ($booking->bookingNotes as $note) {
                            $timelineEvents->push([
                                'time' => $note->created_at,
                                'type' => 'note_added',
                                'title' => 'Note added',
                                'actor' => ActorIdentifier::forUser($note->user),
                                'status' => $note->is_customer_visible ? 'customer_visible' : 'internal',
                                'details' => \Illuminate\Support\Str::limit((string) $note->note, 140),
                            ]);
                        }
                        foreach ($booking->cancellationRequests as $cancellation) {
                            $timelineEvents->push([
                                'time' => $cancellation->created_at,
                                'type' => 'cancellation_event',
                                'title' => 'Cancellation event',
                                'actor' => ActorIdentifier::forUser($cancellation->requester),
                                'status' => (string) $cancellation->status->value,
                                'details' => 'Cancellation workflow updated.',
                            ]);
                        }
                        foreach ($booking->refunds as $refund) {
                            $timelineEvents->push([
                                'time' => $refund->created_at,
                                'type' => 'refund_event',
                                'title' => 'Refund event',
                                'actor' => ActorIdentifier::forUser($refund->approver),
                                'status' => (string) $refund->status->value,
                                'details' => 'Refund amount '.((string) $refund->amount).' '.strtoupper((string) ($refund->currency ?? 'PKR')),
                            ]);
                        }
                        $timelineEvents = $timelineEvents
                            ->filter(fn ($event) => $event['time'] !== null)
                            ->sortByDesc(fn ($event) => $event['time'])
                            ->values();
                    @endphp
                    <div class="small text-secondary mb-3">Append-only operational history. This timeline is read-only and does not allow log deletion.</div>
                    @forelse ($timelineEvents as $event)
                        <div class="timeline-entry">
                            <div class="small text-secondary"><x-time.local :value="$event['time']" context="operator" />{{ display_sep_dot() }}<span class="text-capitalize">{{ str_replace('_', ' ', $event['status']) }}</span></div>
                            <div class="small text-secondary">{{ \App\Support\Identity\IdentityDisplay::labelUserActorId() }}: <span class="font-monospace">{{ $event['actor'] }}</span></div>
                            <div class="fw-semibold">{{ $event['title'] }}</div>
                            <details class="small mt-1">
                                <summary>View details</summary>
                                <div class="mt-1 text-secondary">{{ $event['details'] }}</div>
                            </details>
                        </div>
                    @empty
                        <p class="text-secondary small mb-0">No timeline events logged.</p>
                    @endforelse
                </div>
            </div>

            <div class="jp-card" data-tab-section="audit">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Audit trail</h3></div>
                <div class="jp-card__body">
                    @php
                        $staffAuditLogs = $auditLogs->filter(fn ($al) => $al->user !== null);
                        $systemAuditLogs = $auditLogs->filter(fn ($al) => $al->user === null);
                    @endphp
                    <h4 class="mb-2">Staff actions</h4>
                    @forelse ($staffAuditLogs as $al)
                        @php
                            $props = is_array($al->properties) ? $al->properties : [];
                            $newValues = is_array($props['new_values'] ?? null) ? $props['new_values'] : [];
                        @endphp
                        <div class="audit-row">
                            <div><span class="text-secondary"><x-time.local :value="$al->created_at" context="operator" /></span>{{ display_sep_dot() }}<code>{{ $al->action }}</code>{{ display_sep_dot() }}{{ $al->user?->name }}</div>
                            @if (!empty($newValues))
                                <div class="small text-secondary mt-1">
                                    @foreach ($newValues as $k => $v)
                                        <div>{{ str_replace('_', ' ', (string) $k) }}: {{ is_scalar($v) || $v === null ? (string) ($v ?? display_unknown()) : '[complex value]' }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-secondary small mb-2">No staff actions logged.</p>
                    @endforelse

                    <h4 class="mb-2 mt-3">System events</h4>
                    @forelse ($systemAuditLogs as $al)
                        @php
                            $props = is_array($al->properties) ? $al->properties : [];
                            $newValues = is_array($props['new_values'] ?? null) ? $props['new_values'] : [];
                        @endphp
                        <div class="audit-row">
                            <div><span class="text-secondary"><x-time.local :value="$al->created_at" context="operator" /></span>{{ display_sep_dot() }}<code>{{ $al->action }}</code>{{ display_sep_dot() }}System</div>
                            @if (!empty($newValues))
                                <div class="small text-secondary mt-1">
                                    @foreach ($newValues as $k => $v)
                                        <div>{{ str_replace('_', ' ', (string) $k) }}: {{ is_scalar($v) || $v === null ? (string) ($v ?? display_unknown()) : '[complex value]' }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-secondary small mb-0">No system events logged.</p>
                    @endforelse
                </div>
            </div>

            <div class="jp-card" id="supplier-pnr-panel" data-tab-section="supplier">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Supplier booking / PNR</h3></div>
                <div class="jp-card__body">
                    @php
                        $meta = $booking->meta ?? [];
                        $provider = $meta['supplier_provider'] ?? ($booking->supplier ?? display_unknown());
                        $validationStatus = $meta['offer_validation_status'] ?? 'unknown';
                        $iatiOfferValidationPanel = (($iatiDiagnostic['show'] ?? false) && is_array($iatiDiagnostic['offer_validation'] ?? null))
                            ? $iatiDiagnostic['offer_validation']
                            : null;
                        if ($iatiOfferValidationPanel !== null) {
                            $validationStatus = (string) ($iatiOfferValidationPanel['status'] ?? $validationStatus);
                        }
                        $latestAttempt = $booking->supplierBookingAttempts->sortByDesc('created_at')->first();
                        $hasSuccess = $booking->supplierBookings->contains(fn ($item) => in_array($item->status, ['created', 'pending_ticketing', 'ticketed'], true));
                        $bookingRoute = $p === 'staff' ? route('staff.bookings.supplier-booking', $booking) : route('admin.bookings.supplier-booking', $booking);
                        $manualPnrRoute = $p === 'staff' ? route('staff.bookings.manual-pnr', $booking) : route('admin.bookings.manual-pnr', $booking);
                        $providerSupportsPnr = in_array((string) $provider, ['duffel', 'sabre', 'pia_ndc', 'airblue', 'airline_direct', 'amadeus', 'travelport', 'iati'], true);
                        $warnings = is_array($meta['validation_warnings'] ?? null) ? $meta['validation_warnings'] : [];
                        $safeSummary = is_array($latestAttempt?->safe_summary ?? null) ? $latestAttempt->safe_summary : [];
                        $lastValidatedAt = $meta['validated_at'] ?? null;
                        $lastValidationAtLabel = $lastValidatedAt ? \Illuminate\Support\Carbon::parse((string) $lastValidatedAt)->format('Y-m-d H:i') : display_unknown();
                        $viewer = auth()->user();
                        $canViewDiagnostics = $viewer && method_exists($viewer, 'isPlatformAdmin') && $viewer->isPlatformAdmin();
                        $canMarkManualPnr = $viewer && method_exists($viewer, 'isStaff') && ($viewer->isStaff() || $viewer->isPlatformAdmin())
                            && ($sa === null || ! ($sa['has_pnr_or_reference'] ?? false));
                        $canValidateOffer = in_array((string) $validationStatus, ['ok', 'valid', 'fresh'], true);
                        $validateOfferReason = $canValidateOffer ? '' : 'Supplier unavailable';
                        $isPaid = (string) ($booking->payment_status ?? 'unpaid') === 'paid';
                        $passengerReadinessOkay = ($supplierBookingEligible ?? false);
                        $offerValid = $iatiOfferValidationPanel !== null
                            ? (bool) ($iatiOfferValidationPanel['show_as_valid'] ?? false)
                            : in_array((string) $validationStatus, ['ok', 'valid', 'fresh'], true);
                        $canCreatePnr = $providerSupportsPnr && $isPaid && $passengerReadinessOkay && $offerValid && ! $hasSuccess;
                        $createPnrReason = ! $isPaid
                            ? 'Payment unpaid / invalid passengers / expired offer'
                            : (! $passengerReadinessOkay
                                ? 'Payment unpaid / invalid passengers / expired offer'
                                : (! $offerValid
                                    ? 'Payment unpaid / invalid passengers / expired offer'
                                    : ($hasSuccess ? 'Supplier booking already exists.' : ($providerSupportsPnr ? '' : 'Supplier unavailable'))));
                        $latestAttemptStatus = strtolower((string) ($latestAttempt->status ?? ''));
                        $canRetrySupplier = in_array($latestAttemptStatus, ['failed', 'manual_review', 'needs_review'], true);
                        $retryReason = $canRetrySupplier ? '' : 'No failed attempt';
                        if ($sa !== null) {
                            $canCreatePnr = (bool) ($sa['can_create_pnr'] ?? false);
                            $createPnrReason = (string) ($sa['create_pnr_reason'] ?? '');
                            $canRetrySupplier = (bool) ($sa['can_retry_pnr'] ?? false);
                            $retryReason = (string) ($sa['retry_pnr_reason'] ?? '');
                            $safeSummaryDisplayKeys = $sa['safe_summary_display_keys'] ?? [];
                        } else {
                            $safeSummaryDisplayKeys = [];
                        }
                    @endphp
                    @if ($sa !== null && ($sa['supplier_status_message'] ?? '') !== '')
                        <div class="alert alert-{{ $sa['supplier_status_variant'] ?? 'info' }} py-2 px-3 small mb-3" role="status">
                            {{ $sa['supplier_status_message'] }}
                        </div>
                    @endif
                    @if ($sa !== null && ($sa['pnr_failure_admin_message'] ?? '') !== '')
                        @php
                            $pnrFailureVariant = match ($sa['pnr_failure_classification'] ?? '') {
                                'booking_class_mismatch', 'stale_or_missing_inventory' => 'warning',
                                'complex_itinerary_pnr_deferred' => 'info',
                                default => 'danger',
                            };
                        @endphp
                        <div class="alert alert-{{ $pnrFailureVariant }} py-2 px-3 small mb-3" role="alert">
                            {{ $sa['pnr_failure_admin_message'] }}
                        </div>
                    @elseif ($sa !== null && ($sa['stale_segment'] ?? false))
                        <div class="jp-alert jp-alert--warn py-2 px-3 small mb-3" role="alert">
                            <strong>Flight/class no longer available.</strong> Ask the customer to search and select again.
                            @if (! empty($sa['stale_context']))
                                <ul class="mb-0 mt-2">
                                    @foreach ($sa['stale_context'] as $ctxKey => $ctxVal)
                                        <li>{{ str_replace('_', ' ', (string) $ctxKey) }}: {{ $ctxVal }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @elseif ($sa !== null && ($sa['staff_review'] ?? false))
                        <div class="jp-alert jp-alert--danger py-2 px-3 small mb-3" role="alert">
                            {{ $sa['staff_review_summary'] ?? 'Supplier booking failed - staff review required.' }}
                        </div>
                    @endif
                    @include('dashboard.admin.bookings.partials.detail-supplier-summary')
                    <details class="ota-admin-advanced-collapse mb-3">
                        <summary class="ota-admin-advanced-collapse__summary">Advanced supplier diagnostics</summary>
                        <div class="ota-admin-advanced-collapse__body">
                    @php
                        $offerRefreshDiag = is_array($sa['offer_refresh_diagnostics'] ?? null) ? $sa['offer_refresh_diagnostics'] : null;
                    @endphp
                    @if ($offerRefreshDiag !== null && ($offerRefreshDiag['show_panel'] ?? false))
                        @php
                            $offerRefreshVariant = match ($offerRefreshDiag['recommended_staff_action'] ?? '') {
                                'fresh_search_required' => 'warning',
                                'fare_acceptance_required' => 'info',
                                'retry_after_cooldown', 'retry_offer_refresh' => 'warning',
                                default => 'secondary',
                            };
                        @endphp
                        <div class="border rounded p-3 mb-3 bg-light" id="offer-refresh-diagnostics-panel" data-testid="offer-refresh-diagnostics-panel">
                            <h4 class="h6 mb-1">Controlled offer refresh</h4>
                            <div class="alert alert-{{ $offerRefreshVariant }} py-2 px-3 small mb-2" role="status">
                                {{ $offerRefreshDiag['admin_message'] ?? 'Offer refresh diagnostics available for staff review.' }}
                            </div>
                            <div class="overview-summary-grid">
                                <div class="overview-kv">
                                    <span class="label">Recommended action</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($offerRefreshDiag['recommended_staff_action'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Refresh attempted</span>
                                    <span class="value text-end">{{ ($offerRefreshDiag['refresh_attempted'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Refresh available</span>
                                    <span class="value text-end">{{ ($offerRefreshDiag['refresh_available'] ?? null) === null ? display_unknown() : (($offerRefreshDiag['refresh_available'] ?? false) ? 'yes' : 'no') }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Refresh status</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($offerRefreshDiag['refresh_status'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Refresh reason code</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($offerRefreshDiag['refresh_reason_code'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Search criteria present</span>
                                    <span class="value text-end">{{ ($offerRefreshDiag['search_criteria_present'] ?? null) === null ? display_unknown() : (($offerRefreshDiag['search_criteria_present'] ?? false) ? 'yes' : 'no') }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Checkout search ID present</span>
                                    <span class="value text-end">{{ ($offerRefreshDiag['checkout_search_id_present'] ?? null) === null ? display_unknown() : (($offerRefreshDiag['checkout_search_id_present'] ?? false) ? 'yes' : 'no') }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Offer reference present</span>
                                    <span class="value text-end">{{ ($offerRefreshDiag['offer_reference_present'] ?? null) === null ? display_unknown() : (($offerRefreshDiag['offer_reference_present'] ?? false) ? 'yes' : 'no') }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Shop identifiers present</span>
                                    <span class="value text-end">{{ ($offerRefreshDiag['shop_identifiers_present'] ?? null) === null ? display_unknown() : (($offerRefreshDiag['shop_identifiers_present'] ?? false) ? 'yes' : 'no') }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Safe refresh context present</span>
                                    <span class="value text-end">{{ ($offerRefreshDiag['safe_refresh_context_present'] ?? null) === null ? display_unknown() : (($offerRefreshDiag['safe_refresh_context_present'] ?? false) ? 'yes' : 'no') }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Safe refresh context complete</span>
                                    <span class="value text-end">{{ ($offerRefreshDiag['safe_refresh_context_complete'] ?? null) === null ? display_unknown() : (($offerRefreshDiag['safe_refresh_context_complete'] ?? false) ? 'yes' : 'no') }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Can rebuild from safe context</span>
                                    <span class="value text-end">{{ ($offerRefreshDiag['can_rebuild_from_safe_context'] ?? null) === null ? display_unknown() : (($offerRefreshDiag['can_rebuild_from_safe_context'] ?? false) ? 'yes' : 'no') }}</span>
                                </div>
                                @if (! empty($offerRefreshDiag['missing_context_fields']))
                                    <div class="overview-kv">
                                        <span class="label">Missing context fields</span>
                                        <span class="value text-end">{{ implode(', ', array_map(fn ($f) => str_replace('_', ' ', (string) $f), $offerRefreshDiag['missing_context_fields'])) }}</span>
                                    </div>
                                @endif
                                @if (! empty($offerRefreshDiag['refresh_reasons']))
                                    <div class="overview-kv">
                                        <span class="label">Refresh reasons</span>
                                        <span class="value text-end">{{ implode(', ', array_map(fn ($r) => str_replace('_', ' ', (string) $r), $offerRefreshDiag['refresh_reasons'])) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                    @php
                        $verifiedAutoPnrReadiness = is_array($sa['verified_auto_pnr_readiness'] ?? null) ? $sa['verified_auto_pnr_readiness'] : null;
                        $operationalPnrReadiness = is_array($sa['operational_pnr_readiness'] ?? null) ? $sa['operational_pnr_readiness'] : null;
                    @endphp
                    @if ($sa !== null && ($sa['is_sabre'] ?? false) && $verifiedAutoPnrReadiness !== null && $canViewDiagnostics)
                        @php
                            $readinessVariant = ($verifiedAutoPnrReadiness['eligible'] ?? false) ? 'success' : 'secondary';
                            $connectionAirports = is_array($verifiedAutoPnrReadiness['connection_airports'] ?? null)
                                ? implode(', ', $verifiedAutoPnrReadiness['connection_airports'])
                                : display_unknown();
                        @endphp
                        <div class="border rounded p-3 mb-3 bg-light" id="verified-auto-pnr-readiness-panel" data-testid="verified-auto-pnr-readiness-panel">
                            <h4 class="h6 mb-1">Verified auto-PNR readiness (dry-run)</h4>
                            <div class="alert alert-{{ $readinessVariant }} py-2 px-3 small mb-2" role="status">
                                {{ $verifiedAutoPnrReadiness['reason_message'] ?? 'Dry-run readiness evaluation available.' }}
                            </div>
                            @php
                                $operationalAutoPnrEnabled = ($operationalPnrReadiness['operational_auto_pnr_enabled'] ?? false) === true;
                            @endphp
                            <p class="small text-secondary mb-2">
                                @if ($operationalAutoPnrEnabled)
                                    Operational auto-PNR enabled{{ display_sep_dot() }}verified panel below is diagnostics-only.
                                @else
                                    Dry-run only{{ display_sep_dot() }}public auto-PNR is not enabled.
                                @endif
                            </p>
                            <p class="small text-secondary mb-2">
                                <code>unknown_controlled_only</code> here does not block operational PNR when operational readiness shows would_attempt_pnr=yes.
                            </p>
                            <div class="overview-summary-grid">
                                <div class="overview-kv">
                                    <span class="label">Eligible (dry-run)</span>
                                    <span class="value text-end">{{ ($verifiedAutoPnrReadiness['eligible'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Mode</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($verifiedAutoPnrReadiness['mode'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Reason code</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($verifiedAutoPnrReadiness['reason_code'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Controlled PNR certification</span>
                                    <span class="value text-end">{{ $sa['controlled_pnr_certification_label'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Certification status</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($verifiedAutoPnrReadiness['controlled_pnr_certification_status'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Carrier chain</span>
                                    <span class="value text-end">{{ $verifiedAutoPnrReadiness['carrier_chain'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Route</span>
                                    <span class="value text-end">{{ ($verifiedAutoPnrReadiness['origin'] ?? display_unknown()) }}{{ display_sep_dot() }}{{ $connectionAirports !== '--' ? $connectionAirports . display_sep_dot() : '' }}{{ $verifiedAutoPnrReadiness['destination'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Segment count</span>
                                    <span class="value text-end">{{ $verifiedAutoPnrReadiness['segment_count'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Safe refresh complete</span>
                                    <span class="value text-end">{{ ($verifiedAutoPnrReadiness['safe_refresh_context_complete'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Public auto-PNR enabled</span>
                                    <span class="value text-end">{{ ($verifiedAutoPnrReadiness['public_auto_pnr_currently_enabled'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if ($sa !== null && ($sa['is_sabre'] ?? false) && $operationalPnrReadiness !== null && $canViewDiagnostics)
                        @php
                            $operationalVariant = ($operationalPnrReadiness['would_attempt_pnr'] ?? false) ? 'success' : 'secondary';
                            $operationalBlockingRaw = is_array($operationalPnrReadiness['blocking_conditions'] ?? null)
                                ? $operationalPnrReadiness['blocking_conditions']
                                : [];
                            $operationalBlocking = implode(', ', \App\Support\Sabre\SabrePnrLaneDiagnostics::filterBlockingConditionsForLane(
                                $operationalBlockingRaw,
                                \App\Support\Sabre\SabrePnrLaneDiagnostics::LANE_OPERATIONAL_AUTO_PNR,
                                $booking,
                            ));
                            $publicCheckoutAttempted = \App\Support\Sabre\SabrePnrLaneDiagnostics::publicCheckoutPnrWasAttempted($booking);
                        @endphp
                        <div class="border rounded p-3 mb-3 bg-light" id="operational-pnr-readiness-panel" data-testid="operational-pnr-readiness-panel">
                            <h4 class="h6 mb-1">Operational Sabre PNR readiness (BF7-J-OPS)</h4>
                            <p class="small text-secondary mb-2">
                                Lane: {{ \App\Support\Sabre\SabrePnrLaneDiagnostics::laneLabel(\App\Support\Sabre\SabrePnrLaneDiagnostics::LANE_OPERATIONAL_AUTO_PNR) }}.
                                {{ \App\Support\Sabre\SabrePnrLaneDiagnostics::flagDescription('operational_auto_pnr_enabled') }}
                                @if ($publicCheckoutAttempted)
                                    Public checkout PNR was attempted on this booking — operational auto-PNR disabled is not a public-checkout blocker.
                                @endif
                            </p>
                            <div class="alert alert-{{ $operationalVariant }} py-2 px-3 small mb-2" role="status">
                                {{ str_replace('_', ' ', (string) ($operationalPnrReadiness['reason_code'] ?? display_unknown())) }}
                            </div>
                            <div class="overview-summary-grid">
                                <div class="overview-kv">
                                    <span class="label">Would attempt PNR</span>
                                    <span class="value text-end">{{ ($operationalPnrReadiness['would_attempt_pnr'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Last attempt status</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($sa['last_operational_pnr_attempt_status'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Operational auto-PNR enabled</span>
                                    <span class="value text-end">{{ ($operationalPnrReadiness['operational_auto_pnr_enabled'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Public checkout PNR enabled</span>
                                    <span class="value text-end" title="{{ \App\Support\Sabre\SabrePnrLaneDiagnostics::flagDescription('public_checkout_pnr_enabled') }}">{{ ($operationalPnrReadiness['public_checkout_pnr_enabled'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Payment mode</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($operationalPnrReadiness['payment_mode'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Mixed carrier</span>
                                    <span class="value text-end">{{ ($operationalPnrReadiness['mixed_carrier'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Blocking conditions</span>
                                    <span class="value text-end ota-r-text-safe">{{ $operationalBlocking }}</span>
                                </div>
                                @if (($sa['operational_pnr_reason_code'] ?? null) !== null)
                                    <div class="overview-kv">
                                        <span class="label">Last safe Sabre reason</span>
                                        <span class="value text-end ota-r-text-safe">{{ str_replace('_', ' ', (string) $sa['operational_pnr_reason_code']) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                    @php
                        $brandedFarePublicAutoPnr = is_array($sa['branded_fare_public_auto_pnr_eligibility'] ?? null)
                            ? $sa['branded_fare_public_auto_pnr_eligibility']
                            : null;
                    @endphp
                    @if ($sa !== null && ($sa['is_sabre'] ?? false) && $canViewDiagnostics)
                        @if ($brandedFarePublicAutoPnr === null)
                            <div class="border rounded p-3 mb-3 bg-light" id="branded-fare-public-auto-pnr-panel" data-testid="branded-fare-public-auto-pnr-panel">
                                <h4 class="h6 mb-1">Public Auto-PNR eligibility (branded fare, BF7-I dry)</h4>
                                <div class="jp-alert jp-alert--info py-2 px-3 small mb-0" role="status">
                                    Not evaluated yet{{ display_sep_dot() }}no stored checkout eligibility on this booking.
                                </div>
                            </div>
                        @else
                        @php
                            $bf7iVariant = ($brandedFarePublicAutoPnr['eligible'] ?? false) ? 'success' : 'secondary';
                            $bf7iFailed = is_array($brandedFarePublicAutoPnr['failed_conditions'] ?? null)
                                ? $brandedFarePublicAutoPnr['failed_conditions']
                                : [];
                        @endphp
                        <div class="border rounded p-3 mb-3 bg-light" id="branded-fare-public-auto-pnr-panel" data-testid="branded-fare-public-auto-pnr-panel">
                            <h4 class="h6 mb-1">Public Auto-PNR eligibility (branded fare, BF7-I dry)</h4>
                            <div class="alert alert-{{ $bf7iVariant }} py-2 px-3 small mb-2" role="status">
                                {{ ($brandedFarePublicAutoPnr['eligible'] ?? false) ? 'Eligible' : 'Blocked' }}
                               {{ display_sep_dot() }}{{ str_replace('_', ' ', (string) ($brandedFarePublicAutoPnr['reason_code'] ?? display_unknown())) }}
                            </div>
                            <p class="small text-secondary mb-2">Read-only checkout evaluation{{ display_sep_dot() }}no PNR created from this diagnostic.</p>
                            <div class="overview-summary-grid">
                                <div class="overview-kv">
                                    <span class="label">Eligible</span>
                                    <span class="value text-end">{{ ($brandedFarePublicAutoPnr['eligible'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Reason code</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($brandedFarePublicAutoPnr['reason_code'] ?? display_unknown())) }}</span>
                                </div>
                                @if ($bf7iFailed !== [])
                                    <div class="overview-kv">
                                        <span class="label">Failed conditions</span>
                                        <span class="value text-end">{{ implode(', ', array_map(fn ($c) => str_replace('_', ' ', (string) $c), $bf7iFailed)) }}</span>
                                    </div>
                                @endif
                                <div class="overview-kv">
                                    <span class="label">Brand shape</span>
                                    <span class="value text-end">{{ $brandedFarePublicAutoPnr['brand_shape'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Selected brand code</span>
                                    <span class="value text-end">{{ $brandedFarePublicAutoPnr['selected_brand_code'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Carrier chain</span>
                                    <span class="value text-end">{{ $brandedFarePublicAutoPnr['carrier_chain'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Ticketing enabled</span>
                                    <span class="value text-end">{{ ($brandedFarePublicAutoPnr['ticketing_enabled'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Public flag enabled</span>
                                    <span class="value text-end">{{ ($brandedFarePublicAutoPnr['public_flag_enabled'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Auto-PNR flag enabled</span>
                                    <span class="value text-end">{{ ($brandedFarePublicAutoPnr['auto_pnr_flag_enabled'] ?? false) ? 'yes' : 'no' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Evaluated at</span>
                                    <span class="value text-end">{{ $brandedFarePublicAutoPnr['evaluated_at'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Live supplier call</span>
                                    <span class="value text-end">no</span>
                                </div>
                            </div>
                        </div>
                        @endif
                    @endif
                    @php
                        $preCheckoutSellability = is_array($sa['pre_checkout_sellability'] ?? null) ? $sa['pre_checkout_sellability'] : null;
                        $preCheckoutPresentation = is_array($preCheckoutSellability['presentation'] ?? null) ? $preCheckoutSellability['presentation'] : null;
                        $preCheckoutDryRun = is_array($preCheckoutSellability['dry_run'] ?? null) ? $preCheckoutSellability['dry_run'] : null;
                    @endphp
                    @if ($sa !== null && ($sa['is_sabre'] ?? false) && $preCheckoutPresentation !== null && $canViewDiagnostics)
                        @php
                            $preCheckoutSeverity = match ((string) ($preCheckoutPresentation['severity'] ?? 'secondary')) {
                                'success' => 'success',
                                'warning' => 'warning',
                                'danger' => 'danger',
                                'info' => 'info',
                                default => 'secondary',
                            };
                        @endphp
                        <div class="border rounded p-3 mb-3 bg-light" id="pre-checkout-sellability-panel" data-testid="pre-checkout-sellability-panel">
                            <h4 class="h6 mb-1">Pre-checkout sellability (passive E5I)</h4>
                            <div class="alert alert-{{ $preCheckoutSeverity }} py-2 px-3 small mb-2" role="status">
                                {{ $preCheckoutPresentation['label'] ?? display_unknown() }}{{ display_sep_dot() }}{{ $preCheckoutPresentation['staff_message'] ?? '' }}
                            </div>
                            <p class="small text-secondary mb-2">Passive E5I{{ display_sep_dot() }}does not block checkout or create PNRs.</p>
                            <div class="overview-summary-grid">
                                <div class="overview-kv">
                                    <span class="label">Dry-run status</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($preCheckoutDryRun['status'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Recommended checkout action</span>
                                    <span class="value text-end">{{ str_replace('_', ' ', (string) ($preCheckoutDryRun['recommended_checkout_action'] ?? display_unknown())) }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Evidence booking ID (success)</span>
                                    <span class="value text-end">{{ $preCheckoutDryRun['evidence_booking_id_success'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Evidence booking ID (failed)</span>
                                    <span class="value text-end">{{ $preCheckoutDryRun['evidence_booking_id_failed'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Live supplier call attempted</span>
                                    <span class="value text-end">false</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Booking status updated</span>
                                    <span class="value text-end">false</span>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if ($sa !== null && ($sa['complex_itinerary_deferred'] ?? false))
                        <div class="jp-alert jp-alert--info py-2 px-3 small mb-3" role="status">
                            {{ $sa['complex_itinerary_message'] ?? __('Supplier PNR deferred - return/multi-city itinerary requires staff confirmation.') }}
                        </div>
                    @endif
                    @if ($sa !== null && ($sa['rate_limit']['in_cooldown'] ?? false))
                        <div class="jp-alert jp-alert--warn py-2 px-3 small mb-3" role="alert">
                            {{ $sa['rate_limit']['message'] ?? 'Sabre busy / retry later' }}
                        </div>
                    @endif
                    @if ($sabrePassengerRecordsGuard)
                        <div class="jp-alert jp-alert--warn mb-3" role="alert">
                            <div class="fw-semibold">{{ $sabrePassengerRecordsGuard['headline'] }}: {{ $sabrePassengerRecordsGuard['reason'] }}</div>
                            <ul class="small mb-2 mt-2">
                                <li><strong>{{ __('Trigger') }}:</strong> {{ $sabrePassengerRecordsGuard['guard_trigger'] }}</li>
                                <li><strong>{{ __('Segment count') }}:</strong> {{ $sabrePassengerRecordsGuard['segment_count'] }}</li>
                                <li><strong>{{ __('Segment order corrected') }}:</strong> {{ $sabrePassengerRecordsGuard['segment_order_corrected'] }}</li>
                                <li><strong>{{ __('Live Sabre call attempted') }}:</strong> {{ $sabrePassengerRecordsGuard['live_call_attempted'] }}</li>
                                <li><strong>{{ __('PNR') }}:</strong> {{ $sabrePassengerRecordsGuard['pnr'] }}</li>
                                <li><strong>{{ __('Ticketing') }}:</strong> {{ $sabrePassengerRecordsGuard['ticketing'] }}</li>
                            </ul>
                            <p class="small mb-0"><strong>{{ __('Suggested action') }}:</strong> {{ $sabrePassengerRecordsGuard['suggested_action'] }}</p>
                        </div>
                    @endif
                    @php
                        $scp = is_array($sa['sabre_capability_posture'] ?? null) ? $sa['sabre_capability_posture'] : null;
                    @endphp
                    @if ($scp !== null && ($scp['show'] ?? false))
                        <div class="border rounded p-3 mb-3 bg-light" id="sabre-capability-posture-panel">
                            <h4 class="h6 mb-2">Sabre capability posture</h4>
                            <p class="small text-secondary mb-2">Architecture posture (read-only). Env deployment gates are shown separately where applicable.</p>
                            <div class="overview-summary-grid mb-0">
                                <div class="overview-kv">
                                    <span class="label">GDS cancel (architecture)</span>
                                    <span class="value text-end">{{ $scp['gds_cancel_label'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">GDS ticketing (architecture)</span>
                                    <span class="value text-end">{{ $scp['gds_ticketing_label'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">NDC (architecture)</span>
                                    <span class="value text-end">{{ $scp['ndc_label'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Diagnostics (architecture)</span>
                                    <span class="value text-end">{{ $scp['diagnostics_label'] ?? display_unknown() }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if ($sa !== null && ($sa['is_sabre'] ?? false))
                        <p class="mb-1 small text-secondary"><strong>PNR retrieve/sync available:</strong> {{ ($sa['can_sync_pnr_itinerary'] ?? false) ? 'yes' : 'no' }}</p>
                        <p class="mb-1 small text-secondary"><strong>PNR retrieve endpoint:</strong> {{ $sa['pnr_itinerary_retrieve_endpoint'] ?? '/v1/trip/orders/getBooking' }}</p>
                        <p class="mb-1 small text-secondary"><strong>PNR retrieve result:</strong> {{ str_replace('_', ' ', (string) ($sa['pnr_itinerary_retrieve_result'] ?? 'not_attempted')) }}@if (! empty($sa['pnr_itinerary_synced_at'])){{ display_sep_dot() }}{{ $sa['pnr_itinerary_synced_at'] }}@endif</p>
                        @if (! empty($sa['pnr_itinerary_sync_status']))
                            <p class="mb-1 small text-secondary"><strong>PNR itinerary sync status:</strong> {{ str_replace('_', ' ', (string) $sa['pnr_itinerary_sync_status']) }}</p>
                        @endif
                        @if (! empty($sa['pnr_itinerary_sync_reason']))
                            <p class="mb-1 small text-warning"><strong>PNR sync note:</strong> {{ str_replace('_', ' ', (string) $sa['pnr_itinerary_sync_reason']) }}</p>
                        @endif
                    @endif

                    @if (($iatiDiagnostic['show'] ?? false))
                        <div class="border rounded p-3 mb-3" id="iati-diagnostic-panel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h4 class="h6 mb-0">{{ $iatiDiagnostic['title'] ?? 'IATI supplier' }}</h4>
                                <form method="POST" action="{{ route('admin.bookings.sync-iati-booking', $booking) }}">
                                    @csrf
                                    <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Sync IATI Order</button>
                                </form>
                            </div>
                            <dl class="row mb-0 small">
                                @foreach ($iatiDiagnostic['fields'] ?? [] as $field)
                                    <dt class="col-sm-4">{{ $field['label'] ?? '' }}</dt>
                                    <dd class="col-sm-8">{{ $field['value'] ?? '—' }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    @if (($airblueDiagnostic['show'] ?? false))
                        <div class="border rounded p-3 mb-3" id="airblue-diagnostic-panel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h4 class="h6 mb-0">{{ $airblueDiagnostic['title'] ?? 'AirBlue supplier' }}</h4>
                                <form method="POST" action="{{ route('admin.bookings.sync-airblue-booking', $booking) }}">
                                    @csrf
                                    <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Sync AirBlue booking</button>
                                </form>
                            </div>
                            <dl class="row mb-0 small">
                                @foreach ($airblueDiagnostic['fields'] ?? [] as $field)
                                    <dt class="col-sm-4">{{ $field['label'] ?? '' }}</dt>
                                    <dd class="col-sm-8">{{ $field['value'] ?? '—' }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    @if (($piaNdcSelectedFare['show'] ?? false))
                        <div class="border rounded p-3 mb-3" id="pia-ndc-selected-fare-panel" data-testid="pia-ndc-selected-fare-panel">
                            <h4 class="h6 mb-2">{{ $piaNdcSelectedFare['title'] ?? 'Selected branded fare (PIA NDC)' }}</h4>
                            <dl class="row mb-0 small">
                                @if (! empty($piaNdcSelectedFare['selected_fare_total']))
                                    <dt class="col-sm-4">Selected fare total</dt>
                                    <dd class="col-sm-8">{{ $piaNdcSelectedFare['selected_fare_total'] }}</dd>
                                @endif
                                @if (! empty($piaNdcSelectedFare['revalidated_fare_total']))
                                    <dt class="col-sm-4">Revalidated fare total</dt>
                                    <dd class="col-sm-8">{{ $piaNdcSelectedFare['revalidated_fare_total'] }}</dd>
                                @endif
                                @foreach (['selected', 'outbound', 'return'] as $legKey)
                                    @php $leg = is_array($piaNdcSelectedFare[$legKey] ?? null) ? $piaNdcSelectedFare[$legKey] : null; @endphp
                                    @if ($leg !== null)
                                        <dt class="col-sm-4">{{ $leg['label'] ?? ucfirst($legKey) }}</dt>
                                        <dd class="col-sm-8">
                                            <div>{{ $leg['brand_name'] ?? display_unknown() }}</div>
                                            @if (! empty($leg['fare_basis']) || ! empty($leg['booking_class']) || ! empty($leg['baggage']))
                                                <div class="text-secondary">
                                                    {{ implode(' · ', array_filter([
                                                        $leg['fare_basis'] ?? null,
                                                        ! empty($leg['booking_class']) ? 'Class '.$leg['booking_class'] : null,
                                                        $leg['baggage'] ?? null,
                                                    ])) }}
                                                </div>
                                            @endif
                                            @if (! empty($leg['price_display']))
                                                <div>{{ $leg['price_display'] }}</div>
                                            @endif
                                            @if (! empty($leg['offer_ref_masked']) || ! empty($leg['offer_item_ref_masked']))
                                                <div class="text-secondary">
                                                    @if (! empty($leg['offer_ref_masked']))
                                                        Offer ref: {{ $leg['offer_ref_masked'] }}
                                                    @endif
                                                    @if (! empty($leg['offer_item_ref_masked']))
                                                        {{ display_sep_dot() }}Item: {{ $leg['offer_item_ref_masked'] }}
                                                    @endif
                                                </div>
                                            @endif
                                        </dd>
                                    @endif
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    @if (($piaNdcOptionPnr['show'] ?? false))
                        <div class="border rounded p-3 mb-3" id="pia-ndc-option-pnr-panel">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <h4 class="h6 mb-0">{{ $piaNdcOptionPnr['title'] ?? 'PIA NDC option PNR' }}</h4>
                                <div class="d-flex flex-wrap gap-2">
                                    @if (($piaNdcStatusRefresh['can_refresh'] ?? false))
                                        <form method="POST" action="{{ route('admin.bookings.refresh-pia-ndc-status', $booking) }}">
                                            @csrf
                                            <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline" data-testid="pia-ndc-refresh-status">
                                                Refresh PIA Status
                                            </button>
                                        </form>
                                    @endif
                                    @if (($piaNdcRelease['can_release'] ?? false))
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#piaNdcReleaseModal">
                                            Release PIA NDC Option PNR
                                        </button>
                                    @endif
                                </div>
                            </div>
                            @if (($piaNdcStatusRefresh['show_stale_warning'] ?? false))
                                <div class="jp-alert jp-alert--warn py-2 px-3 small mb-2">{{ $piaNdcStatusRefresh['stale_warning'] ?? 'Supplier status may be stale — refresh PIA status.' }}</div>
                            @endif
                            @error('pia_ndc_status_refresh')
                                <div class="jp-alert jp-alert--danger py-2 px-3 small mb-2">{{ $message }}</div>
                            @enderror
                            <p class="small text-secondary mb-2">{{ $piaNdcOptionPnr['helper_text'] ?? 'PIA NDC option PNR is created automatically when the customer submits the booking request. Ticketing remains payment-gated.' }}</p>
                            @if (($piaNdcOptionPnr['latest_safe_error'] ?? null))
                                <div class="jp-alert jp-alert--warn py-2 px-3 small mb-2">{{ $piaNdcOptionPnr['latest_safe_error'] }}</div>
                            @endif
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4">PNR</dt>
                                <dd class="col-sm-8 ota-r-text-safe">{{ $piaNdcOptionPnr['pnr'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Order ID</dt>
                                <dd class="col-sm-8 ota-r-text-safe">{{ $piaNdcOptionPnr['order_id'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Airline locator</dt>
                                <dd class="col-sm-8 ota-r-text-safe">{{ $piaNdcOptionPnr['airline_locator'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Owner code</dt>
                                <dd class="col-sm-8">{{ $piaNdcOptionPnr['owner_code'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Supplier booking status</dt>
                                <dd class="col-sm-8">{{ $piaNdcOptionPnr['supplier_booking_status'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Order status</dt>
                                <dd class="col-sm-8">{{ $piaNdcOptionPnr['order_status'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Payment required by</dt>
                                <dd class="col-sm-8">{{ $piaNdcOptionPnr['payment_required_by'] ?? ($piaNdcStatusRefresh['payment_required_by'] ?? '—') }}</dd>
                                @if (($piaNdcStatusRefresh['show'] ?? false))
                                    <dt class="col-sm-4">Last supplier check</dt>
                                    <dd class="col-sm-8">{{ $piaNdcStatusRefresh['last_checked_at'] ?? '—' }}</dd>
                                    <dt class="col-sm-4">Supplier interpreted status</dt>
                                    <dd class="col-sm-8">{{ $piaNdcStatusRefresh['interpreted_status'] ?? '—' }}</dd>
                                    <dt class="col-sm-4">Segment count</dt>
                                    <dd class="col-sm-8">{{ $piaNdcStatusRefresh['segment_count'] ?? '—' }}</dd>
                                    <dt class="col-sm-4">Ticket numbers present</dt>
                                    <dd class="col-sm-8">{{ $piaNdcStatusRefresh['has_ticket_numbers'] ?? '—' }}</dd>
                                @endif
                                @if (($piaNdcOptionPnr['provider_context_source'] ?? null))
                                    <dt class="col-sm-4">Offer context source</dt>
                                    <dd class="col-sm-8 ota-r-text-safe">{{ $piaNdcOptionPnr['provider_context_source'] }}</dd>
                                @endif
                                @if (($piaNdcOptionPnr['latest_attempt_action'] ?? null))
                                    <dt class="col-sm-4">Latest attempt</dt>
                                    <dd class="col-sm-8">{{ $piaNdcOptionPnr['latest_attempt_action'] }} / {{ $piaNdcOptionPnr['latest_attempt_status'] ?? '—' }}</dd>
                                @endif
                                @if (($piaNdcRelease['show'] ?? false))
                                    <dt class="col-sm-4">Ticket numbers</dt>
                                    <dd class="col-sm-8 ota-r-text-safe">{{ $piaNdcRelease['ticket_numbers'] ?? '—' }}</dd>
                                    <dt class="col-sm-4">Released</dt>
                                    <dd class="col-sm-8">{{ (($piaNdcRelease['option_pnr_released'] ?? false) || ($piaNdcStatusRefresh['released'] ?? false)) ? 'Yes' : 'No' }}</dd>
                                @endif
                            </dl>
                        </div>

                        @if (($piaNdcRelease['can_release'] ?? false))
                            <div class="modal fade" id="piaNdcReleaseModal" tabindex="-1" aria-labelledby="piaNdcReleaseModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('admin.bookings.release-pia-ndc-option-pnr', $booking) }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="piaNdcReleaseModalLabel">Release PIA NDC Option PNR</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="small text-secondary">Unticketed option PNR only. This calls DoOrderCancelPreview then DoOrderCancelCommit. No public customer cancellation.</p>
                                                <dl class="row small mb-3">
                                                    <dt class="col-sm-5">PNR</dt>
                                                    <dd class="col-sm-7 ota-r-text-safe">{{ $piaNdcRelease['pnr'] ?? ($piaNdcOptionPnr['pnr'] ?? '—') }}</dd>
                                                    <dt class="col-sm-5">Order / supplier ref</dt>
                                                    <dd class="col-sm-7 ota-r-text-safe">{{ $piaNdcRelease['order_id'] ?? '—' }}</dd>
                                                    <dt class="col-sm-5">Payment required by</dt>
                                                    <dd class="col-sm-7">{{ $piaNdcRelease['payment_required_by'] ?? ($piaNdcOptionPnr['payment_required_by'] ?? '—') }}</dd>
                                                    <dt class="col-sm-5">Passengers</dt>
                                                    <dd class="col-sm-7">{{ $piaNdcRelease['passenger_count'] ?? $booking->passengers->count() }}</dd>
                                                </dl>
                                                <div class="mb-3">
                                                    <label for="pia_ndc_release_reason" class="jp-label">Operator reason</label>
                                                    <textarea id="pia_ndc_release_reason" name="operator_reason" class="jp-control" rows="3" required maxlength="500" placeholder="Why is this option PNR being released?"></textarea>
                                                </div>
                                                <div class="mb-0">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="admin_confirm_reviewed" value="1" id="pia_ndc_release_admin_confirm" required>
                                                        <label class="form-check-label" for="pia_ndc_release_admin_confirm">I reviewed this booking and approve releasing the option PNR.</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="jp-btn jp-btn--secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="jp-btn jp-btn--danger">Release option PNR</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    @if ($sa !== null && ($sa['can_release_sabre_gds_pnr'] ?? false))
                        <div class="modal fade" id="sabreGdsReleasePnrModal" tabindex="-1" aria-labelledby="sabreGdsReleasePnrModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="sabreGdsReleasePnrModalLabel">{{ $sa['release_sabre_gds_pnr_label'] ?? 'Release PNR' }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="small text-secondary">{{ $sa['release_sabre_gds_pnr_help'] ?? 'Cancels the unticketed Sabre/GDS PNR. Refund/credit handling remains manual.' }}</p>
                                        <p class="small mb-2"><strong>PNR:</strong> <span class="ota-r-text-safe">{{ $booking->pnr ?? $booking->supplier_reference ?? '—' }}</span></p>
                                        <p class="small text-warning mb-0">Supplier release is not invoked from this screen in the current phase. Use <strong>Cancellation &amp; Refund</strong> to process when live cancel is enabled.</p>
                                        <p class="small text-secondary mt-2 mb-0">Future confirmation phrase: <code>{{ $sa['release_sabre_gds_pnr_confirm_phrase'] ?? '' }}</code></p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="jp-btn jp-btn--secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="jp-btn jp-btn--danger" disabled title="Supplier release call deferred to cancellation workflow">Release PNR</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <p class="mb-2"><strong>Last validation time:</strong> {{ $lastValidationAtLabel }}</p>

                    @if (! $isPiaNdcProvider && ($sabreCompactDiagnostic['show'] ?? false))
                        <div class="border rounded p-3 mb-3" id="sabre-compact-diagnostic-panel">
                            <h4 class="h6 mb-2">{{ $sabreCompactDiagnostic['title'] ?? 'Sabre diagnostic summary' }}</h4>
                            <p class="small text-secondary mb-2">Read-only status groups{{ display_sep_dot() }}no raw supplier payloads or credentials.</p>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Area</th><th>Status</th><th>Detail</th><th>Blockers</th></tr></thead>
                                    <tbody>
                                        @foreach ($sabreCompactDiagnostic['groups'] ?? [] as $group)
                                            <tr>
                                                <td>{{ $group['label'] ?? display_unknown() }}</td>
                                                <td>{{ $group['status'] ?? display_unknown() }}</td>
                                                <td class="small">{{ $group['detail'] ?? 'Not available' }}</td>
                                                <td class="small">
                                                    @if (! empty($group['blockers']))
                                                        {{ implode('; ', $group['blockers']) }}
                                                    @else
                                                        --
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if (! $isPiaNdcProvider && ($sabreGdsTicketing['show'] ?? false))
                        @php
                            $gdsTicketingActionState = (string) ($sabreGdsTicketing['action_state'] ?? 'not_eligible');
                            $gdsTicketingAlertClass = match ($gdsTicketingActionState) {
                                'ticketed' => 'alert-success',
                                'ticketing_pending' => 'alert-warning',
                                'manual_action_required' => 'alert-danger',
                                'pnr_cancelled_released' => 'alert-danger',
                                'issue_ticket' => 'alert-info',
                                default => 'alert-secondary',
                            };
                        @endphp
                        <div class="mt-3 pt-3 border-top" id="sabre-gds-ticketing-panel" data-testid="sabre-gds-ticketing-panel">
                            <h4 class="h6 mb-2">{{ $sabreGdsTicketing['title'] ?? 'Sabre GDS Ticketing' }}</h4>
                            <div class="alert {{ $gdsTicketingAlertClass }} py-2 px-3 small mb-2">
                                <div><strong>{{ $sabreGdsTicketing['action_label'] ?? 'Not eligible' }}</strong></div>
                                <div>{{ $sabreGdsTicketing['admin_message'] ?? '' }}</div>
                                @if (! empty($sabreGdsTicketing['customer_message']))
                                    <div class="mt-1 text-secondary">Customer: {{ $sabreGdsTicketing['customer_message'] }}</div>
                                @endif
                            </div>
                            <div class="small">
                                @foreach ($sabreGdsTicketing['rows'] ?? [] as $gdsRow)
                                    <div class="overview-kv">
                                        <span class="label">{{ $gdsRow['label'] ?? '' }}</span>
                                        <span class="value text-end">{{ $gdsRow['value'] ?? display_unknown() }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if (! $isPiaNdcProvider && ($sabreGdsCancel['show'] ?? false))
                        @php
                            $gdsCancelActionState = (string) ($sabreGdsCancel['action_state'] ?? 'not_eligible');
                            $gdsCancelAlertClass = match ($gdsCancelActionState) {
                                'cancelled' => 'alert-success',
                                'cancellation_pending' => 'alert-warning',
                                'manual_ticketed_required' => 'alert-danger',
                                'cancel_sabre_pnr' => 'alert-info',
                                default => 'alert-secondary',
                            };
                        @endphp
                        <div class="mt-3 pt-3 border-top" id="sabre-gds-cancel-panel" data-testid="sabre-gds-cancel-panel">
                            <h4 class="h6 mb-2">{{ $sabreGdsCancel['title'] ?? 'Sabre GDS Cancellation' }}</h4>
                            <div class="alert {{ $gdsCancelAlertClass }} py-2 px-3 small mb-2">
                                <div><strong>{{ $sabreGdsCancel['action_label'] ?? 'Not eligible' }}</strong></div>
                                <div>{{ $sabreGdsCancel['admin_message'] ?? '' }}</div>
                                @if (! empty($sabreGdsCancel['customer_message']))
                                    <div class="mt-1 text-secondary">Customer: {{ $sabreGdsCancel['customer_message'] }}</div>
                                @endif
                            </div>
                            <div class="small">
                                @foreach ($sabreGdsCancel['rows'] ?? [] as $gdsCancelRow)
                                    <div class="overview-kv">
                                        <span class="label">{{ $gdsCancelRow['label'] ?? '' }}</span>
                                        <span class="value text-end">{{ $gdsCancelRow['value'] ?? display_unknown() }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if (($sabreNdcOrder['show'] ?? false))
                        <div class="mt-3 pt-3 border-top">
                            <h4 class="h6 mb-2">{{ $sabreNdcOrder['title'] ?? 'Sabre NDC Order' }}</h4>
                            <div class="small">
                                @foreach ($sabreNdcOrder['rows'] ?? [] as $ndcRow)
                                    <div class="overview-kv">
                                        <span class="label">{{ $ndcRow['label'] ?? '' }}</span>
                                        <span class="value text-end">{{ $ndcRow['value'] ?? display_unknown() }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if (($sabrePnrReadiness['show'] ?? false))
                        <div class="border rounded p-3 mb-3 bg-light" id="sabre-pnr-readiness-panel">
                            <h4 class="h6 mb-2">{{ $sabrePnrReadiness['title'] ?? 'Sabre PNR Readiness' }}</h4>
                            <p class="small text-secondary mb-2">Safe supplier diagnostics for staff review. Raw Sabre payloads and credentials are not shown here.</p>
                            <div class="overview-summary-grid">
                                @foreach ($sabrePnrReadiness['rows'] ?? [] as $readinessRow)
                                    <div class="overview-kv">
                                        <span class="label">{{ $readinessRow['label'] }}</span>
                                        <span class="value text-end">
                                            @if (! empty($readinessRow['badge']))
                                                <span class="badge bg-secondary-lt text-secondary me-1">{{ $readinessRow['badge'] }}</span>
                                            @endif
                                            {{ $readinessRow['value'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (($sabreHostSellDiagnostics['show'] ?? false))
                        <div class="border rounded p-3 mb-3 bg-light" id="sabre-host-sell-diagnostics-panel" data-testid="sabre-host-sell-diagnostics-panel">
                            <h4 class="h6 mb-1">{{ $sabreHostSellDiagnostics['title'] ?? 'Sabre Host Sell Diagnostics' }}</h4>
                            <p class="small text-secondary mb-2">Safe host sell diagnostics for Sabre GDS only. Raw Sabre payloads are not shown.</p>
                            <div class="overview-summary-grid">
                                @foreach ($sabreHostSellDiagnostics['rows'] ?? [] as $hostSellRow)
                                    <div class="overview-kv">
                                        <span class="label">{{ $hostSellRow['label'] ?? '' }}</span>
                                        <span class="value text-end ota-r-text-safe">{{ $hostSellRow['value'] ?? display_unknown() }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (($sabreHostClassification['show'] ?? false))
                        @php
                            $hostClassFields = is_array($sabreHostClassification['fields'] ?? null) ? $sabreHostClassification['fields'] : [];
                            $hostClassBadges = is_array($sabreHostClassification['signal_badges'] ?? null) ? $sabreHostClassification['signal_badges'] : [];
                        @endphp
                        <div class="border rounded p-3 mb-3 bg-light" id="sabre-host-classification-panel" data-testid="sabre-host-classification-panel">
                            <h4 class="h6 mb-1">Sabre host error classification</h4>
                            <p class="small text-secondary mb-2">{{ $sabreHostClassification['disclaimer'] ?? 'Advisory only. Saved at checkout. Does not change retry buttons or trigger automated Sabre action.' }}</p>
                            <div class="overview-summary-grid">
                                <div class="overview-kv">
                                    <span class="label">Host reason code</span>
                                    <span class="value text-end">{{ $hostClassFields['safe_reason_code'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Summary</span>
                                    <span class="value text-end">{{ $hostClassFields['safe_summary'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Recommended action</span>
                                    <span class="value text-end">{{ $hostClassFields['recommended_admin_action'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Retry guidance (advisory only)</span>
                                    <span class="value text-end">{{ $hostClassFields['retry_policy_label'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Manual review required</span>
                                    <span class="value text-end">{{ $hostClassFields['manual_review_required'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Source layer</span>
                                    <span class="value text-end">{{ $hostClassFields['source_layer'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Matched signals</span>
                                    <span class="value text-end">
                                        @if ($hostClassBadges !== [])
                                            @foreach ($hostClassBadges as $hostClassSignal)
                                                <span class="badge bg-secondary-lt text-secondary me-1 mb-1">{{ $hostClassSignal }}</span>
                                            @endforeach
                                        @else
                                            {{ $hostClassFields['matched_signals'] ?? display_unknown() }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (($sabreContinuityDiagnostic['show'] ?? false))
                        <div class="border rounded p-3 mb-3 bg-light" id="sabre-continuity-diagnostic-panel" data-testid="sabre-continuity-diagnostic-panel">
                            <h4 class="h6 mb-1">{{ $sabreContinuityDiagnostic['title'] ?? 'Sabre continuity & host classification' }}</h4>
                            <p class="small text-secondary mb-2">{{ $sabreContinuityDiagnostic['disclaimer'] ?? 'Read-only diagnostic from stored booking data.' }}</p>
                            @if ($sabreContinuityDiagnostic['unavailable'] ?? false)
                                <p class="small text-warning mb-0">{{ $sabreContinuityDiagnostic['unavailable_message'] ?? 'Sabre continuity diagnostic unavailable' }}</p>
                            @else
                                <div class="overview-summary-grid mb-2">
                                    @foreach ($sabreContinuityDiagnostic['summary_rows'] ?? [] as $continuityRow)
                                        <div class="overview-kv">
                                            <span class="label">{{ $continuityRow['label'] }}</span>
                                            <span class="value text-end">
                                                {{ $continuityRow['value'] }}
                                                @if (! empty($continuityRow['hint']))
                                                    <span class="d-block small text-secondary fst-italic">{{ $continuityRow['hint'] }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                                @if (($sabreContinuityDiagnostic['source_present_rows'] ?? []) !== [])
                                    <p class="small fw-semibold mb-1">Source layers present</p>
                                    <div class="overview-summary-grid mb-2">
                                        @foreach ($sabreContinuityDiagnostic['source_present_rows'] as $sourceRow)
                                            <div class="overview-kv">
                                                <span class="label">{{ $sourceRow['label'] }}</span>
                                                <span class="value text-end">{{ $sourceRow['value'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if (($sabreContinuityDiagnostic['continuity_field_rows'] ?? []) !== [])
                                    <p class="small fw-semibold mb-1">Continuity field status</p>
                                    <div class="continuity-field-grid mb-0">
                                        @foreach ($sabreContinuityDiagnostic['continuity_field_rows'] as $fieldRow)
                                            <div class="continuity-field-chip">
                                                <span class="text-secondary">{{ $fieldRow['label'] }}:</span>
                                                <span class="badge bg-secondary-lt text-secondary">{{ $fieldRow['value'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif

                    @php
                        $prs = is_array($sa['pnr_retrieve_safety'] ?? null) ? $sa['pnr_retrieve_safety'] : null;
                    @endphp
                    @if ($prs !== null && ($prs['show_panel'] ?? false))
                        <div class="border rounded p-3 mb-3" id="pnr-retrieve-safety-panel">
                            <h4 class="h6 mb-2">PNR retrieve &amp; airline status</h4>
                            <div class="overview-summary-grid mb-2">
                                <div class="overview-kv">
                                    <span class="label">Retrieve result</span>
                                    <span class="value text-end">{{ $prs['retrieve_result_label'] ?? display_unknown() }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Sync status</span>
                                    <span class="value text-end">{{ $prs['sync_status_label'] ?? display_unknown() }}</span>
                                </div>
                                @if (! empty($prs['reason_label']))
                                    <div class="overview-kv">
                                        <span class="label">Reason</span>
                                        <span class="value text-end">{{ $prs['reason_label'] }}</span>
                                    </div>
                                @endif
                                <div class="overview-kv">
                                    <span class="label">Cancel eligible</span>
                                    <span class="value text-end">{{ $prs['cancel_eligible_label'] ?? 'Unknown' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Ticketed by airline</span>
                                    <span class="value text-end">{{ $prs['is_ticketed_label'] ?? 'Unknown' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Ticket numbers</span>
                                    <span class="value text-end">{{ $prs['ticket_numbers_label'] ?? 'Unknown' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Supplier booking ID</span>
                                    <span class="value text-end">{{ $prs['booking_id_label'] ?? 'Unknown' }}</span>
                                </div>
                                @if (! empty($prs['sabre_pnr_label']))
                                    <div class="overview-kv">
                                        <span class="label">Sabre / GDS PNR</span>
                                        <span class="value text-end">{{ $prs['sabre_pnr_label'] }}</span>
                                    </div>
                                @endif
                                <div class="overview-kv">
                                    <span class="label">Airline / carrier locator</span>
                                    <span class="value text-end">{{ $prs['airline_locator_display'] ?? ($prs['airline_locator_label'] ?? 'Not recorded yet') }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Ticketing</span>
                                    <span class="value text-end">{{ $prs['ticketing_status_label'] ?? 'Unknown' }}</span>
                                </div>
                                <div class="overview-kv">
                                    <span class="label">Live supplier cancel (env gate)</span>
                                    <span class="value text-end">{{ $prs['live_cancel_label'] ?? 'Disabled' }}</span>
                                </div>
                            </div>
                            @if (! empty($prs['verification_note']))
                                <p class="small text-warning mb-2"><strong>Verification note:</strong> {{ $prs['verification_note'] }}</p>
                            @endif
                            @if (! empty($prs['segments']))
                                <div class="table-responsive mb-2">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">Leg</th>
                                                <th scope="col">Route</th>
                                                <th scope="col">Flight</th>
                                                <th scope="col">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($prs['segments'] as $prsSegment)
                                                <tr>
                                                    <td>{{ $prsSegment['leg'] ?? display_unknown() }}</td>
                                                    <td>{{ $prsSegment['route_label'] ?? display_unknown() }}</td>
                                                    <td>{{ $prsSegment['flight_label'] ?? display_unknown() }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ $prsSegment['status_class'] ?? 'secondary' }}-lt text-{{ $prsSegment['status_class'] ?? 'secondary' }} me-1">{{ $prsSegment['status_label'] ?? display_unknown() }}</span>
                                                        {{ $prsSegment['segment_status'] ?? display_unknown() }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            <p class="small text-secondary mb-0">Safe summary only{{ display_sep_dot() }}no raw Sabre response is shown.</p>
                        </div>
                    @endif

                    <h4 class="mb-2">Last supplier attempt</h4>
                    @if ($latestAttempt)
                        <p class="mb-1 text-capitalize"><strong>Latest attempt:</strong> {{ $latestAttempt->status }}</p>
                        @if (strtolower((string) $provider) === 'sabre')
                            <p class="mb-1 small"><strong>Endpoint path:</strong> {{ $safeSummary['endpoint_path'] ?? display_unknown() }}</p>
                            <p class="mb-1 small"><strong>Payload schema:</strong> {{ $safeSummary['payload_schema'] ?? display_unknown() }}</p>
                            <p class="mb-1 small"><strong>HTTP status:</strong> {{ $safeSummary['http_status'] ?? display_unknown() }}</p>
                            <p class="mb-1 small"><strong>Revalidation skipped by config:</strong> {{ ! empty($safeSummary['revalidation_skipped_by_config']) ? 'yes' : 'no' }}</p>
                            <p class="mb-1 small"><strong>Revalidation bypass enabled:</strong> {{ ! empty($safeSummary['revalidation_bypass_enabled']) ? 'yes' : 'no' }}</p>
                            <p class="mb-1 small"><strong>Ticketing enabled:</strong> {{ ! empty($safeSummary['ticketing_enabled']) ? 'yes' : 'no' }}</p>
                            <p class="mb-1 small"><strong>Ticketing status:</strong> {{ str_replace('_', ' ', (string) ($booking->ticketing_status ?? 'pending')) }} (manual)</p>
                            <p class="mb-1 small"><strong>Supplier reference:</strong> {{ $latestAttempt->supplier_reference ?? display_unknown() }}</p>
                            <p class="mb-1 small"><strong>Sabre / GDS PNR:</strong> {{ $booking->pnr ?? ($safeSummary['pnr'] ?? display_unknown()) }}</p>
                            <p class="mb-1 small text-secondary"><strong>Ticketing:</strong> {{ __('Disabled / pending manual issue') }}</p>
                        @endif
                        <p class="mb-1"><strong>Error code:</strong> {{ $latestAttempt->error_code ? str_replace('_', ' ', (string) $latestAttempt->error_code) : display_unknown() }}</p>
                        <p class="mb-1"><strong>Attempted at:</strong> {{ $latestAttempt->attempted_at?->format('Y-m-d H:i') ?? display_unknown() }}</p>
                        <p class="mb-1"><strong>Completed at:</strong> {{ $latestAttempt->completed_at?->format('Y-m-d H:i') ?? display_unknown() }}</p>
                        @if ($latestAttempt->error_message)
                            <div class="jp-alert jp-alert--warn py-2 px-3 small">{{ \App\Support\Security\SensitiveDataRedactor::sanitizeErrorMessage($latestAttempt->error_message) }}</div>
                        @endif
                        @if (!empty($safeSummary))
                            <div class="small text-secondary mb-1"><strong>Safe error summary</strong></div>
                            <ul class="small mb-2">
                                @foreach ($safeSummary as $k => $v)
                                    @if ($sa === null || $safeSummaryDisplayKeys === [] || in_array((string) $k, $safeSummaryDisplayKeys, true))
                                        <li>{{ str_replace('_', ' ', (string) $k) }}: {{ is_scalar($v) || $v === null ? (string) ($v ?? display_unknown()) : '[redacted]' }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    @else
                        <p class="text-secondary small mb-2">No supplier attempt logged yet.</p>
                    @endif
                    <p class="mb-0 small text-secondary">
                        @if ($scp !== null && ($scp['show'] ?? false))
                            {{ $scp['staff_guidance'] ?? 'GDS cancellation remains unresolved - staff manual review is required. GDS ticketing is disabled; issue tickets manually.' }}
                        @else
                            Held bookings: monitor payment deadline (PNR / payment required by) in the overview. Mark paid, issue ticket manually, or cancel via existing payment/ticketing tools.
                        @endif
                    </p>
                    @if (!empty($warnings))
                        <div class="small text-secondary mb-1"><strong>Errors / warnings</strong></div>
                        <ul class="small mb-2">
                            @foreach ($warnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif
                        </div>
                    </details>

                    <h4 class="mb-2">Actions</h4>
                    @if ($sa !== null && ($sa['can_sync_pnr_itinerary'] ?? false))
                        <form method="post" action="{{ $syncPnrItineraryRoute }}" class="mb-2">
                            @csrf
                            <button type="submit" class="jp-btn jp-btn--outline w-100">{{ $sa['sync_pnr_itinerary_label'] ?? 'Sync PNR itinerary' }}</button>
                        </form>
                        @if (! empty($sa['sync_pnr_itinerary_help']))
                            <p class="small text-secondary mb-3">{{ $sa['sync_pnr_itinerary_help'] }}</p>
                        @endif
                    @endif
                    @if ($sa !== null && ($sa['can_release_sabre_gds_pnr'] ?? false) && auth()->user()?->can('createSupplierBooking', $booking))
                        <button type="button" class="jp-btn jp-btn--danger w-100 mb-2" data-bs-toggle="modal" data-bs-target="#sabreGdsReleasePnrModal" data-testid="sabre-gds-release-pnr-action">
                            {{ $sa['release_sabre_gds_pnr_label'] ?? 'Release PNR' }}
                        </button>
                        @if (! empty($sa['release_sabre_gds_pnr_help']))
                            <p class="small text-secondary mb-3">{{ $sa['release_sabre_gds_pnr_help'] }}</p>
                        @endif
                    @endif
                    @if ($sa !== null && ($sa['sabre_gds_pnr_cancelled_or_released'] ?? false))
                        <div class="jp-alert jp-alert--warn py-2 px-3 small mb-3">
                            <strong>PNR released/cancelled.</strong>
                            {{ $sa['sabre_gds_manual_close_message'] ?? 'Handle refund/credit manually or close booking manually.' }}
                        </div>
                    @endif
                    @if (! $isPiaNdcProvider)
                        @if ($canCreatePnr)
                            <form method="post" action="{{ $bookingRoute }}">
                                @csrf
                                <button type="submit" class="jp-btn jp-btn--primary w-100 mb-2">{{ $sa['create_supplier_booking_label'] ?? 'Create supplier booking / PNR' }}</button>
                            </form>
                        @else
                            <button type="button" class="jp-btn jp-btn--ghost w-100 mb-2" disabled>{{ $sa['create_supplier_booking_label'] ?? 'Create supplier booking / PNR' }}</button>
                            <p class="text-muted small mt-n1 mb-2">{{ $createPnrReason !== '' ? $createPnrReason : 'Payment unpaid / invalid passengers / expired offer' }}</p>
                        @endif
                    @endif
                    @if ($isPiaNdcProvider && ($piaNdcRelease['can_release'] ?? false))
                        <button type="button" class="jp-btn jp-btn--danger w-100 mb-2" data-bs-toggle="modal" data-bs-target="#piaNdcReleaseModal" data-testid="pia-ndc-release-action">
                            Release PIA NDC Option PNR
                        </button>
                    @endif

                    @if ($sa !== null && ($sa['can_prepare_supplier_context'] ?? false))
                        <form method="post" action="{{ $prepareSupplierContextRoute }}" class="mb-2">
                            @csrf
                            <button type="submit" class="jp-btn jp-btn--outline w-100">{{ $sa['prepare_supplier_context_label'] ?? 'Prepare supplier PNR context' }}</button>
                        </form>
                        @if (! empty($sa['prepare_supplier_context_help']))
                            <p class="small text-secondary mb-3">{{ $sa['prepare_supplier_context_help'] }}</p>
                        @endif
                    @elseif ($sa !== null && ($sa['connecting_same_carrier_candidate'] ?? false) && ($sa['pricing_context_ready'] ?? false))
                        <button type="button" class="jp-btn jp-btn--ghost w-100 mb-2" disabled>Prepare supplier PNR context</button>
                        <p class="text-muted small mt-n1 mb-2">Pricing context is already complete.</p>
                    @endif

                    @if ($canRetrySupplier)
                        <form method="post" action="{{ $bookingRoute }}">
                            @csrf
                            <button type="submit" class="jp-btn jp-btn--outline w-100 mb-2">Retry supplier booking</button>
                        </form>
                        @if (! empty($sa['retry_pnr_refresh_helper']))
                            <p class="small text-secondary mb-2">{{ $sa['retry_pnr_refresh_helper'] }}</p>
                        @endif
                    @else
                        <button type="button" class="jp-btn jp-btn--ghost w-100 mb-2" disabled>Retry supplier booking</button>
                        <p class="text-muted small mt-n1 mb-2">{{ $retryReason }}</p>
                    @endif

                    @if ($canMarkManualPnr)
                        <form method="post" action="{{ $manualPnrRoute }}" class="border rounded p-2 mb-2">
                            @csrf
                            <div class="mb-2">
                                <label class="jp-label">Manual PNR</label>
                                <input type="text" name="pnr" class="jp-control" maxlength="32" required>
                            </div>
                            <div class="mb-2">
                                <label class="jp-label">Supplier reference</label>
                                <input type="text" name="supplier_reference" class="jp-control" maxlength="255">
                            </div>
                            <div class="mb-2">
                                <label class="jp-label">Note</label>
                                <textarea name="note" class="jp-control" rows="2" maxlength="1000"></textarea>
                            </div>
                            <button type="submit" class="jp-btn jp-btn--outline w-100">Mark manual PNR</button>
                        </form>
                    @else
                        <button type="button" class="jp-btn jp-btn--ghost w-100" disabled>Mark manual PNR</button>
                        <p class="text-muted small mt-2 mb-2">
                            @if ($sa !== null && ($sa['has_pnr_or_reference'] ?? false))
                                PNR or supplier reference already recorded. Use booking notes to document corrections.
                            @elseif (! $canMarkManualPnr && $viewer && method_exists($viewer, 'isStaff') && ! $viewer->isStaff() && ! $viewer->isPlatformAdmin())
                                Permission denied
                            @else
                                Manual PNR entry is not available for this booking.
                            @endif
                        </p>
                    @endif

                    @if ($canViewDiagnostics)
                        <details class="mt-2">
                            <summary class="small fw-semibold">View safe diagnostics</summary>
                            <div class="small text-secondary mt-2">
                                <div><strong>Attempt ID:</strong> {{ $latestAttempt->id ?? display_unknown() }}</div>
                                <div><strong>Error code:</strong> {{ $latestAttempt->error_code ?? display_unknown() }}</div>
                                <div><strong>Error message:</strong> {{ $latestAttempt->error_message ?? display_unknown() }}</div>
                                <div><strong>Safe summary fields:</strong> {{ !empty($safeSummary) ? implode(', ', array_keys($safeSummary)) : 'none' }}</div>
                            </div>
                        </details>
                    @else
                        <button type="button" class="jp-btn jp-btn--ghost w-100 mt-2" disabled>View safe diagnostics</button>
                        <p class="text-muted small mt-1 mb-0">Restricted</p>
                    @endif
                </div>
            </div>
            <div class="jp-card" id="ticketing-panel" data-tab-section="ticketing">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Ticketing</h3></div>
                <div class="jp-card__body">
                    @php
                        $latestTicketAttempt = $booking->ticketingAttempts->sortByDesc('created_at')->first();
                        $ticketRoute = $p === 'staff' ? route('staff.bookings.issue-ticket', $booking) : route('admin.bookings.issue-ticket', $booking);
                        $provider = (string) ($booking->latestSupplierBooking?->provider ?? $booking->supplier ?? '');
                        $gdsTicketingActionState = (string) ($sabreGdsTicketing['action_state'] ?? 'not_eligible');
                        $sabreGdsIssueTicketLabel = ($provider === 'sabre' && ($sabreGdsTicketing['show'] ?? false))
                            ? (string) ($sabreGdsTicketing['action_label'] ?? 'Issue ticket')
                            : 'Issue ticket';
                        $providerSupported = in_array($provider, ['sabre', 'pia_ndc', 'airblue', 'airline_direct', 'iati'], true);
                        $providerTicketingLabel = ($provider ?? '') === 'iati'
                            ? 'IATI confirm/book'
                            : ($providerSupported ? 'Mock ticketing' : 'Real provider (not supported)');
                        $isPaid = (string) ($booking->payment_status ?? 'unpaid') === 'paid';
                        $hasPnr = ((string) ($booking->pnr ?? '')) !== '';
                        $piaNdcTicketVoidedTab = $isPiaNdcProvider && PiaNdcVoidLocalReconciliation::isVoided($booking);
                        $piaNdcVoidRequiresReviewTab = $isPiaNdcProvider && PiaNdcVoidLocalReconciliation::requiresVoidReview($booking);
                        $hasActiveIssuedTicketsTab = $booking->tickets->contains(fn ($ticket) => strtolower((string) ($ticket->status ?? '')) !== 'voided');
                        $alreadyTicketed = ! $piaNdcTicketVoidedTab && ($hasActiveIssuedTicketsTab || in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true));
                        $canIssueByRules = $isPaid && $hasPnr && ! $alreadyTicketed;
                        $canIssueTicketAction = $sa !== null
                            ? (bool) ($sa['can_issue_ticket_action'] ?? false)
                            : (($ticketingEligible ?? false) && $canIssueByRules);
                        $canIssueTicketUi = $canIssueTicketAction
                            && auth()->user()?->can('issueTicket', $booking) === true;
                        if ($provider === 'sabre' && ($sabreGdsTicketing['show'] ?? false)) {
                            $canIssueTicketUi = ($sabreGdsTicketing['can_execute'] ?? false)
                                && $gdsTicketingActionState === 'issue_ticket'
                                && auth()->user()?->can('issueTicket', $booking) === true;
                        }
                        if ($sa !== null && ($sa['sabre_gds_pnr_cancelled_or_released'] ?? false)) {
                            $canIssueTicketUi = false;
                        }
                        $canIssueTicket = $canIssueTicketUi;
                        $hasFailedTicketingAttempt = in_array((string) ($latestTicketAttempt?->status ?? ''), ['failed'], true);
                        $canRetryTicketing = $sa !== null ? (bool) ($sa['can_retry_ticketing'] ?? false) : $hasFailedTicketingAttempt;
                        $ticketingStatusMessage = $piaNdcTicketVoidedTab
                            ? 'Ticket voided — option PNR remains until released.'
                            : ($piaNdcVoidRequiresReviewTab
                                ? 'Void requires admin review before closing this booking.'
                                : ($alreadyTicketed
                                    ? 'Tickets issued.'
                                    : (($provider === 'sabre' && ($sabreGdsTicketing['show'] ?? false) && trim((string) ($sabreGdsTicketing['admin_message'] ?? '')) !== '')
                                        ? (string) $sabreGdsTicketing['admin_message']
                                        : ($sa !== null
                                            ? (trim((string) ($sa['issue_ticket_disabled_reason'] ?? '')) !== ''
                                                ? (string) $sa['issue_ticket_disabled_reason']
                                                : (string) ($sa['ticketing_status_message'] ?? 'Manual ticketing required.'))
                                            : 'Automated ticketing is not available until certified.'))));
                        $hasTicketArtifacts = $booking->tickets->isNotEmpty() || $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'ticket_itinerary');
                        $contactEmail = trim((string) ($booking->contact?->email ?? $booking->customer?->email ?? ''));
                        $contactPhone = trim((string) ($booking->contact?->phone ?? $booking->customer?->phone ?? ''));
                        $hasContact = $contactEmail !== '' || $contactPhone !== '';
                        $canSendTicketEmail = $hasTicketArtifacts && $hasContact;
                        $canVoidTicket = false;
                        $ticketRuleReason = ! $isPaid
                            ? 'Cannot issue ticket: payment is unpaid.'
                            : (! $hasPnr && ($provider ?? '') !== 'iati'
                                ? 'Cannot issue ticket: supplier PNR is missing.'
                                : ($alreadyTicketed
                                    ? 'Cannot issue ticket twice: ticket already issued.'
                                    : (($provider ?? '') === 'iati'
                                        ? (($sa['retry_ticketing_reason'] ?? '') !== '' ? (string) $sa['retry_ticketing_reason'] : 'Confirm / book the IATI order from the ticketing panel.')
                                        : (! $providerSupported
                                            ? 'Real supplier ticketing is not supported until certified.'
                                            : 'Ticketing prerequisites are not complete.'))));
                    @endphp
                    @php
                        $ticketingReadiness = TicketingReadinessPresenter::forBooking($booking);
                        $readinessStatusClass = match ($ticketingReadiness['overall_status']) {
                            TicketingReadinessPresenter::OVERALL_READY_EXCEPT_TICKETING_DISABLED => 'alert-success',
                            TicketingReadinessPresenter::OVERALL_MANUAL_REVIEW_WITH_WARNINGS => 'alert-warning',
                            default => 'alert-warning',
                        };
                        $readinessItemClass = static function (string $status): string {
                            return match ($status) {
                                'pass' => 'text-success',
                                'warning' => 'text-warning',
                                'fail' => 'text-danger',
                                'blocked' => 'text-secondary',
                                default => 'text-secondary',
                            };
                        };
                        $readinessItemIcon = static function (string $status): string {
                            return match ($status) {
                                'pass' => '?',
                                'warning' => '!',
                                'fail' => '?',
                                'blocked' => '--',
                                default => '--',
                            };
                        };
                    @endphp
                    <h4 class="mb-2">Ticketing readiness checklist</h4>
                    <div class="alert {{ $readinessStatusClass }} py-2 px-3 small mb-3" role="status">
                        <strong>{{ $ticketingReadiness['overall_label'] }}</strong>
                        @if ($ticketingReadiness['overall_status'] === TicketingReadinessPresenter::OVERALL_READY_EXCEPT_TICKETING_DISABLED)
                            <div class="mt-1 mb-0">Ready for manual ticketing review{{ display_sep_dot() }}live API ticketing remains disabled.</div>
                        @elseif ($ticketingReadiness['overall_status'] === TicketingReadinessPresenter::OVERALL_MANUAL_REVIEW_WITH_WARNINGS)
                            <div class="mt-1 mb-0">Warnings are present{{ display_sep_dot() }}operator confirmation is required before supplier ticketing actions.</div>
                        @endif
                    </div>
                    <ul class="list-unstyled small mb-3">
                        @foreach ($ticketingReadiness['items'] as $readinessItem)
                            <li class="mb-2 {{ $readinessItemClass($readinessItem['status']) }}">
                                <span class="me-1" aria-hidden="true">{{ $readinessItemIcon($readinessItem['status']) }}</span>
                                <strong>{{ $readinessItem['label'] }}:</strong>
                                {{ $readinessItem['message'] }}
                            </li>
                        @endforeach
                    </ul>
                    <p class="mb-1"><strong>Provider:</strong> {{ $provider !== '' ? $provider : display_unknown() }}</p>
                    <p class="mb-1 text-capitalize"><strong>Payment status:</strong> {{ str_replace('_', ' ', (string) ($booking->payment_status ?? 'unpaid')) }}</p>
                    <p class="mb-1"><strong>PNR:</strong> {{ $booking->pnr ?? display_unknown() }}</p>
                    <p class="mb-2 text-capitalize"><strong>Ticketing status:</strong> {{ str_replace('_', ' ', (string) ($booking->ticketing_status ?? 'not started')) }}</p>

                    <h4 class="mb-2">Actions</h4>
                    @php $piaNdcTicketing = $piaNdcTicketing ?? ['show' => false]; @endphp
                    @if ($isPiaNdcProvider && ($piaNdcTicketing['show'] ?? false) && ! empty($piaNdcTicketing['warnings']))
                        <div class="jp-alert jp-alert--warn py-2 px-3 small mb-3" role="alert">
                            @foreach ($piaNdcTicketing['warnings'] as $piaWarning)
                                <div>{{ $piaWarning }}</div>
                            @endforeach
                        </div>
                    @endif
                    @if ($isPiaNdcProvider && ($piaNdcTicketing['show'] ?? false))
                        <div class="border rounded p-2 mb-3 small" data-testid="pia-ndc-void-status-panel">
                            <div><strong>Void status:</strong> {{ $piaNdcTicketing['void_status'] ?? '—' }}</div>
                            <div><strong>Latest void attempt:</strong> {{ $piaNdcTicketing['latest_void_attempt_status'] ?? '—' }} @if(($piaNdcTicketing['latest_void_attempt_at'] ?? '—') !== '—')<span class="text-secondary">({{ $piaNdcTicketing['latest_void_attempt_at'] }})</span>@endif</div>
                            <div><strong>Ticket number(s):</strong> {{ $piaNdcTicketing['ticket_numbers'] ?? '—' }}</div>
                            <div><strong>Supplier response:</strong> {{ $piaNdcTicketing['latest_void_supplier_summary'] ?? '—' }}</div>
                        </div>
                        @error('pia_ndc_ticket_preview')
                            <div class="jp-alert jp-alert--danger py-2 px-3 small">{{ $message }}</div>
                        @enderror
                        @error('pia_ndc_void_ticket')
                            <div class="jp-alert jp-alert--danger py-2 px-3 small">{{ $message }}</div>
                        @enderror
                        @error('pia_ndc_eticket_resend')
                            <div class="jp-alert jp-alert--danger py-2 px-3 small">{{ $message }}</div>
                        @enderror
                        @error('ticketing_confirm')
                            <div class="jp-alert jp-alert--danger py-2 px-3 small">{{ $message }}</div>
                        @enderror
                        @error('ticketing')
                            <div class="jp-alert jp-alert--danger py-2 px-3 small">{{ $message }}</div>
                        @enderror
                        @if (($piaNdcTicketing['can_preview'] ?? false))
                            <button type="button" class="jp-btn jp-btn--outline w-100 mb-2" data-bs-toggle="modal" data-bs-target="#piaNdcTicketPreviewModal" data-testid="pia-ndc-ticket-preview-action">Ticket preview</button>
                        @else
                            <button type="button" class="jp-btn jp-btn--ghost w-100 mb-2" disabled title="{{ $piaNdcTicketing['preview_blocked_reason'] ?? 'Ticket preview is not available.' }}">Ticket preview</button>
                            <p class="text-muted small mt-n1 mb-2">{{ $piaNdcTicketing['preview_blocked_reason'] ?? 'Ticket preview is not available.' }}</p>
                        @endif
                    @endif
                    @if ($piaNdcTicketVoidedTab)
                        <p class="text-warning small mb-2"><strong>Ticket voided.</strong> Issued ticket numbers are voided; option PNR may remain active until released.</p>
                    @elseif ($piaNdcVoidRequiresReviewTab)
                        <div class="jp-alert jp-alert--warn py-2 px-3 small mb-2" role="alert"><strong>Void requires review.</strong> Supplier void evidence is ambiguous — verify before retrying void.</div>
                    @elseif ($alreadyTicketed)
                        <p class="text-success small mb-2"><strong>{{ ($provider === 'sabre' && ($sabreGdsTicketing['show'] ?? false)) ? ($sabreGdsTicketing['action_label'] ?? 'Ticketed') : 'Ticketing completed.' }}</strong> Ticket records are read-only on this booking.</p>
                    @elseif ($canIssueTicket || ($isPiaNdcProvider && ($piaNdcTicketing['can_issue'] ?? false)))
                        @if ($isPiaNdcProvider)
                            <button type="button" class="jp-btn jp-btn--primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#piaNdcIssueTicketModal" data-testid="pia-ndc-issue-ticket-action">{{ $sa['issue_ticket_label'] ?? 'Issue ticket' }}</button>
                        @else
                            @php $sabreTicketingConfirmPhrase = $provider === 'sabre' ? 'ISSUE-TICKET-FOR-BOOKING-'.$booking->id : null; @endphp
                            <form method="post" action="{{ $ticketRoute }}" class="ota-admin-supplier-action-form">
                                @csrf
                                @if ($sabreTicketingConfirmPhrase)
                                    <label class="jp-label small" for="ticketing_confirm">Sabre confirmation phrase</label>
                                    <input type="text" name="ticketing_confirm" id="ticketing_confirm" class="jp-control jp-control-sm mb-2" placeholder="{{ $sabreTicketingConfirmPhrase }}" autocomplete="off">
                                    <p class="text-muted small mb-2">Type exactly: <code>{{ $sabreTicketingConfirmPhrase }}</code></p>
                                @endif
                                <button type="submit" class="jp-btn jp-btn--primary w-100 mb-2">{{ $sabreGdsIssueTicketLabel }}</button>
                            </form>
                        @endif
                    @else
                        <button type="button" class="jp-btn jp-btn--ghost w-100 mb-2" disabled title="{{ $isPiaNdcProvider ? ($piaNdcTicketing['issue_blocked_reason'] ?? $ticketingStatusMessage) : $ticketingStatusMessage }}">Issue ticket</button>
                        <p class="text-muted small mt-n1 mb-2">{{ $isPiaNdcProvider ? ($piaNdcTicketing['issue_blocked_reason'] ?? $ticketingStatusMessage) : $ticketingStatusMessage }}</p>
                    @endif

                    <form method="post" action="{{ $docItineraryUrl }}" class="mb-2 ota-admin-supplier-action-form">
                        @csrf
                        <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled($booking->tickets->isEmpty()) title="{{ $booking->tickets->isEmpty() ? 'Ticket must be issued first.' : 'Generate ticket itinerary document.' }}">Generate ticket itinerary</button>
                    </form>
                    @if ($booking->tickets->isEmpty())
                        <p class="text-muted small mt-n1 mb-2">Generate ticket itinerary is unavailable until a ticket is issued.</p>
                    @endif
                    @if ($canRetryTicketing)
                        <form method="post" action="{{ $ticketRoute }}" class="mb-2 ota-admin-supplier-action-form">
                            @csrf
                            @if (($provider ?? '') === 'sabre')
                                <input type="hidden" name="ticketing_confirm" value="ISSUE-TICKET-FOR-BOOKING-{{ $booking->id }}">
                            @elseif ($isPiaNdcProvider)
                                <input type="hidden" name="admin_confirm_reviewed" value="1">
                            @endif
                            <button type="submit" class="jp-btn jp-btn--outline w-100">Retry ticketing</button>
                        </form>
                    @else
                        <button type="button" class="jp-btn jp-btn--ghost w-100 mb-2" disabled title="{{ $sa['retry_ticketing_reason'] ?? 'Automated ticketing retry is disabled.' }}">Retry ticketing</button>
                        <p class="text-muted small mt-n1 mb-2">{{ $sa['retry_ticketing_reason'] ?? 'Automated ticketing retry is disabled.' }}</p>
                    @endif

                    @if ($booking->tickets->isEmpty())
                        <p class="text-muted small mt-n1 mb-2">No issued tickets</p>
                    @endif

                    @if ($isPiaNdcProvider && ($piaNdcTicketing['show'] ?? false))
                        @if (($piaNdcTicketing['can_resend_eticket'] ?? false))
                            <form method="post" action="{{ route($p.'.bookings.resend-pia-ndc-eticket', $booking) }}" class="mb-2">
                                @csrf
                                <label class="jp-label small" for="pia_resend_confirm">Resend e-ticket confirmation</label>
                                <input id="pia_resend_confirm" type="text" name="confirm_phrase" class="jp-control jp-control-sm mb-2" required autocomplete="off" placeholder="{{ $piaNdcTicketing['resend_confirm_phrase'] ?? 'RESEND_PIA_ETICKET' }}">
                                <p class="text-muted small mb-2">Type exactly: <code>{{ $piaNdcTicketing['resend_confirm_phrase'] ?? 'RESEND_PIA_ETICKET' }}</code></p>
                                <button type="submit" class="jp-btn jp-btn--outline w-100" data-testid="pia-ndc-resend-eticket-action">Send / Resend E-ticket Email</button>
                            </form>
                        @else
                            <button type="button" class="jp-btn jp-btn--ghost w-100 mb-2" disabled>Send / Resend E-ticket Email</button>
                            <p class="text-muted small mt-n1 mb-2">{{ $piaNdcTicketing['resend_blocked_reason'] ?? 'E-ticket resend is not available.' }}</p>
                        @endif
                        @if (($piaNdcTicketing['can_void'] ?? false))
                            <form method="post" action="{{ route($p.'.bookings.void-pia-ndc-ticket', $booking) }}" class="mb-2">
                                @csrf
                                <label class="jp-label small" for="pia_void_confirm">Void ticket confirmation</label>
                                <input id="pia_void_confirm" type="text" name="confirm_phrase" class="jp-control jp-control-sm mb-2" required autocomplete="off" placeholder="{{ $piaNdcTicketing['void_confirm_phrase'] ?? 'VOID_PIA_NDC_TICKET' }}">
                                <label class="jp-label small" for="pia_void_reason">Operator reason</label>
                                <textarea id="pia_void_reason" name="operator_reason" class="jp-control jp-control-sm mb-2" rows="2" required maxlength="500"></textarea>
                                <p class="text-muted small mb-2">Type exactly: <code>{{ $piaNdcTicketing['void_confirm_phrase'] ?? 'VOID_PIA_NDC_TICKET' }}</code></p>
                                <button type="submit" class="jp-btn jp-btn--danger w-100" data-testid="pia-ndc-void-ticket-action">Void ticket</button>
                            </form>
                        @else
                            <button type="button" class="jp-btn jp-btn--ghost w-100 mb-2" disabled>Void ticket</button>
                            <p class="text-muted small mt-n1 mb-2">{{ $piaNdcTicketing['void_blocked_reason'] ?? 'Void ticket is not available.' }}</p>
                        @endif
                    @else
                        <button type="button" class="jp-btn jp-btn--outline w-100 mb-2" @disabled(! $canSendTicketEmail)>Send ticket email</button>
                        @if (! $canSendTicketEmail)
                            <p class="text-muted small mt-n1 mb-2">No ticket/contact</p>
                        @endif
                        <details class="mb-3" data-testid="admin-booking-planned-ticketing-actions">
                            <summary class="small text-secondary">Planned ticketing actions</summary>
                            <button type="button" class="jp-btn jp-btn--ghost btn-sm w-100 mt-2" disabled>Void ticket / request void</button>
                            <p class="text-muted small mt-2 mb-0">GDS void automation is disabled{{ display_sep_dot() }}use manual supplier workflow when required.</p>
                        </details>
                    @endif

                    @if ($isPiaNdcProvider && in_array((string) ($booking->ticketing_status ?? ''), ['ticketing_requires_review', 'issued_pending_document_sync'], true))
                        <div class="jp-alert jp-alert--warn py-2 px-3 small mb-3" role="alert">
                            <strong>Ticketing status requires review.</strong>
                            Supplier ticketing may have succeeded, but local ticket documents are incomplete. Verify supplier PNR/ticket numbers before closing this booking.
                        </div>
                    @endif

                    @if ($isPiaNdcProvider && ($piaNdcTicketing['show'] ?? false))
                        @php
                            $piaModalFareTotal = number_format($totalDue, 0).' '.strtoupper((string) ($booking->currency ?? 'PKR'));
                            $piaModalBrand = '';
                            if (is_array($piaNdcSelectedFare['selected'] ?? null)) {
                                $piaModalBrand = trim((string) ($piaNdcSelectedFare['selected']['brand_name'] ?? ''));
                            }
                            if ($piaModalBrand === '' && is_array($piaNdcSelectedFare['outbound'] ?? null)) {
                                $piaModalBrand = trim((string) ($piaNdcSelectedFare['outbound']['brand_name'] ?? ''));
                            }
                            $piaModalItineraryWarning = ! ($piaNdcTicketing['itinerary_synced'] ?? false);
                        @endphp
                        <div class="modal fade" id="piaNdcTicketPreviewModal" tabindex="-1" aria-labelledby="piaNdcTicketPreviewModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post" action="{{ route($p.'.bookings.preview-pia-ndc-ticket', $booking) }}" class="ota-admin-supplier-action-form">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="piaNdcTicketPreviewModalLabel">Continue with ticket preview?</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <dl class="row small mb-3">
                                                <dt class="col-sm-5">Booking ref</dt><dd class="col-sm-7">{{ $bookingRef }}</dd>
                                                <dt class="col-sm-5">PNR</dt><dd class="col-sm-7">{{ $booking->pnr ?? display_unknown() }}</dd>
                                                <dt class="col-sm-5">Passengers</dt><dd class="col-sm-7">{{ $paxCount }}</dd>
                                                <dt class="col-sm-5">Fare total</dt><dd class="col-sm-7">{{ $piaModalFareTotal }}</dd>
                                                <dt class="col-sm-5">Selected brand</dt><dd class="col-sm-7">{{ $piaModalBrand !== '' ? $piaModalBrand : display_unknown() }}</dd>
                                            </dl>
                                            @if ($piaModalItineraryWarning)
                                                <div class="jp-alert jp-alert--warn py-2 px-3 small mb-3">PNR itinerary is not synced. Verify itinerary with supplier before continuing.</div>
                                            @endif
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="admin_confirm_reviewed" value="1" id="pia_preview_modal_confirm" required>
                                                <label class="form-check-label small" for="pia_preview_modal_confirm">I reviewed this booking and want to continue to ticket preview.</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="jp-btn jp-btn--secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="jp-btn jp-btn--primary">Continue to ticket preview</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="modal fade" id="piaNdcIssueTicketModal" tabindex="-1" aria-labelledby="piaNdcIssueTicketModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post" action="{{ $ticketRoute }}" class="ota-admin-supplier-action-form">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="piaNdcIssueTicketModalLabel">Issue ticket?</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <dl class="row small mb-3">
                                                <dt class="col-sm-5">Booking ref</dt><dd class="col-sm-7">{{ $bookingRef }}</dd>
                                                <dt class="col-sm-5">PNR</dt><dd class="col-sm-7">{{ $booking->pnr ?? display_unknown() }}</dd>
                                                <dt class="col-sm-5">Passengers</dt><dd class="col-sm-7">{{ $paxCount }}</dd>
                                                <dt class="col-sm-5">Fare total</dt><dd class="col-sm-7">{{ $piaModalFareTotal }}</dd>
                                                <dt class="col-sm-5">Payment status</dt><dd class="col-sm-7 text-capitalize">{{ str_replace('_', ' ', (string) ($booking->payment_status ?? 'unpaid')) }}</dd>
                                                <dt class="col-sm-5">Selected brand</dt><dd class="col-sm-7">{{ $piaModalBrand !== '' ? $piaModalBrand : display_unknown() }}</dd>
                                            </dl>
                                            @if ($piaModalItineraryWarning)
                                                <div class="jp-alert jp-alert--warn py-2 px-3 small mb-3">PNR itinerary is not synced. Verify itinerary with supplier before issuing.</div>
                                            @endif
                                            @if (! empty($piaNdcTicketing['warnings']))
                                                @foreach ($piaNdcTicketing['warnings'] as $issueWarning)
                                                    <div class="jp-alert jp-alert--warn py-2 px-3 small mb-2">{{ $issueWarning }}</div>
                                                @endforeach
                                            @endif
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="admin_confirm_reviewed" value="1" id="pia_issue_modal_confirm" required>
                                                <label class="form-check-label small" for="pia_issue_modal_confirm">I reviewed this booking and approve ticket issuance.</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="jp-btn jp-btn--secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="jp-btn jp-btn--primary">Issue ticket</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif

                    <h4 class="mb-2">Latest ticketing attempt</h4>
                    @if ($latestTicketAttempt)
                        <div class="mt-3 small">
                            <strong>Latest attempt:</strong> <span class="text-capitalize">{{ $latestTicketAttempt->status }}</span>
                            <div><strong>Attempted at:</strong> {{ $latestTicketAttempt->attempted_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
                            <div><strong>Completed at:</strong> {{ $latestTicketAttempt->completed_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
                            @if ($latestTicketAttempt->error_message)
                                <div class="jp-alert jp-alert--warn py-2 px-3 mt-2 mb-0">
                                    <strong>Ticketing error:</strong> {{ $latestTicketAttempt->error_message }}
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="small text-secondary mb-3">No ticketing attempt logged yet.</p>
                    @endif

                    <h4 class="mb-2">Issued tickets</h4>
                    @if ($booking->tickets->isNotEmpty())
                        @foreach ($booking->tickets as $ticket)
                            @php $ticketVoided = strtolower((string) ($ticket->status ?? '')) === 'voided' || strtolower((string) ($ticket->void_status ?? '')) === 'voided'; @endphp
                            <div class="border rounded p-2 mb-2 {{ $ticketVoided ? 'border-warning bg-light' : '' }}">
                                <div><strong>Ticket number:</strong> {{ $ticket->ticket_number ?? display_unknown() }} @if($ticketVoided)<span class="badge text-bg-warning ms-1">VOIDED</span>@endif</div>
                                <div><strong>PNR:</strong> {{ $ticket->pnr ?? display_unknown() }}</div>
                                <div><strong>Status:</strong> {{ $ticketVoided ? 'Voided' : ucfirst((string) ($ticket->status ?? display_unknown())) }}</div>
                                <div><strong>Ticket issue date:</strong> {{ $ticket->issued_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
                                @if ($ticketVoided)
                                    <div><strong>Voided at:</strong> {{ $ticket->voided_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
                                @endif
                                <div class="small text-secondary"><strong>Passenger mapping:</strong> {{ $ticket->passenger?->first_name }} {{ $ticket->passenger?->last_name }} (ID: {{ $ticket->passenger_id ?? display_unknown() }})</div>
                                <div class="small text-secondary"><strong>Provider type:</strong> {{ $providerTicketingLabel ?? ($providerSupported ? 'Mock ticketing' : 'Real provider (not supported)') }}</div>
                            </div>
                        @endforeach
                    @else
                        <p class="small text-secondary mb-0">No issued tickets yet.</p>
                    @endif
                </div>
            </div>
            <div class="jp-card" id="payments" data-tab-section="payments">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Payments</h3></div>
                <div class="jp-card__body">
                    @php
                        $verifiedTotal = (float) ($booking->amount_paid ?? 0);
                        $balanceDue = $booking->balance_due !== null ? (float) $booking->balance_due : max(0, $totalDue - $verifiedTotal);
                        $contactEmail = trim((string) ($booking->contact?->email ?? $booking->customer?->email ?? ''));
                        $contactPhone = trim((string) ($booking->contact?->phone ?? $booking->customer?->phone ?? ''));
                        $hasContact = $contactEmail !== '' || $contactPhone !== '';
                        $isPaymentBlockedByBookingState = in_array((string) $booking->status->value, ['cancelled', 'refunded'], true);
                        $hasPendingProof = $booking->payments->contains(fn ($pay) => (string) $pay->status->value === 'submitted' && (string) ($pay->proof_path ?? '') !== '');
                        $hasVerifiedPayment = $booking->payments->contains(fn ($pay) => (string) $pay->status->value === 'verified');
                        $viewer = auth()->user();
                        $canUseMarkPaidOverride = $viewer && method_exists($viewer, 'isPlatformAdmin') && $viewer->isPlatformAdmin();
                        $canRecordManualPayment = $balanceDue > 0 && ! $isPaymentBlockedByBookingState;
                        $recordManualPaymentReason = $canRecordManualPayment ? '' : ($isPaymentBlockedByBookingState ? 'No balance / cancelled' : 'No balance / cancelled');
                        $canMarkAsPaid = (($verifiedTotal >= $totalDue && $totalDue > 0) || ($canUseMarkPaidOverride && $balanceDue > 0 && ! $isPaymentBlockedByBookingState));
                        $markAsPaidReason = $canMarkAsPaid ? '' : 'Insufficient paid amount';
                        $canGeneratePaymentReceipt = $hasVerifiedPayment;
                        $receiptReason = $canGeneratePaymentReceipt ? '' : 'No verified payment';
                        $canSendReminder = $balanceDue > 0 && $hasContact;
                        $sendReminderReason = $canSendReminder ? '' : 'No balance / no contact';
                        $canSendConfirmation = in_array((string) ($booking->payment_status ?? 'unpaid'), ['paid', 'partial'], true) && $hasContact;
                        $sendConfirmationReason = $canSendConfirmation ? '' : 'No verified payment';
                        $verificationHistory = $booking->payments
                            ->filter(fn ($pay) => in_array((string) $pay->status->value, ['verified', 'rejected'], true))
                            ->sortByDesc('updated_at')
                            ->values();
                        $paymentReceiptDocs = $booking->documents
                            ->filter(fn ($doc) => (string) $doc->document_type->value === 'payment_receipt')
                            ->sortByDesc('created_at')
                            ->values();
                    @endphp
                    @php
                        $adminPaymentSummary = \App\Support\Bookings\BookingPaymentSummaryPresenter::forBooking($booking, false, 'customer');
                        $pendingProofPayments = $booking->payments->filter(fn ($pay) => (string) $pay->status->value === 'submitted');
                    @endphp
                    <h4 class="mb-2">Payment summary</h4>
                    <div class="row g-2 mb-3 small" data-testid="admin-booking-payment-summary">
                        <div class="col-md-6"><strong>Fare total:</strong> Rs {{ number_format($fareTotal, 0) }}</div>
                        @if (filled($booking->promo_code))
                            <div class="col-md-6"><strong>Promo ({{ e($booking->promo_code) }}):</strong> −Rs {{ number_format($promoDiscountAmount, 0) }}</div>
                            <div class="col-md-6"><strong>Final payable:</strong> Rs {{ number_format($totalDue, 0) }}</div>
                        @else
                            <div class="col-md-6"><strong>Total:</strong> Rs {{ number_format($totalDue, 0) }}</div>
                        @endif
                        <div class="col-md-6"><strong>Paid (verified):</strong> Rs {{ number_format($verifiedTotal, 0) }}</div>
                        <div class="col-md-6"><strong>Balance due:</strong> Rs {{ number_format($balanceDue, 0) }}</div>
                        <div class="col-md-6"><strong>Status:</strong> {{ $adminPaymentSummary['status_label'] }}</div>
                        @if (! empty($adminPaymentSummary['last_activity_at']))
                            <div class="col-12 text-secondary"><strong>Last activity:</strong> {{ $adminPaymentSummary['last_activity_label'] }}{{ display_sep_dot() }}{{ $adminPaymentSummary['last_activity_at'] }}</div>
                        @endif
                    </div>
                    <p class="small text-secondary mb-3">{{ $adminPaymentSummary['status_meaning'] }}</p>

                    @if ($hasPendingProof)
                        <div class="jp-alert jp-alert--warn py-2 px-3 small mb-3" data-testid="admin-pending-payment-proof">
                            <strong>Pending proof review.</strong> {{ $pendingProofPayments->count() }} payment proof(s) awaiting verification. Use verify/reject on each submitted record below.
                        </div>
                    @endif

                    <h4 class="mb-2">Quick actions</h4>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <a href="#payment-record-form" class="jp-btn jp-btn--sm jp-btn--primary {{ $canRecordManualPayment ? '' : 'disabled' }}" @if(! $canRecordManualPayment) aria-disabled="true" @endif>Record manual payment</a>
                        @if ($hasPendingProof)
                            <a href="#payment-records" class="jp-btn jp-btn--sm jp-btn--outline">Review pending proof</a>
                        @endif
                        @if ($canUseMarkPaidOverride && $balanceDue > 0 && ! $isPaymentBlockedByBookingState)
                            <form method="post" action="{{ $paymentStoreUrl }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="method" value="other">
                                <input type="hidden" name="amount" value="{{ max(1, $balanceDue) }}">
                                <input type="hidden" name="payment_reference" value="MARK-AS-PAID">
                                <input type="hidden" name="notes" value="Marked as paid from payments tab (admin override)">
                                <input type="hidden" name="admin_override" value="1">
                                <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Mark as paid (admin override)</button>
                            </form>
                        @endif
                        @if ($communicationSendUrl && $canSendReminder)
                            <a href="#communication" class="jp-btn jp-btn--sm jp-btn--outline" data-tab-jump="communication">Send payment reminder</a>
                        @else
                            <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" disabled title="{{ $sendReminderReason }}">Send payment reminder</button>
                        @endif
                    </div>
                    <div class="small text-secondary mb-1">Manual payments and mark-as-paid are internal finance actions. They do not use an online payment gateway.</div>
                    <div class="small text-secondary mb-3">Payment proof alone does not mean paid. Only verified payments increase paid amount and unlock ticketing.</div>

                    <h4 class="mb-2" id="payment-record-form">Manual payment form (internal)</h4>
                    <div class="small text-secondary mb-2">Record offline/manual payment received by your team. No card gateway is connected.</div>
                    <form method="post" action="{{ $paymentStoreUrl }}" class="mb-3" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-2">
                            <label class="jp-label">Method</label>
                            <select name="method" class="jp-control" required>
                                @foreach (['bank_transfer', 'cash', 'card_manual', 'easypaisa', 'jazzcash', 'other'] as $m)
                                    <option value="{{ $m }}">{{ str_replace('_', ' ', $m) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="jp-label">Amount</label>
                            <input name="amount" type="number" step="0.01" min="1" class="jp-control" required>
                        </div>
                        <div class="mb-2">
                            <label class="jp-label">Reference</label>
                            <input name="payment_reference" type="text" class="jp-control">
                        </div>
                        <div class="mb-2">
                            <label class="jp-label">Notes</label>
                            <textarea name="notes" class="jp-control" rows="2"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="jp-label">Payment proof upload</label>
                            <input name="payment_proof" type="file" class="jp-control" accept=".jpg,.jpeg,.png,.pdf,.webp">
                            <div class="small text-secondary mt-1">Accepted: JPG, PNG, WEBP, PDF (max 5MB).</div>
                        </div>
                        <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $canRecordManualPayment)>Record manual payment</button>
                    </form>
                    @if ($errors->has('payment'))
                        <div class="jp-alert jp-alert--warn py-2 px-3 small">{{ $errors->first('payment') }}</div>
                    @endif
                    <x-bookings.gateway-payment-status-card :booking="$booking" />
                    <h4 class="mb-2" id="payment-records">Payment records</h4>
                    @foreach ($booking->payments->sortByDesc('created_at') as $payment)
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <strong>Rs {{ number_format((float) $payment->amount, 0) }}</strong>
                                <span class="text-capitalize">{{ str_replace('_', ' ', $payment->status->value) }}</span>
                            </div>
                            <div class="small text-secondary">{{ str_replace('_', ' ', $payment->method->value) }}{{ display_sep_dot() }}{{ $payment->payment_reference ?? 'No ref' }}</div>
                            <div class="small text-secondary">Submitted: <x-time.local :value="$payment->submitted_at" context="operator" /></div>
                            <div class="small text-secondary">Verified: <x-time.local :value="$payment->verified_at" context="operator" /></div>
                            <div class="small text-secondary">Rejected: <x-time.local :value="$payment->rejected_at" context="operator" /></div>
                            @if($payment->proof_path)
                                <div class="small mt-1"><strong>Proof file:</strong> <code>{{ $payment->proof_path }}</code></div>
                            @endif
                            @if($payment->documents->isNotEmpty())
                                <div class="small mt-2"><strong>Payment proof uploads</strong></div>
                                @foreach ($payment->documents as $proofDoc)
                                    <div class="small text-secondary">- {{ str_replace('_', ' ', $proofDoc->document_type->value) }} @if($proofDoc->generated_at){{ display_sep_dot() }}{{ $proofDoc->generated_at->format('Y-m-d H:i') }} @endif</div>
                                @endforeach
                            @endif
                            @if ($payment->status->value === 'submitted')
                                @php
                                    $verifyUrl = $p === 'staff' ? route('staff.bookings.payments.verify', $payment) : route('admin.bookings.payments.verify', $payment);
                                    $rejectUrl = $p === 'staff' ? route('staff.bookings.payments.reject', $payment) : route('admin.bookings.payments.reject', $payment);
                                @endphp
                                <div class="small text-secondary mt-2 mb-1"><strong>Verify/reject actions</strong></div>
                                <form method="post" action="{{ $verifyUrl }}" class="mt-2">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-success">Verify payment</button>
                                </form>
                                <form method="post" action="{{ $rejectUrl }}" class="mt-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="reason" value="Rejected during admin review">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Reject payment</button>
                                </form>
                            @endif
                            @if ($payment->status->value === 'verified')
                                <div class="small text-secondary mt-2 mb-1"><strong>Generate payment receipt</strong></div>
                                <form method="post" action="{{ route($docReceiptRoute, $payment) }}" class="mt-1">
                                    @csrf
                                    <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Generate payment receipt</button>
                                </form>
                                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost mt-1" @disabled(! $canSendConfirmation)>Send payment confirmation</button>
                            @endif
                        </div>
                    @endforeach

                    <h4 class="mb-2 mt-3">Payment verification history</h4>
                    @forelse ($verificationHistory->take(10) as $item)
                        <div class="border rounded p-2 mb-2 small">
                            <div class="d-flex justify-content-between">
                                <strong>Payment #{{ $item->id }}</strong>
                                <span class="text-capitalize">{{ str_replace('_', ' ', $item->status->value) }}</span>
                            </div>
                            <div class="text-secondary">Amount: Rs {{ number_format((float) $item->amount, 0) }}</div>
                            <div class="text-secondary">Verified at: {{ $item->verified_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
                            <div class="text-secondary">Rejected at: {{ $item->rejected_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
                        </div>
                    @empty
                        <p class="small text-secondary mb-2">No payment verification history yet.</p>
                    @endforelse

                    <h4 class="mb-2 mt-3">Receipts</h4>
                    @forelse ($paymentReceiptDocs->take(10) as $receipt)
                        <div class="border rounded p-2 mb-2 small">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $receipt->document_number ?? 'Payment receipt' }}</strong>
                                <span class="text-capitalize">{{ str_replace('_', ' ', $receipt->status->value) }}</span>
                            </div>
                            <div class="text-secondary">{{ $receipt->generated_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
                            @if ($receipt->file_path)
                                <a class="jp-btn jp-btn--sm jp-btn--ghost mt-1" href="{{ route($docDownloadRoute, $receipt) }}">Download receipt</a>
                            @endif
                        </div>
                    @empty
                        <p class="small text-secondary mb-0">No payment receipts generated yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="jp-card" data-tab-section="refunds">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Cancellation &amp; Refund</h3></div>
                <div class="jp-card__body">
                    @php
                        $verifiedPaidAmount = (float) ($booking->payments->where('status.value', 'verified')->sum('amount') ?? 0);
                        $paidRefundAmount = (float) ($booking->refunds->where('status.value', 'paid')->sum('amount') ?? 0);
                        $refundableAmount = max(0, $verifiedPaidAmount - $paidRefundAmount);
                        $ticketingStatusLabel = str_replace('_', ' ', (string) ($booking->ticketing_status ?? 'not_started'));
                        $latestCancellation = $booking->cancellationRequests->sortByDesc('created_at')->first();
                        $latestPendingCancellation = $booking->cancellationRequests->first(fn ($c) => (string) $c->status->value === 'requested');
                        $latestApprovedCancellation = $booking->cancellationRequests->first(fn ($c) => (string) $c->status->value === 'approved');
                        $latestPendingRefund = $booking->refunds->first(fn ($r) => (string) $r->status->value === 'pending');
                        $latestApprovedRefund = $booking->refunds->first(fn ($r) => (string) $r->status->value === 'approved');
                        $eligibleRefundForNote = $booking->refunds->first(fn ($r) => in_array((string) $r->status->value, ['approved', 'paid'], true));
                        $isBookingCancelled = (string) $booking->status->value === 'cancelled';
                        $isLatestCancellationProcessed = $latestCancellation !== null && (string) $latestCancellation->status->value === 'processed';
                        $cancellationActionsLocked = $isBookingCancelled || $isLatestCancellationProcessed;
                        $isCancellationFinalState = $isBookingCancelled && $isLatestCancellationProcessed;
                        $canRequestCancellation = ! $cancellationActionsLocked;
                        $canApproveCancellation = ! $cancellationActionsLocked && $latestPendingCancellation !== null;
                        $canRejectCancellation = ! $cancellationActionsLocked && $latestPendingCancellation !== null;
                        $isSabreCancellation = $provider === 'sabre';
                        $sabreGdsCancelActionState = (string) ($sabreGdsCancel['action_state'] ?? '');
                        $sabreGdsCancelInProgress = $sabreGdsCancelActionState === 'cancellation_pending';
                        $sabreGdsCancelManualTicketed = $sabreGdsCancelActionState === 'manual_ticketed_required';
                        $canProcessCancellation = $latestApprovedCancellation !== null
                            && ! $cancellationActionsLocked
                            && (! $isSabreCancellation || ($hasSupplierPnr && ! $isTicketedForActions && ! $sabreGdsCancelInProgress && ! $sabreGdsCancelManualTicketed));
                        $canCreateRefund = $refundableAmount > 0;
                        $canApproveRefund = $latestPendingRefund !== null;
                        $canMarkRefundPaid = $latestApprovedRefund !== null;
                        $canGenerateRefundNoteTab = $eligibleRefundForNote !== null;
                        $hasRefundContact = (trim((string) ($booking->contact?->email ?? '')) !== '') || (trim((string) ($booking->contact?->phone ?? '')) !== '');
                        $canSendRefundUpdate = $hasRefundContact && $booking->refunds->isNotEmpty();
                        $docRefundNoteUrlTab = $p === 'staff' ? route('staff.bookings.documents.refund-note', $booking) : route('admin.bookings.documents.refund-note', $booking);
                    @endphp
                    <h4 class="mb-2">Refund status</h4>
                    <p class="mb-1"><strong>Booking status:</strong> <span class="text-capitalize">{{ str_replace('_', ' ', $booking->status->value) }}</span></p>
                    <p class="mb-1"><strong>Cancellation status:</strong> <span class="text-capitalize">{{ str_replace('_', ' ', (string) ($booking->cancellation_status ?? 'none')) }}</span></p>
                    <p class="mb-1"><strong>Refund status:</strong> <span class="text-capitalize">{{ str_replace('_', ' ', (string) ($booking->refund_status ?? 'none')) }}</span></p>
                    <p class="mb-1"><strong>Paid amount:</strong> Rs {{ number_format($verifiedPaidAmount, 0) }}</p>
                    <p class="mb-1"><strong>Refundable amount:</strong> Rs {{ number_format($refundableAmount, 0) }}</p>
                    <p class="mb-2"><strong>Ticketing status:</strong> <span class="text-capitalize">{{ $ticketingStatusLabel }}</span></p>
                    @if ($isCancellationFinalState)
                        <div class="jp-alert jp-alert--success py-2 px-3 small">
                            Supplier cancellation confirmed. Booking has been cancelled.
                        </div>
                        <div class="border rounded bg-light p-2 mb-3 small">
                            <div><strong>Booking cancelled</strong></div>
                            <div>Supplier cancellation confirmed</div>
                            <div>Payment/refund remains manual unless a refund record exists.</div>
                        </div>
                    @endif
                    @if ($booking->status->value === 'ticketed')
                        <div class="jp-alert jp-alert--warn py-2 px-3 small">
                            Manual supplier warning: ticketed cancellation requires manual supplier/airline review before final refund processing.
                        </div>
                    @endif
                    <div class="alert alert-secondary py-2 px-3 small">
                        Refund records are manual only and do not trigger gateway/bank transfers.
                    </div>

                    <h4 class="mb-2">Actions</h4>
                    <div class="d-grid gap-2 mb-3">
                        <button type="button" class="jp-btn jp-btn--primary w-100" @disabled(! $canProcessCancellation && ! $canCreateRefund)>{{ $canProcessCancellation ? 'Process cancellation' : 'Create refund' }}</button>
                        @if (! $cancellationActionsLocked)
                            <button type="button" class="jp-btn jp-btn--outline w-100" @disabled(! $canRequestCancellation)>Request cancellation</button>
                            <button type="button" class="jp-btn jp-btn--outline w-100" @disabled(! $canApproveCancellation)>Approve cancellation</button>
                        @endif
                        <button type="button" class="jp-btn jp-btn--outline w-100" @disabled(! $canCreateRefund)>Create refund</button>
                        <button type="button" class="jp-btn jp-btn--outline w-100" @disabled(! $canApproveRefund)>Approve refund</button>
                        <button type="button" class="jp-btn jp-btn--outline w-100" @disabled(! $canMarkRefundPaid)>Mark refund paid</button>
                        @if (! $cancellationActionsLocked)
                            <button type="button" class="jp-btn jp-btn--danger w-100" @disabled(! $canRejectCancellation)>Reject cancellation</button>
                        @endif
                        <button type="button" class="jp-btn jp-btn--danger w-100" @disabled(! $canApproveRefund)>Reject refund</button>
                        <form method="post" action="{{ $docRefundNoteUrlTab }}">@csrf<button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $canGenerateRefundNoteTab)>Generate refund note</button></form>
                        <button type="button" class="jp-btn jp-btn--ghost w-100" @disabled(! $canSendRefundUpdate)>Send refund update</button>
                    </div>
                    @if (! $cancellationActionsLocked && ! $canRequestCancellation)<div class="small text-secondary mb-1">Request cancellation: Already cancelled</div>@endif
                    @if (! $cancellationActionsLocked && ! $canApproveCancellation)<div class="small text-secondary mb-1">Approve cancellation: No pending request</div>@endif
                    @if (! $cancellationActionsLocked && ! $canRejectCancellation)<div class="small text-secondary mb-1">Reject cancellation: No pending request</div>@endif
                    @if (! $cancellationActionsLocked && ! $canProcessCancellation)<div class="small text-secondary mb-1">Process cancellation: {{ $latestApprovedCancellation === null ? 'Not approved' : ($sabreGdsCancelInProgress ? 'Cancellation pending — do not submit again' : ($sabreGdsCancelManualTicketed ? 'Manual action required for ticketed booking' : 'Requires PNR, unticketed status, and not already cancelled')) }}</div>@endif
                    @if (! $canCreateRefund)<div class="small text-secondary mb-1">Create refund: No refundable paid amount</div>@endif
                    @if (! $canApproveRefund)<div class="small text-secondary mb-1">Approve refund: No pending refund</div>@endif
                    @if (! $canMarkRefundPaid)<div class="small text-secondary mb-1">Mark refund paid: Refund not approved</div>@endif
                    @if (! $canGenerateRefundNoteTab)<div class="small text-secondary mb-1">Generate refund note: No refund</div>@endif
                    @if (! $canSendRefundUpdate)<div class="small text-secondary mb-3">Send refund update: No refund/contact</div>@endif

                    @if (! $cancellationActionsLocked)
                        <h4 class="mb-2 mt-3">Request cancellation</h4>
                        <form method="post" action="{{ $cancelStoreUrl }}" class="mb-3 border rounded p-2">
                            @csrf
                            <div class="mb-2">
                                <label class="jp-label">Cancellation type</label>
                                <select name="cancellation_type" class="jp-control" required>
                                    @foreach (['booking_cancel', 'ticket_void', 'ticket_refund', 'supplier_cancel'] as $type)
                                        <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <textarea name="reason" class="jp-control" rows="2" placeholder="Reason (optional)"></textarea>
                            </div>
                            <button type="submit" class="jp-btn jp-btn--danger w-100" @disabled(! $canRequestCancellation)>Submit cancellation request</button>
                        </form>
                    @endif

                    <h4 class="mb-2">Cancellation requests</h4>
                    @if ($latestCancellation)
                        <div class="border rounded p-2 mb-3 small">
                            <div><strong>Latest:</strong> {{ $latestCancellation->id }}{{ display_sep_dot() }}<span class="text-capitalize">{{ $latestCancellation->status->value }}</span></div>
                            <div><strong>Source:</strong> {{ $latestCancellation->request_source }}</div>
                            <div><strong>Type:</strong> {{ $latestCancellation->cancellation_type->value }}</div>
                            <div><strong>Reason:</strong> {{ $latestCancellation->reason ?? 'N/A' }}</div>
                            @php
                                $latestSabreCancelOutcome = is_array($latestCancellation->meta ?? null) ? ($latestCancellation->meta['sabre_cancel_outcome'] ?? null) : null;
                                $latestSabreCancelDiagnosticFields = [
                                    'classification' => 'Classification',
                                    'sabre_cancel_execution_attempted' => 'Sabre execution attempted',
                                    'sabre_cancel_execution_blocked_reason' => 'Blocked reason',
                                    'sabre_cancel_precheck_status' => 'Precheck status',
                                    'sabre_cancel_classification' => 'Sabre classification',
                                    'http_status' => 'HTTP status',
                                    'post_cancel_segment_count' => 'Post-cancel segment count',
                                    'ticket_numbers_present' => 'Ticket numbers present',
                                ];
                            @endphp
                            @if ($isCancellationFinalState)
                                <div class="jp-alert jp-alert--success py-2 px-3 small mt-2 mb-2">Supplier cancellation confirmed. Booking has been cancelled.</div>
                            @endif
                            @if (is_array($latestSabreCancelOutcome))
                                <div class="border rounded bg-light p-2 mt-2 mb-2">
                                    @foreach ($latestSabreCancelDiagnosticFields as $field => $label)
                                        @php
                                            $diagnosticValue = $latestSabreCancelOutcome[$field] ?? null;
                                            $diagnosticDisplayValue = is_bool($diagnosticValue)
                                                ? ($diagnosticValue ? 'yes' : 'no')
                                                : (is_scalar($diagnosticValue) && (string) $diagnosticValue !== '' ? (string) $diagnosticValue : 'N/A');
                                        @endphp
                                        <div><strong>{{ $label }}:</strong> {{ $diagnosticDisplayValue }}</div>
                                    @endforeach
                                </div>
                            @endif
                            @if (! $cancellationActionsLocked && in_array($latestCancellation->status->value, ['requested', 'approved']))
                                @php
                                    $approveUrl = $p === 'staff' ? route('staff.bookings.cancellations.approve', $latestCancellation) : route('admin.bookings.cancellations.approve', $latestCancellation);
                                    $rejectUrl = $p === 'staff' ? route('staff.bookings.cancellations.reject', $latestCancellation) : route('admin.bookings.cancellations.reject', $latestCancellation);
                                    $processUrl = $p === 'staff' ? route('staff.bookings.cancellations.process', $latestCancellation) : route('admin.bookings.cancellations.process', $latestCancellation);
                                @endphp
                                <form method="post" action="{{ $approveUrl }}" class="mt-2">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-success" @disabled((string) $latestCancellation->status->value !== 'requested')>Approve cancellation</button>
                                </form>
                                <div class="jp-alert jp-alert--warn py-2 px-3 small mt-2 mb-2">
                                    This will attempt supplier cancellation and only mark the booking cancelled after Sabre confirms air segments are removed.
                                </div>
                                @php
                                    $processButtonLabel = match ($sabreGdsCancelActionState) {
                                        'cancelled' => 'Cancelled',
                                        'cancellation_pending' => 'Cancellation pending',
                                        'manual_ticketed_required' => 'Manual action required for ticketed booking',
                                        'cancel_sabre_pnr' => 'Cancel Sabre PNR',
                                        default => 'Process supplier cancellation',
                                    };
                                @endphp
                                <form method="post" action="{{ $processUrl }}" class="mt-1" onsubmit="return confirm('This will attempt supplier cancellation and only mark the booking cancelled after Sabre confirms air segments are removed. Continue?')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="jp-btn jp-btn--sm jp-btn--primary" @disabled(! $canProcessCancellation || (string) $latestCancellation->status->value !== 'approved')>{{ $processButtonLabel }}</button>
                                </form>
                                <form method="post" action="{{ $rejectUrl }}" class="mt-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="reason" value="Rejected by operations review">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" @disabled((string) $latestCancellation->status->value !== 'requested')>Reject cancellation</button>
                                </form>
                            @endif
                        </div>
                    @endif

                    <div class="small text-secondary mb-1">Cancellation history</div>
                    @forelse ($booking->cancellationRequests->sortByDesc('created_at')->take(5) as $cancel)
                        <div class="border rounded p-2 mb-2 small">
                            <div class="d-flex justify-content-between">
                                <strong>#{{ $cancel->id }}</strong>
                                <span class="text-capitalize">{{ str_replace('_', ' ', $cancel->status->value) }}</span>
                            </div>
                            <div>{{ $cancel->request_source }}{{ display_sep_dot() }}{{ $cancel->cancellation_type->value }}</div>
                            <div class="text-secondary">{{ $cancel->created_at?->format('Y-m-d H:i') }}</div>
                        </div>
                    @empty
                        <div class="text-secondary small mb-3">No cancellation requests yet.</div>
                    @endforelse

                    <h4 class="mb-2">Manual refund form</h4>
                    <form method="post" action="{{ $refundStoreUrl }}" class="mb-3 border rounded p-2">
                        @csrf
                        <div class="mb-2"><label class="jp-label">Create refund record</label></div>
                        <div class="mb-2">
                            <input class="jp-control" type="number" name="amount" step="0.01" min="1" placeholder="Amount" required>
                        </div>
                        <div class="mb-2">
                            <select name="method" class="jp-control" required>
                                @foreach (['bank_transfer', 'cash', 'card_manual', 'easypaisa', 'jazzcash', 'other'] as $method)
                                    <option value="{{ $method }}">{{ str_replace('_', ' ', $method) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2"><input class="jp-control" type="text" name="reference" placeholder="Reference"></div>
                        <div class="mb-2"><textarea class="jp-control" name="notes" rows="2" placeholder="Notes"></textarea></div>
                        <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $canCreateRefund)>Create refund</button>
                    </form>

                    <h4 class="mb-2">Refund records</h4>
                    @forelse ($booking->refunds->sortByDesc('created_at')->take(8) as $refund)
                        @php
                            $refundApproveUrl = $p === 'staff' ? route('staff.bookings.refunds.approve', $refund) : route('admin.bookings.refunds.approve', $refund);
                            $refundPaidUrl = $p === 'staff' ? route('staff.bookings.refunds.mark-paid', $refund) : route('admin.bookings.refunds.mark-paid', $refund);
                            $refundRejectUrl = $p === 'staff' ? route('staff.bookings.refunds.reject', $refund) : route('admin.bookings.refunds.reject', $refund);
                        @endphp
                        <div class="border rounded p-2 mb-2 small">
                            <div class="d-flex justify-content-between">
                                <strong>Rs {{ number_format((float) $refund->amount, 0) }}</strong>
                                <span class="text-capitalize">{{ $refund->status->value }}</span>
                            </div>
                            <div>{{ str_replace('_', ' ', $refund->method) }}{{ display_sep_dot() }}{{ $refund->reference ?? 'No ref' }}</div>
                            <div class="text-secondary">{{ $refund->created_at?->format('Y-m-d H:i') }}</div>
                            @if (in_array($refund->status->value, ['pending', 'approved']))
                                <form method="post" action="{{ $refundApproveUrl }}" class="mt-1">@csrf @method('PATCH')<button type="submit" class="btn btn-sm btn-success" @disabled((string) $refund->status->value !== 'pending')>Approve refund</button></form>
                                <form method="post" action="{{ $refundPaidUrl }}" class="mt-1">@csrf @method('PATCH')<button type="submit" class="jp-btn jp-btn--sm jp-btn--primary" @disabled((string) $refund->status->value !== 'approved')>Mark refund paid</button></form>
                                <form method="post" action="{{ $refundRejectUrl }}" class="mt-1">@csrf @method('PATCH')<input type="hidden" name="reason" value="Rejected by operations"><button type="submit" class="btn btn-sm btn-outline-danger" @disabled((string) $refund->status->value !== 'pending')>Reject refund</button></form>
                            @endif
                            <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost mt-1" @disabled(! $canSendRefundUpdate)>Send refund update</button>
                        </div>
                    @empty
                        <div class="text-secondary small">No refunds yet.</div>
                    @endforelse
                </div>
            </div>
            <div class="jp-card" data-tab-section="documents">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Documents</h3></div>
                <div class="jp-card__body">
                    @php
                        $bookingExists = $booking->exists;
                        $verifiedPayment = $booking->payments->firstWhere('status.value', 'verified');
                        $hasFareSnapshot = $booking->fareBreakdown !== null;
                        $hasPassengerSnapshot = $booking->passengers->isNotEmpty();
                        $hasContactSnapshot = $booking->contact !== null && (
                            trim((string) ($booking->contact->email ?? '')) !== '' ||
                            trim((string) ($booking->contact->phone ?? '')) !== ''
                        );
                        $hasTotalAmount = (float) ($booking->fareBreakdown?->total ?? 0) > 0;
                        $approvedOrPaidRefund = $booking->refunds->first(fn ($r) => in_array((string) $r->status->value, ['approved', 'paid'], true));
                        $processedCancellation = $booking->cancellationRequests->first(fn ($c) => (string) $c->status->value === 'processed');
                        $canGenerateConfirmation = $bookingExists && $hasPassengerSnapshot && $hasContactSnapshot && $hasFareSnapshot;
                        $canGenerateInvoice = $hasFareSnapshot && $hasTotalAmount;
                        $canGenerateReceipt = $verifiedPayment !== null;
                        $canGenerateItinerary = $booking->tickets->isNotEmpty();
                        $canGenerateRefundNote = $approvedOrPaidRefund !== null;
                        $canGenerateCancellationConfirmation = $processedCancellation !== null;
                        $docRefundNoteUrl = $p === 'staff' ? route('staff.bookings.documents.refund-note', $booking) : route('admin.bookings.documents.refund-note', $booking);
                        $docCancellationConfirmationUrl = $p === 'staff' ? route('staff.bookings.documents.cancellation-confirmation', $booking) : route('admin.bookings.documents.cancellation-confirmation', $booking);
                        $contactExists = $hasContactSnapshot;
                        $hasConfirmationDoc = $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'booking_confirmation');
                        $hasInvoiceDoc = $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'invoice');
                        $hasReceiptDoc = $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'payment_receipt');
                        $hasItineraryDoc = $booking->documents->contains(fn ($doc) => (string) $doc->document_type->value === 'ticket_itinerary');
                        $missingDocumentLabel = 'Generate booking confirmation';
                        $missingDocumentRoute = $docConfirmationUrl;
                        $canGenerateMissingDocument = $canGenerateConfirmation;
                        if (! $hasInvoiceDoc) {
                            $missingDocumentLabel = 'Generate invoice';
                            $missingDocumentRoute = $docInvoiceUrl;
                            $canGenerateMissingDocument = $canGenerateInvoice;
                        } elseif (! $hasConfirmationDoc) {
                            $missingDocumentLabel = 'Generate booking confirmation';
                            $missingDocumentRoute = $docConfirmationUrl;
                            $canGenerateMissingDocument = $canGenerateConfirmation;
                        } elseif (! $hasReceiptDoc) {
                            $missingDocumentLabel = 'Generate payment receipt';
                            $missingDocumentRoute = $verifiedPayment ? route($docReceiptRoute, $verifiedPayment) : null;
                            $canGenerateMissingDocument = $canGenerateReceipt;
                        } elseif (! $hasItineraryDoc) {
                            $missingDocumentLabel = 'Generate ticket itinerary';
                            $missingDocumentRoute = $docItineraryUrl;
                            $canGenerateMissingDocument = $canGenerateItinerary;
                        }
                    @endphp

                    <h4 class="mb-2">Actions</h4>
                    <div class="d-grid gap-2 mb-2">
                        @if ($missingDocumentRoute)
                            <form method="post" action="{{ $missingDocumentRoute }}">
                                @csrf
                                <button type="submit" class="jp-btn jp-btn--primary w-100" @disabled(! $canGenerateMissingDocument)>{{ $missingDocumentLabel }}</button>
                            </form>
                        @endif
                        <form method="post" action="{{ $docConfirmationUrl }}">
                            @csrf
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $canGenerateConfirmation)>Generate booking confirmation</button>
                        </form>
                        @if (! $canGenerateConfirmation)
                            <div class="small text-secondary">Cannot generate booking confirmation: missing booking data.</div>
                        @endif

                        <form method="post" action="{{ $docInvoiceUrl }}">
                            @csrf
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $canGenerateInvoice)>Generate invoice</button>
                        </form>
                        @if (! $canGenerateInvoice)
                            <div class="small text-secondary">Cannot generate invoice: missing fare/total.</div>
                        @endif

                        @if ($canGenerateReceipt)
                            <form method="post" action="{{ route($docReceiptRoute, $verifiedPayment) }}">
                                @csrf
                                <button type="submit" class="jp-btn jp-btn--outline w-100">Generate payment receipt</button>
                            </form>
                        @else
                            <button type="button" class="jp-btn jp-btn--ghost w-100" disabled>Generate payment receipt</button>
                            <div class="small text-secondary">Cannot generate payment receipt: payment unverified/unpaid.</div>
                        @endif

                        <form method="post" action="{{ $docItineraryUrl }}">
                            @csrf
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $canGenerateItinerary)>Generate ticket itinerary</button>
                        </form>
                        @if (! $canGenerateItinerary)
                            <div class="small text-secondary">Cannot generate ticket itinerary: no issued tickets.</div>
                        @endif

                        <form method="post" action="{{ $docRefundNoteUrl }}">
                            @csrf
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $canGenerateRefundNote)>Generate refund note</button>
                        </form>
                        @if (! $canGenerateRefundNote)
                            <div class="small text-secondary">Cannot generate refund note: no refund record.</div>
                        @endif

                        <form method="post" action="{{ $docCancellationConfirmationUrl }}">
                            @csrf
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $canGenerateCancellationConfirmation)>Generate cancellation confirmation</button>
                        </form>
                        @if (! $canGenerateCancellationConfirmation)
                            <div class="small text-secondary">Cannot generate cancellation confirmation: cancellation not processed.</div>
                        @endif
                    </div>
                    <div class="small text-secondary mb-1">Invoice lifecycle: generated ? sent to customer/agent ? payment proof submitted/manual payment recorded ? payment verified ? receipt generated.</div>
                    <div class="small text-secondary mb-3">Invoice is a request/record of payable amount, not proof of payment.</div>
                    <div class="small text-secondary mb-3">
                        <strong>Document types:</strong> booking confirmation, invoice, payment receipt, ticket itinerary, refund note, cancellation confirmation.
                    </div>

                    <h4 class="mb-2">Document records</h4>
                    @forelse ($booking->documents->sortByDesc('created_at') as $document)
                        @php
                            $docTypeRaw = (string) $document->document_type->value;
                            $docTypeLabel = DocumentOperationalState::typeLabel($docTypeRaw);
                            $docStatusMap = DocumentOperationalState::statusForDocument(
                                (string) $document->status->value,
                                (string) ($document->file_path ?? '') !== '',
                                in_array((string) ($document->meta['state'] ?? ''), ['voided', 'cancelled'], true)
                            );
                        @endphp
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $docTypeLabel }}</strong>
                                <span class="text-capitalize">{{ $docStatusMap['label'] }}</span>
                            </div>
                            <div class="small text-secondary">{{ $document->document_number ?? 'N/A' }}{{ display_sep_dot() }}{{ $document->generated_at?->format('Y-m-d H:i') ?? display_unknown() }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                @if ($docStatusMap['code'] === 'generated' && $document->file_path)
                                    <a class="jp-btn jp-btn--sm jp-btn--ghost" href="{{ route($docDownloadRoute, $document) }}">Download</a>
                                @else
                                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" disabled>Download</button>
                                @endif

                                @php
                                    $docType = $docTypeRaw;
                                    $regenRoute = match ($docType) {
                                        'booking_confirmation' => $docConfirmationUrl,
                                        'invoice' => $docInvoiceUrl,
                                        'ticket_itinerary' => $docItineraryUrl,
                                        'payment_receipt' => ($document->booking_payment_id && $document->bookingPayment) ? route($docReceiptRoute, $document->bookingPayment) : null,
                                        'refund_note' => $docRefundNoteUrl,
                                        'cancellation_confirmation' => $docCancellationConfirmationUrl,
                                        default => null,
                                    };
                                @endphp
                                @if ($regenRoute)
                                    <form method="post" action="{{ $regenRoute }}">
                                        @csrf
                                        <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Regenerate</button>
                                    </form>
                                @else
                                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" disabled>Regenerate</button>
                                @endif

                                <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost" @disabled(! $contactExists)>Send to customer</button>
                            </div>
                            <div class="small text-secondary mt-1">
                                @if (! $contactExists)
                                    No contact available for document delivery.
                                @else
                                    Send to customer action is available through configured communication workflows.
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary small mb-0">No documents generated yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="jp-card" data-tab-section="communication" id="add-note-panel">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Communication</h3></div>
                <div class="jp-card__body">
                    @php
                        $commLogs = $booking->communicationLogs->sortByDesc('created_at');
                        $failedLogs = $commLogs->filter(fn ($c) => in_array((string) $c->status, ['failed', 'error'], true));
                        $customerLogs = $commLogs->filter(fn ($c) => !empty($c->recipient_email) || !empty($c->recipient_phone));
                        $adminLogs = $commLogs->filter(fn ($c) => empty($c->recipient_email) && empty($c->recipient_phone));
                    @endphp

                    <h4 class="mb-2">Actions</h4>
                    <div class="d-grid gap-2 mb-3">
                        <form method="post" action="{{ $communicationSendUrl }}">
                            @csrf
                            <input type="hidden" name="action" value="booking_update">
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $communicationSendUrl || ! $hasContact)>Send booking update</button>
                        </form>
                        @if (! $hasContact)
                            <div class="small text-secondary mt-n2">Requires booking contact (email or phone).</div>
                        @endif

                        <form method="post" action="{{ $communicationSendUrl }}">
                            @csrf
                            <input type="hidden" name="action" value="payment_reminder">
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $communicationSendUrl || ! $hasContact || ! $hasUnpaidOrPartialBalance)>Send payment reminder</button>
                        </form>
                        @if (! $hasUnpaidOrPartialBalance)
                            <div class="small text-secondary mt-n2">Requires unpaid or partial balance.</div>
                        @endif

                        <form method="post" action="{{ $communicationSendUrl }}">
                            @csrf
                            <input type="hidden" name="action" value="invoice">
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $communicationSendUrl || ! $hasContact || ! $hasInvoiceDocument)>Send invoice</button>
                        </form>
                        @if (! $hasInvoiceDocument)
                            <div class="small text-secondary mt-n2">Requires an existing invoice document.</div>
                        @endif

                        <form method="post" action="{{ $communicationSendUrl }}">
                            @csrf
                            <input type="hidden" name="action" value="receipt">
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $communicationSendUrl || ! $hasContact || ! $hasReceiptDocument)>Send receipt</button>
                        </form>
                        @if (! $hasReceiptDocument)
                            <div class="small text-secondary mt-n2">Requires an existing payment receipt document.</div>
                        @endif

                        <form method="post" action="{{ $communicationSendUrl }}">
                            @csrf
                            <input type="hidden" name="action" value="ticket_itinerary">
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $communicationSendUrl || ! $hasContact || ! $hasItineraryDocument)>Send ticket itinerary</button>
                        </form>
                        @if (! $hasItineraryDocument)
                            <div class="small text-secondary mt-n2">Requires issued itinerary/ticket.</div>
                        @endif

                        <form method="post" action="{{ $communicationSendUrl }}">
                            @csrf
                            <input type="hidden" name="action" value="cancellation_update">
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $communicationSendUrl || ! $hasContact || ! $hasCancellationDocument)>Send cancellation update</button>
                        </form>

                        <form method="post" action="{{ $communicationSendUrl }}">
                            @csrf
                            <input type="hidden" name="action" value="refund_update">
                            <button type="submit" class="jp-btn jp-btn--outline w-100" @disabled(! $communicationSendUrl || ! $hasContact || ! $hasRefundDocument)>Send refund update</button>
                        </form>

                        <button type="button" class="jp-btn jp-btn--ghost w-100" @disabled($failedLogs->isEmpty())>Resend failed notification</button>
                    </div>
                    <div class="small text-secondary mb-3">
                        Security filter is applied to outbound payloads and failed reasons; sensitive values are redacted.
                    </div>

                    <h4 class="mb-2">Communication logs</h4>
                    @forelse ($commLogs->take(12) as $comm)
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <strong>{{ str_replace('_', ' ', $comm->event) }}</strong>
                                <span class="text-capitalize">{{ $comm->status }}</span>
                            </div>
                            <div class="small text-secondary">
                                Event type: <strong>{{ str_replace('_', ' ', $comm->event) }}</strong>
                               {{ display_sep_dot() }}Recipient: {{ $comm->recipient_email ?? $comm->recipient_phone ?? 'N/A' }}
                               {{ display_sep_dot() }}Channel: {{ strtoupper((string) $comm->channel) }}
                            </div>
                            <div class="small text-secondary">Sent: <x-time.local :value="$comm->sent_at" context="operator" /></div>
                            @if ($comm->error_message)
                                <div class="small text-danger mt-1">
                                    Failed reason (safe): {{ BookingManagementController::summarizeFailure($comm->error_message) }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-secondary small mb-0">No communication logs yet.</p>
                    @endforelse

                    <h4 class="mb-2 mt-3">Emails sent</h4>
                    @php $emailLogs = $commLogs->filter(fn ($c) => strtoupper((string) $c->channel) === 'EMAIL'); @endphp
                    @if ($emailLogs->isEmpty())
                        <p class="small text-secondary mb-2">No email logs found.</p>
                    @else
                        @foreach ($emailLogs->take(6) as $comm)
                            <div class="small text-secondary mb-1"><x-time.local :value="$comm->sent_at" context="operator" />{{ display_sep_dot() }}{{ $comm->recipient_email ?? 'N/A' }}{{ display_sep_dot() }}{{ $comm->event }}</div>
                        @endforeach
                    @endif

                    <h4 class="mb-2 mt-3">Customer notifications</h4>
                    @if ($customerLogs->isEmpty())
                        <p class="small text-secondary mb-2">No customer notification logs found.</p>
                    @else
                        @foreach ($customerLogs->take(6) as $comm)
                            <div class="small text-secondary mb-1">{{ strtoupper($comm->channel) }}{{ display_sep_dot() }}{{ $comm->recipient_email ?? $comm->recipient_phone ?? 'N/A' }}{{ display_sep_dot() }}{{ $comm->event }}{{ display_sep_dot() }}<span class="text-capitalize">{{ $comm->status }}</span></div>
                        @endforeach
                    @endif

                    <h4 class="mb-2 mt-3">Admin notifications</h4>
                    @if ($adminLogs->isEmpty())
                        <p class="small text-secondary mb-2">No admin notification logs found.</p>
                    @else
                        @foreach ($adminLogs->take(6) as $comm)
                            <div class="small text-secondary mb-1">{{ strtoupper($comm->channel) }}{{ display_sep_dot() }}{{ $comm->event }}{{ display_sep_dot() }}<span class="text-capitalize">{{ $comm->status }}</span></div>
                        @endforeach
                    @endif

                    <h4 class="mb-2 mt-3">Failed notifications</h4>
                    @if ($failedLogs->isEmpty())
                        <p class="small text-secondary mb-0">No failed notifications.</p>
                    @else
                        @foreach ($failedLogs->take(8) as $comm)
                            <div class="border rounded p-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $comm->event }}</strong>
                                    <span class="text-danger text-capitalize">{{ $comm->status }}</span>
                                </div>
                                <div class="small text-secondary">{{ strtoupper((string) $comm->channel) }}{{ display_sep_dot() }}{{ $comm->recipient_email ?? $comm->recipient_phone ?? 'N/A' }}</div>
                                <div class="small text-danger mt-1">{{ BookingManagementController::summarizeFailure($comm->error_message) ?: 'No error summary available.' }}</div>
                                @if ($communicationSendUrl && filled($comm->recipient_email) && filled($comm->subject) && filled($comm->message))
                                    <form method="post" action="{{ route('admin.bookings.communication.resend', [$booking, $comm]) }}">
                                        @csrf
                                        <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost mt-2">Resend failed notification</button>
                                    </form>
                                @else
                                    <button type="button" class="jp-btn jp-btn--sm jp-btn--ghost mt-2" disabled>Resend failed notification</button>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="jp-card" data-tab-section="communication" id="internal-notes-panel">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Internal notes</h3></div>
                <div class="jp-card__body">
                    @forelse ($booking->bookingNotes->sortByDesc('created_at') as $bn)
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="small text-secondary">{{ $bn->created_at?->format('Y-m-d H:i') }}{{ display_sep_dot() }}{{ $bn->user?->name ?? 'System' }}
                                @if($bn->is_customer_visible)<span class="badge bg-info ms-1">Customer visible</span>@endif
                            </div>
                            <div class="mt-1">{{ $bn->note }}</div>
                        </div>
                    @empty
                        <p class="text-secondary mb-0">No notes yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="jp-card" data-tab-section="communication" id="change-status-panel">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Change status</h3></div>
                <div class="jp-card__body">
                    @if (count($allowedTransitions) > 0)
                        <form method="post" action="{{ $statusUrl }}">
                            @csrf
                            @method('PATCH')
                            <div class="mb-2">
                                <label class="jp-label">New status</label>
                                <select name="status" class="jp-control" required>
                                    @foreach ($allowedTransitions as $st)
                                        <option value="{{ $st->value }}">{{ str_replace('_', ' ', $st->value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="jp-label">Note (optional)</label>
                                <textarea name="note" class="jp-control" rows="2" maxlength="1000"></textarea>
                            </div>
                            <button type="submit" class="jp-btn jp-btn--primary w-100">Update status</button>
                        </form>
                    @else
                        <p class="text-secondary small mb-0">No transitions available (terminal state or insufficient permissions).</p>
                    @endif
                </div>
            </div>

            <div class="jp-card" data-tab-section="communication">
                <div class="jp-card__head"><h3 class="jp-card__title mb-0">Add note</h3></div>
                <div class="jp-card__body">
                    <form method="post" action="{{ $noteUrl }}">
                        @csrf
                        <div class="mb-2">
                            <textarea name="note" class="jp-control" rows="3" required maxlength="10000" placeholder="Internal note--"></textarea>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" value="1" name="is_customer_visible" id="custvis">
                            <label class="form-check-label" for="custvis">Customer visible</label>
                        </div>
                        <button type="submit" class="jp-btn jp-btn--outline w-100">Save note</button>
                    </form>
                </div>
            </div>

            @if ($p === 'admin' && $assignUrl)
                <div class="jp-card" data-tab-section="communication" id="assign-staff-panel">
                    <div class="jp-card__head"><h3 class="jp-card__title mb-0">Assign staff</h3></div>
                    <div class="jp-card__body">
                        <p class="text-secondary small mb-0">Use the <strong>Assign staff</strong> control in the booking header above, or <a href="{{ $bookingShowUrl }}">refresh this page</a> after assignment.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
