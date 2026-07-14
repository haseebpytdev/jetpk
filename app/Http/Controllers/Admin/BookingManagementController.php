<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\BookingDocumentType;
use App\Enums\BookingStatus;
use App\Http\Controllers\Concerns\HandlesAirBlueBookingSync;
use App\Http\Controllers\Concerns\HandlesIatiBookingSync;
use App\Http\Controllers\Concerns\HandlesPiaNdcBookingSync;
use App\Http\Controllers\Concerns\HandlesPiaNdcOptionPnrCreate;
use App\Http\Controllers\Concerns\HandlesPiaNdcOptionPnrRelease;
use App\Http\Controllers\Concerns\HandlesPiaNdcStatusRefresh;
use App\Http\Controllers\Concerns\HandlesPiaNdcTicketing;
use App\Http\Controllers\Concerns\HandlesSabrePnrItinerarySync;
use App\Http\Controllers\Controller;
use App\Mail\ManualBookingCommunicationMail;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Booking\BookingActionStateService;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Booking\BookingService;
use App\Services\Communication\AgencyCommunicationSettingsService;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Suppliers\SupplierBookingService;
use App\Services\Suppliers\TicketingService;
use App\Support\Bookings\AdminAirBlueDiagnosticPanelsPresenter;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\AdminIatiDiagnosticPanelsPresenter;
use App\Support\Bookings\AdminPiaNdcOptionPnrPresenter;
use App\Support\Bookings\AdminPiaNdcReleaseOptionPnrPresenter;
use App\Support\Bookings\AdminPiaNdcSelectedFarePresenter;
use App\Support\Bookings\AdminPiaNdcStatusRefreshPresenter;
use App\Support\Bookings\AdminPiaNdcTicketingPresenter;
use App\Support\Bookings\AdminSabreDiagnosticPanelsPresenter;
use App\Support\Bookings\AdminSabreGdsCancelPanelsPresenter;
use App\Support\Bookings\AdminSabreGdsTicketingPanelsPresenter;
use App\Support\Bookings\BookingListPresenter;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Branding\PlatformBrandingResolver;
use App\Support\Emails\ManualBookingCommunicationEmailRenderer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class BookingManagementController extends Controller
{
    use HandlesAirBlueBookingSync;
    use HandlesIatiBookingSync;
    use HandlesPiaNdcBookingSync;
    use HandlesPiaNdcOptionPnrCreate;
    use HandlesPiaNdcOptionPnrRelease;
    use HandlesPiaNdcStatusRefresh;
    use HandlesPiaNdcTicketing;
    use HandlesSabrePnrItinerarySync;

    public function __construct(
        protected BookingService $bookingService,
        protected SupplierBookingService $supplierBookingService,
        protected BookingProviderRouter $bookingProviderRouter,
        protected TicketingService $ticketingService,
        protected BookingActionStateService $bookingActionStateService,
        protected AdminBookingSupplierActions $adminBookingSupplierActions,
        protected AdminSabreDiagnosticPanelsPresenter $adminSabreDiagnosticPanels,
        protected AdminSabreGdsTicketingPanelsPresenter $adminSabreGdsTicketingPanels,
        protected AdminSabreGdsCancelPanelsPresenter $adminSabreGdsCancelPanels,
        protected AdminIatiDiagnosticPanelsPresenter $adminIatiDiagnosticPanels,
        protected AdminAirBlueDiagnosticPanelsPresenter $adminAirBlueDiagnosticPanels,
        protected AdminPiaNdcOptionPnrPresenter $adminPiaNdcOptionPnr,
        protected AdminPiaNdcReleaseOptionPnrPresenter $adminPiaNdcReleaseOptionPnr,
        protected AdminPiaNdcSelectedFarePresenter $adminPiaNdcSelectedFare,
        protected AdminPiaNdcStatusRefreshPresenter $adminPiaNdcStatusRefresh,
        protected AdminPiaNdcTicketingPresenter $adminPiaNdcTicketing,
        protected BookingCommunicationService $bookingCommunicationService,
        protected AgencyCommunicationSettingsService $agencyCommunicationSettingsService,
        protected ManualBookingCommunicationEmailRenderer $manualBookingCommunicationEmailRenderer,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewAny', Booking::class);

        if ($request->string('product')->toString() === 'group') {
            return redirect()->route('admin.group-bookings.index', [
                'q' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
            ]);
        }

        $user = auth()->user();

        $baseQuery = $this->scopedBookingsQuery($user);
        $this->applyListFilters($baseQuery, $request, $user);

        $kpis = [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('status', BookingStatus::Pending)->count(),
            'ticketed' => (clone $baseQuery)->where('status', BookingStatus::Ticketed)->count(),
            'unpaid' => (clone $baseQuery)->whereIn('payment_status', ['unpaid', 'partial'])->count(),
        ];

        /** @var LengthAwarePaginator<int, array<string, mixed>> $paginator */
        $paginator = (clone $baseQuery)->paginate(25)->withQueryString();
        $mappedRows = $paginator->getCollection()->map(fn (Booking $booking): array => BookingListPresenter::toListRow($booking));
        $paginator->setCollection($mappedRows);

        $previewParam = $request->string('preview')->toString();
        $selectedBooking = null;
        $selectedPreviewKey = '';

        if ($previewParam !== '') {
            $match = ctype_digit($previewParam)
                ? (clone $baseQuery)->whereKey((int) $previewParam)->first()
                : (clone $baseQuery)->whereIn('booking_reference', PlatformBrandingResolver::lookupReferenceCandidates($previewParam))->first();

            if ($match === null) {
                abort(403);
            }

            Gate::authorize('view', $match);
            $selectedBooking = BookingListPresenter::toListRow($match);
            $selectedPreviewKey = (string) $selectedBooking['preview_query'];
        } else {
            $first = (clone $baseQuery)->first();
            if ($first !== null) {
                Gate::authorize('view', $first);
                $selectedBooking = BookingListPresenter::toListRow($first);
                $selectedPreviewKey = (string) $selectedBooking['preview_query'];
            }
        }

        $filterStaff = $this->assignableUsersForAgency($user, null);

        return view(client_view('bookings', 'admin'), [
            'bookings' => $paginator,
            'kpis' => $kpis,
            'selectedBooking' => $selectedBooking,
            'previewRef' => $previewParam,
            'selectedPreviewKey' => $selectedPreviewKey,
            'usingDatabase' => true,
            'hasRows' => $mappedRows->isNotEmpty(),
            'filters' => $request->only(['search', 'status', 'payment_status', 'date_from', 'date_to', 'assigned_staff_id', 'product']),
            'filterStaffUsers' => $filterStaff,
            'statusEnumCases' => BookingStatus::cases(),
        ]);
    }

    public function show(Booking $booking): View
    {
        Gate::authorize('view', $booking);

        $booking->load([
            'passengers',
            'contact',
            'fareBreakdown',
            'agent.user',
            'assignedStaff',
            'bookingNotes.user',
            'statusLogs.user',
            'supplierBookingAttempts.attemptedBy',
            'supplierBookings.createdBy',
            'latestSupplierBooking',
            'tickets.passenger',
            'tickets.issuedBy',
            'ticketingAttempts.attemptedBy',
            'latestTicketingAttempt',
            'payments.payer',
            'payments.receiver',
            'payments.documents',
            'communicationLogs',
            'documents.generatedBy',
            'cancellationRequests.requester',
            'cancellationRequests.approver',
            'cancellationRequests.processor',
            'refunds.approver',
            'refunds.payer',
        ]);

        $allowed = $this->bookingService->getAllowedStatusTransitions($booking, auth()->user());

        $auditLogs = AuditLog::query()
            ->where('auditable_type', Booking::class)
            ->where('auditable_id', $booking->id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $assignableStaff = $this->assignableUsersForAgency(auth()->user(), $booking, staffOnly: true);

        $supplierBookingEligible = $this->supplierBookingService->isBookingEligible($booking);
        $ticketingEligible = $this->ticketingService->isBookingEligibleForTicketing($booking);
        $supplierActions = $this->adminBookingSupplierActions->build($booking, $supplierBookingEligible, $ticketingEligible);

        return view(client_view('bookings.show', 'admin'), [
            'booking' => $booking,
            'portal' => 'admin',
            'allowedTransitions' => $allowed,
            'actionState' => $this->bookingActionStateService->build($booking, $supplierBookingEligible, $ticketingEligible),
            'assignableStaff' => $assignableStaff,
            'auditLogs' => $auditLogs,
            'supplierBookingEligible' => $supplierBookingEligible,
            'ticketingEligible' => $ticketingEligible,
            'supplierActions' => $supplierActions,
            'sabrePnrReadiness' => $this->adminSabreDiagnosticPanels->pnrReadinessPanel($booking),
            'sabreHostClassification' => $this->adminSabreDiagnosticPanels->hostClassificationPanel($booking),
            'sabreHostSellDiagnostics' => $this->adminSabreDiagnosticPanels->hostSellDiagnosticsPanel($booking),
            'sabreContinuityDiagnostic' => $this->adminSabreDiagnosticPanels->continuityDiagnosticPanel($booking),
            'sabreCompactDiagnostic' => $this->adminSabreDiagnosticPanels->compactStatusPanel($booking, $supplierActions),
            'sabreGdsTicketing' => $this->adminSabreGdsTicketingPanels->gdsTicketingPanel($booking),
            'sabreGdsCancel' => $this->adminSabreGdsCancelPanels->gdsCancelPanel($booking),
            'sabreNdcOrder' => $this->adminSabreGdsTicketingPanels->ndcOrderPanel($booking),
            'iatiDiagnostic' => $this->adminIatiDiagnosticPanels->panel($booking),
            'airblueDiagnostic' => $this->adminAirBlueDiagnosticPanels->panel($booking),
            'piaNdcOptionPnr' => $this->adminPiaNdcOptionPnr->panel($booking),
            'piaNdcSelectedFare' => $this->adminPiaNdcSelectedFare->panel($booking),
            'piaNdcRelease' => $this->adminPiaNdcReleaseOptionPnr->panel($booking),
            'piaNdcStatusRefresh' => $this->adminPiaNdcStatusRefresh->panel($booking),
            'piaNdcTicketing' => $this->adminPiaNdcTicketing->panel($booking, $ticketingEligible),
        ]);
    }

    public function preview(Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        try {
            $row = BookingListPresenter::toListRow($booking);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Preview unavailable for this booking.',
            ], 422);
        }

        return response()->json([
            'booking' => $row,
            'preview_key' => (string) ($row['preview_query'] ?? $booking->id),
            'preview_ref' => (string) (($row['booking_ref'] ?? '') !== '' ? $row['booking_ref'] : ('Draft #'.$booking->id)),
            'show_url' => route('admin.bookings.show', $booking),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Booking::class);
        $user = auth()->user();
        $activeQueue = $request->string('queue', 'all')->toString();

        $baseQuery = $this->scopedBookingsQuery($user);
        $this->applyQueueFilter($baseQuery, $activeQueue);
        $this->applyListFilters($baseQuery, $request, $user);

        $perPage = max(5, min(50, (int) $request->query('per_page', 25)));
        $paginator = (clone $baseQuery)->paginate($perPage)->withQueryString();
        $rows = $paginator->getCollection()->map(
            fn (Booking $booking): array => BookingListPresenter::toListRow($booking)
        )->values()->all();

        $previewParam = $request->string('preview')->toString();
        $selectedBooking = null;
        $selectedPreviewKey = '';
        if ($previewParam !== '') {
            $match = ctype_digit($previewParam)
                ? (clone $baseQuery)->whereKey((int) $previewParam)->first()
                : (clone $baseQuery)->whereIn('booking_reference', PlatformBrandingResolver::lookupReferenceCandidates($previewParam))->first();
            if ($match === null) {
                abort(403);
            }
            Gate::authorize('view', $match);
            $selectedBooking = BookingListPresenter::toListRow($match);
            $selectedPreviewKey = (string) $selectedBooking['preview_query'];
        } elseif ($rows !== []) {
            $selectedBooking = $rows[0];
            $selectedPreviewKey = (string) ($selectedBooking['preview_query'] ?? '');
        }

        return response()->json([
            'rows' => $rows,
            'selected_booking' => $selectedBooking,
            'selected_preview_key' => $selectedPreviewKey,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Booking::class);
        $user = auth()->user();
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $query = $this->scopedBookingsQuery($user);
        $candidates = PlatformBrandingResolver::lookupReferenceCandidates($q);
        $query->where(function (Builder $inner) use ($q, $candidates): void {
            $inner->where('booking_reference', 'like', '%'.$q.'%');
            if ($candidates !== [] && $candidates !== [$q]) {
                $inner->orWhereIn('booking_reference', $candidates);
            }
            $inner->orWhereHas('passengers', function (Builder $p) use ($q): void {
                $p->where('first_name', 'like', '%'.$q.'%')
                    ->orWhere('last_name', 'like', '%'.$q.'%');
            })
                ->orWhereHas('contact', function (Builder $c) use ($q): void {
                    $c->where('email', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%');
                });
        });

        $rows = $query->limit(8)->get()->map(function (Booking $booking): array {
            $row = BookingListPresenter::toListRow($booking);
            $ref = (string) ($row['booking_ref'] ?? ('Draft #'.($row['id'] ?? '')));

            return [
                'value' => $ref,
                'preview' => (string) ($row['preview_query'] ?? ''),
                'label' => $ref.' - '.($row['customer_name'] ?? 'Guest').' - '.($row['route'] ?? '—'),
            ];
        })->values()->all();

        return response()->json(['suggestions' => $rows]);
    }

    public function createSupplierBooking(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('createSupplierBooking', $booking);

        $validated = $request->validate([
            'admin_override' => ['nullable', 'boolean'],
        ]);

        $postBlock = $this->adminBookingSupplierActions->assertSupplierBookingPostAllowed(
            $booking,
            $this->supplierBookingService->isBookingEligible($booking),
        );
        if ($postBlock !== null) {
            return back()->withErrors(['supplier_booking' => $postBlock]);
        }

        $result = $this->bookingProviderRouter->createSupplierBooking(
            $booking,
            $request->user(),
            (bool) ($validated['admin_override'] ?? false),
            allowControlledStaffPnr: true,
        );
        if (! $result->success) {
            return back()->withErrors([
                'supplier_booking' => $result->error_message ?: ($result->warnings[0] ?? 'Supplier booking could not be created.'),
            ]);
        }

        return back()->with('status', 'supplier-booking-created');
    }

    public function prepareSupplierPnrContext(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('createSupplierBooking', $booking);

        $postBlock = $this->adminBookingSupplierActions->assertPrepareSupplierContextPostAllowed($booking);
        if ($postBlock !== null) {
            return back()->withErrors(['supplier_context' => $postBlock]);
        }

        $result = app(SabrePnrCertificationSupport::class)->prepareSabrePricingContext($booking);

        if (($result['success'] ?? false) === true) {
            return back()->with('status', 'supplier-pnr-context-prepared');
        }

        return back()->withErrors([
            'supplier_context' => (string) ($result['message'] ?? 'Could not prepare Sabre pricing context.'),
        ]);
    }

    public function markManualPnr(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('createSupplierBooking', $booking);
        $validated = $request->validate([
            'pnr' => ['required', 'string', 'max:32'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->supplierBookingService->markManualPnr(
                $booking,
                $request->user(),
                (string) $validated['pnr'],
                $validated['supplier_reference'] ?? null,
                $validated['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['supplier_booking' => $e->getMessage()]);
        }

        return back()->with('status', 'manual-pnr-marked');
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('changeStatus', $booking);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(BookingStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $to = BookingStatus::from($validated['status']);

        try {
            $this->bookingService->changeStatus(
                $booking,
                $to,
                $request->user(),
                $validated['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'booking-status-updated');
    }

    public function storeNote(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('addNote', $booking);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:10000'],
            'is_customer_visible' => ['sometimes', 'boolean'],
        ]);

        $this->bookingService->addInternalNote(
            $booking,
            $request->user(),
            $validated['note'],
            (bool) ($validated['is_customer_visible'] ?? false),
        );

        return back()->with('status', 'note-added');
    }

    public function assignStaff(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('assignStaff', $booking);

        $validated = $request->validate([
            'staff_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $assignee = isset($validated['staff_user_id'])
            ? User::query()->find($validated['staff_user_id'])
            : null;

        try {
            $this->bookingService->assignStaff($booking, $assignee, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['staff_user_id' => $e->getMessage()]);
        }

        return back()->with('status', 'staff-assigned');
    }

    public function sendCommunication(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('addNote', $booking);
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in([
                'booking_update',
                'payment_reminder',
                'invoice',
                'receipt',
                'ticket_itinerary',
                'cancellation_update',
                'refund_update',
            ])],
        ]);

        $booking->loadMissing(['contact', 'documents', 'payments', 'tickets', 'refunds', 'cancellationRequests']);
        $action = (string) $validated['action'];
        if (! $this->isCommunicationActionEnabled($booking, $action)) {
            return back()->withErrors([
                'communication' => 'Action is not available for this booking state.',
            ]);
        }

        if ($action === 'booking_update') {
            $label = (string) str($booking->status->value)->replace('_', ' ')->title();
            $this->bookingCommunicationService->sendBookingStatusChanged($booking, $label);

            return back()->with('status', 'Booking update sent.');
        }

        if ($action === 'ticket_itinerary') {
            $result = $this->bookingCommunicationService->sendItineraryReady(
                $booking,
                document: null,
                actor: $request->user(),
                note: null,
                forceManual: true,
            );

            if (! ($result['sent'] ?? false)) {
                return back()->withErrors([
                    'communication' => $result['message'] ?? 'Could not send ticket itinerary email.',
                ]);
            }

            return back()->with('status', $result['message'] ?? 'Ticket itinerary email sent.');
        }

        $to = trim((string) ($booking->contact?->email ?? ''));
        if ($to === '') {
            return back()->withErrors(['communication' => 'Booking contact email is required for this action.']);
        }

        $event = $this->manualCommunicationEvent($action);
        $settings = $this->agencyCommunicationSettingsService->getOrCreateSettings($booking->agency()->firstOrFail());
        if (! $settings->email_enabled) {
            return back()->withErrors(['communication' => 'Notification setting is disabled for email.']);
        }
        $template = $this->agencyCommunicationSettingsService->renderTemplate(
            $booking->agency()->firstOrFail(),
            $event,
            'email',
            [
                'booking_reference' => (string) ($booking->booking_reference ?? ('#'.$booking->id)),
                'route' => (string) ($booking->route ?? ''),
                'agency_name' => (string) ($booking->agency?->name ?? config('app.name')),
            ]
        );
        if (! $template['used_template']) {
            return back()->withErrors(['communication' => 'Template is required before sending this update.']);
        }
        if (! $template['is_enabled']) {
            return back()->withErrors(['communication' => 'Template is disabled for this update.']);
        }

        [$subject, $message] = $this->buildManualCommunicationMessage($booking, $action);
        $subject = trim((string) ($template['subject'] ?? '')) !== '' ? (string) $template['subject'] : $subject;
        $message = trim((string) ($template['body'] ?? '')) !== '' ? (string) $template['body'] : $message;
        $log = CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'user_id' => $request->user()?->id,
            'channel' => 'email',
            'event' => $event,
            'recipient_name' => null,
            'recipient_email' => $to,
            'subject' => $subject,
            'message' => $message,
            'status' => 'queued',
            'provider' => (string) config('mail.default'),
        ]);

        try {
            $this->sendModernManualCommunication($booking, $to, $event, $subject, $message);
            $log->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $log->forceFill([
                'status' => 'failed',
                'error_message' => self::summarizeFailure($e->getMessage()),
            ])->save();

            return back()->withErrors(['communication' => 'Message failed to send.']);
        }

        return back()->with('status', 'Communication sent.');
    }

    public function resendFailedCommunication(Booking $booking, CommunicationLog $communicationLog): RedirectResponse
    {
        Gate::authorize('addNote', $booking);
        if ((int) $communicationLog->booking_id !== (int) $booking->id) {
            abort(404);
        }
        if (! in_array((string) $communicationLog->status, ['failed', 'error'], true)) {
            return back()->withErrors(['communication' => 'Only failed communications can be resent.']);
        }

        $to = trim((string) ($communicationLog->recipient_email ?? ''));
        $subject = trim((string) ($communicationLog->subject ?? ''));
        $message = trim((string) ($communicationLog->message ?? ''));
        if ($to === '' || $subject === '' || $message === '') {
            return back()->withErrors(['communication' => 'Failed notification does not contain enough resend details.']);
        }

        $retry = CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'user_id' => auth()->id(),
            'channel' => (string) ($communicationLog->channel ?: 'email'),
            'event' => (string) ($communicationLog->event ?: 'manual_resend'),
            'recipient_name' => $communicationLog->recipient_name,
            'recipient_email' => $to,
            'recipient_phone' => $communicationLog->recipient_phone,
            'subject' => $subject,
            'message' => $message,
            'status' => 'queued',
            'provider' => (string) config('mail.default'),
            'meta' => [
                'resend_of_log_id' => $communicationLog->id,
            ],
        ]);

        try {
            $event = (string) ($communicationLog->event ?: 'manual_resend');
            $this->sendModernManualCommunication($booking, $to, $event, $subject, $message);
            $retry->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $retry->forceFill([
                'status' => 'failed',
                'error_message' => self::summarizeFailure($e->getMessage()),
            ])->save();

            return back()->withErrors(['communication' => 'Resend failed.']);
        }

        return back()->with('status', 'Failed communication resent.');
    }

    public function exportAudit(Booking $booking): Response
    {
        Gate::authorize('view', $booking);
        $booking->loadMissing([
            'statusLogs.user',
            'payments.payer',
            'supplierBookings.createdBy',
            'tickets.issuedBy',
            'documents.generatedBy',
            'communicationLogs',
            'bookingNotes.user',
            'cancellationRequests.requester',
            'refunds.approver',
        ]);

        $auditLogs = AuditLog::query()
            ->where('auditable_type', Booking::class)
            ->where('auditable_id', $booking->id)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $lines = collect([
            ['time', 'type', 'actor', 'status', 'summary'],
        ]);

        $lines->push([
            (string) optional($booking->created_at)?->toDateTimeString(),
            'booking_created',
            'system',
            'done',
            'Booking was created.',
        ]);

        foreach ($booking->statusLogs as $log) {
            $lines->push([
                (string) optional($log->created_at)?->toDateTimeString(),
                'status_changed',
                (string) ($log->user?->name ?? 'system'),
                'done',
                trim((string) $log->from_status).' -> '.trim((string) $log->to_status),
            ]);
        }

        foreach ($booking->payments as $payment) {
            $lines->push([
                (string) optional($payment->created_at)?->toDateTimeString(),
                'payment_recorded',
                (string) ($payment->payer?->name ?? 'system'),
                (string) $payment->status->value,
                'Amount: '.(string) $payment->amount,
            ]);
        }

        foreach ($booking->communicationLogs as $log) {
            $lines->push([
                (string) optional($log->created_at)?->toDateTimeString(),
                'notification_sent',
                (string) ($log->user?->name ?? 'system'),
                (string) $log->status,
                (string) ($log->event.' via '.$log->channel),
            ]);
        }

        foreach ($auditLogs as $log) {
            $lines->push([
                (string) optional($log->created_at)?->toDateTimeString(),
                'audit_event',
                (string) ($log->user?->name ?? 'system'),
                'logged',
                (string) $log->action,
            ]);
        }

        $sorted = $lines->slice(1)->sortByDesc(fn ($row) => (string) ($row[0] ?? ''))->values();
        $rows = collect([$lines->first()])->merge($sorted);
        $csv = $rows->map(function (array $row): string {
            return collect($row)->map(function ($value): string {
                $v = str_replace('"', '""', (string) $value);

                return '"'.$v.'"';
            })->implode(',');
        })->implode("\n");

        $file = 'booking-audit-'.$booking->id.'-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
        ]);
    }

    protected function scopedBookingsQuery(User $user): Builder
    {
        $q = Booking::query()
            ->with(['passengers', 'contact', 'fareBreakdown', 'agent.user', 'assignedStaff'])
            ->orderByDesc('created_at');

        if (! $user->isPlatformAdmin()) {
            $q->where('agency_id', $user->current_agency_id);
        }

        return $q;
    }

    protected function applyListFilters(Builder $q, Request $request, User $user): void
    {
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $candidates = PlatformBrandingResolver::lookupReferenceCandidates($search);
            $q->where(function (Builder $inner) use ($search, $candidates): void {
                $inner->where('booking_reference', 'like', '%'.$search.'%');
                if ($candidates !== [] && $candidates !== [$search]) {
                    $inner->orWhereIn('booking_reference', $candidates);
                }
                $inner->orWhereHas('passengers', function (Builder $p) use ($search): void {
                    $p->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%');
                })
                    ->orWhereHas('contact', function (Builder $c) use ($search): void {
                        $c->where('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }

        if ($request->filled('payment_status')) {
            $q->where('payment_status', $request->string('payment_status')->toString());
        }

        if ($request->filled('date_from')) {
            $q->whereDate('bookings.created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $q->whereDate('bookings.created_at', '<=', $request->date('date_to'));
        }

        if ($request->filled('assigned_staff_id')) {
            $q->where('assigned_staff_id', $request->integer('assigned_staff_id'));
        }
    }

    protected function applyQueueFilter(Builder $q, string $queue): void
    {
        match ($queue) {
            'needs_action' => $q->where(function (Builder $inner): void {
                $inner->whereIn('payment_status', ['unpaid', 'partial'])
                    ->orWhereHas('payments', function (Builder $p): void {
                        $p->whereIn('status', ['submitted', 'pending']);
                    })
                    ->orWhereIn('supplier_booking_status', ['failed', 'manual_review'])
                    ->orWhere(function (Builder $pnr): void {
                        $pnr->where('payment_status', 'paid')
                            ->where(function (Builder $missingPnr): void {
                                $missingPnr->whereNull('pnr')
                                    ->orWhere('pnr', '');
                            });
                    })
                    ->orWhereIn('ticketing_status', ['pending', 'not_started', 'failed'])
                    ->orWhereHas('cancellationRequests', function (Builder $c): void {
                        $c->whereIn('status', ['requested', 'approved']);
                    })
                    ->orWhereHas('refunds', function (Builder $r): void {
                        $r->whereIn('status', ['pending', 'approved']);
                    });
            }),
            'payment_review' => $q->whereIn('payment_status', ['unpaid', 'partial']),
            'supplier_pnr' => $q->where(function (Builder $inner): void {
                $inner->where(function (Builder $paidNoPnr): void {
                    $paidNoPnr->where('payment_status', 'paid')
                        ->where(function (Builder $missingPnr): void {
                            $missingPnr->whereNull('pnr')
                                ->orWhere('pnr', '');
                        });
                })->orWhereIn('supplier_booking_status', ['failed', 'manual_review']);
            }),
            'ticketing' => $q->where(function (Builder $inner): void {
                $inner->where('payment_status', 'paid')
                    ->where(function (Builder $pnr): void {
                        $pnr->whereNotNull('pnr')->where('pnr', '<>', '');
                    })
                    ->where(function (Builder $notTicketed): void {
                        $notTicketed->whereNull('ticketed_at')
                            ->orWhereNotIn('ticketing_status', ['ticketed', 'issued']);
                    });
            }),
            'cancellations' => $q->whereHas('cancellationRequests', function (Builder $c): void {
                $c->whereIn('status', ['requested', 'approved']);
            }),
            'refunds' => $q->whereHas('refunds', function (Builder $r): void {
                $r->whereIn('status', ['pending', 'approved'])
                    ->orWhere(function (Builder $unpaid): void {
                        $unpaid->where('status', 'paid')->whereNull('paid_at');
                    });
            }),
            default => null,
        };
    }

    private function isCommunicationActionEnabled(Booking $booking, string $action): bool
    {
        $hasContact = $booking->contact !== null
            && (filled($booking->contact->email) || filled($booking->contact->phone));
        $hasUnpaidBalance = in_array((string) ($booking->payment_status ?? 'unpaid'), ['unpaid', 'partial'], true)
            || (float) ($booking->balance_due ?? 0) > 0;
        $hasInvoice = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::Invoice);
        $hasReceipt = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::PaymentReceipt);
        $hasItinerary = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::TicketItinerary)
            || $booking->tickets->isNotEmpty();
        $hasCancellation = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::CancellationConfirmation)
            || $booking->cancellationRequests->isNotEmpty();
        $hasRefund = $booking->documents->contains(fn ($doc) => $doc->document_type === BookingDocumentType::RefundNote)
            || $booking->refunds->isNotEmpty();

        return match ($action) {
            'booking_update' => $hasContact,
            'payment_reminder' => $hasContact && $hasUnpaidBalance,
            'invoice' => $hasContact && $hasInvoice,
            'receipt' => $hasContact && $hasReceipt,
            'ticket_itinerary' => $hasContact && $hasItinerary,
            'cancellation_update' => $hasContact && $hasCancellation,
            'refund_update' => $hasContact && $hasRefund,
            default => false,
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildManualCommunicationMessage(Booking $booking, string $action): array
    {
        $ref = (string) ($booking->booking_reference ?: ('#'.$booking->id));
        $route = (string) ($booking->route ?? 'your trip');
        $agencyName = (string) ($booking->agency?->name ?? config('app.name'));

        return match ($action) {
            'payment_reminder' => [
                'Payment reminder for booking '.$ref,
                "Dear customer,\n\nThis is a payment reminder for booking {$ref} ({$route}). Please complete the remaining balance to avoid delays.\n\nThank you,\n{$agencyName}",
            ],
            'invoice' => [
                'Invoice update for booking '.$ref,
                "Dear customer,\n\nYour invoice for booking {$ref} is available. Please review the latest invoice details from your booking portal.\n\nThank you,\n{$agencyName}",
            ],
            'receipt' => [
                'Payment receipt for booking '.$ref,
                "Dear customer,\n\nYour payment receipt for booking {$ref} is now available.\n\nThank you,\n{$agencyName}",
            ],
            'ticket_itinerary' => [
                'Ticket itinerary for booking '.$ref,
                "Dear customer,\n\nYour ticket itinerary for booking {$ref} is ready. Please check your booking portal for details.\n\nThank you,\n{$agencyName}",
            ],
            'cancellation_update' => [
                'Cancellation update for booking '.$ref,
                "Dear customer,\n\nThere is an update regarding cancellation for booking {$ref}. Please review your booking portal for the latest status.\n\nThank you,\n{$agencyName}",
            ],
            'refund_update' => [
                'Refund update for booking '.$ref,
                "Dear customer,\n\nThere is an update regarding refund processing for booking {$ref}. Please review your booking portal for the latest status.\n\nThank you,\n{$agencyName}",
            ],
            default => [
                'Booking update for '.$ref,
                "Dear customer,\n\nYour booking {$ref} has been updated. Please review the latest status in your booking portal.\n\nThank you,\n{$agencyName}",
            ],
        };
    }

    private function manualCommunicationEvent(string $action): string
    {
        return match ($action) {
            'payment_reminder' => 'payment_reminder_manual',
            'invoice' => 'invoice_sent_manual',
            'receipt' => 'payment_receipt_sent_manual',
            'ticket_itinerary' => 'ticket_itinerary_sent_manual',
            'cancellation_update' => 'cancellation_update_manual',
            'refund_update' => 'refund_update_manual',
            default => 'booking_update_manual',
        };
    }

    private function sendModernManualCommunication(
        Booking $booking,
        string $to,
        string $event,
        string $subject,
        string $message,
    ): void {
        $agency = $booking->agency()->firstOrFail();
        $wrapped = $this->manualBookingCommunicationEmailRenderer->render(
            $agency,
            $event,
            $subject,
            $message,
        );

        Mail::to($to)->send(new ManualBookingCommunicationMail(
            $wrapped->html,
            $wrapped->subject,
            $wrapped->plainBody,
        ));
    }

    /**
     * @return Collection<int, User>
     */
    protected function assignableUsersForAgency(User $actor, ?Booking $booking, bool $staffOnly = false): Collection
    {
        if ($actor->isPlatformAdmin()) {
            if ($booking !== null) {
                $agencyId = $booking->agency_id;
            } else {
                $query = User::query()->orderBy('name')->limit(500);
                if ($staffOnly) {
                    $query->where('account_type', AccountType::Staff);
                } else {
                    $query->whereIn('account_type', [AccountType::Staff, AccountType::AgencyAdmin]);
                }

                return $query->get();
            }
        } else {
            $agencyId = $actor->current_agency_id;
        }

        if (! isset($agencyId) || $agencyId === null) {
            return collect();
        }

        $query = User::query()
            ->where('current_agency_id', $agencyId)
            ->orderBy('name');

        if ($staffOnly) {
            $query->where('account_type', AccountType::Staff);
        } else {
            $query->where(function (Builder $q): void {
                $q->where('account_type', AccountType::Staff)
                    ->orWhere('account_type', AccountType::AgencyAdmin);
            });
        }

        return $query->get();
    }

    /**
     * @return array{show: bool, title: string, rows: list<array{label: string, value: string, badge: ?string}>}
     */
    public static function buildSabrePnrReadinessPanel(Booking $booking): array
    {
        return app(AdminSabreDiagnosticPanelsPresenter::class)->pnrReadinessPanel($booking);
    }

    /**
     * @return array{show: bool, fields: array<string, string>, signal_badges: list<string>, disclaimer: string}
     */
    public static function buildSabreHostClassificationPanel(Booking $booking): array
    {
        return app(AdminSabreDiagnosticPanelsPresenter::class)->hostClassificationPanel($booking);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildSabreHostSellDiagnosticsPanel(Booking $booking): array
    {
        return app(AdminSabreDiagnosticPanelsPresenter::class)->hostSellDiagnosticsPanel($booking);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildSabreContinuityDiagnosticPanel(Booking $booking): array
    {
        return app(AdminSabreDiagnosticPanelsPresenter::class)->continuityDiagnosticPanel($booking);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildSabreCompactDiagnosticPanel(Booking $booking, ?array $supplierActions = null): array
    {
        return app(AdminSabreDiagnosticPanelsPresenter::class)->compactStatusPanel($booking, $supplierActions);
    }

    public static function formatSabreHostRetryPolicyAdvisory(?string $retryPolicy): string
    {
        return app(AdminSabreDiagnosticPanelsPresenter::class)->formatSabreHostRetryPolicyAdvisory($retryPolicy);
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, string>
     */
    public static function adminSafeSabreDiagnosticFieldsForOutput(array $merged): array
    {
        return app(AdminSabreDiagnosticPanelsPresenter::class)->adminSafeSabreDiagnosticFieldsForOutput($merged);
    }

    public static function summarizeFailure(?string $message): string
    {
        return app(AdminSabreDiagnosticPanelsPresenter::class)->summarizeFailure($message);
    }
}
