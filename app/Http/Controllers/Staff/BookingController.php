<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingStatus;
use App\Http\Controllers\Concerns\HandlesSabrePnrItinerarySync;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingActionStateService;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Booking\BookingService;
use App\Services\Suppliers\TicketingService;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\AdminSabreDiagnosticPanelsPresenter;
use App\Support\Bookings\AdminSabreGdsCancelPanelsPresenter;
use App\Support\Bookings\AdminSabreGdsTicketingPanelsPresenter;
use App\Support\Bookings\BookingListPresenter;
use App\Support\Bookings\BookingSourceFilter;
use App\Support\Branding\PlatformBrandingResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class BookingController extends Controller
{
    use HandlesSabrePnrItinerarySync;

    public function __construct(
        protected BookingService $bookingService,
        protected BookingActionStateService $bookingActionStateService,
        protected BookingProviderRouter $bookingProviderRouter,
        protected TicketingService $ticketingService,
        protected AdminBookingSupplierActions $adminBookingSupplierActions,
        protected AdminSabreDiagnosticPanelsPresenter $adminSabreDiagnosticPanels,
        protected AdminSabreGdsTicketingPanelsPresenter $adminSabreGdsTicketingPanels,
        protected AdminSabreGdsCancelPanelsPresenter $adminSabreGdsCancelPanels,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Booking::class);

        $user = auth()->user();

        $baseQuery = Booking::query()
            ->with(['passengers', 'contact', 'fareBreakdown', 'agent.user', 'assignedStaff'])
            ->where('agency_id', $user->current_agency_id)
            ->orderByDesc('created_at');

        $activeQueue = $request->string('queue', 'all')->toString();
        if ($activeQueue !== 'all') {
            $this->applyQueueFilter($baseQuery, $activeQueue);
        }

        $this->applyListFilters($baseQuery, $request, $user);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $paginator */
        $paginator = (clone $baseQuery)->paginate(25)->withQueryString();
        $mappedRows = $paginator->getCollection()->map(fn (Booking $booking): array => BookingListPresenter::toListRow($booking));
        $paginator->setCollection($mappedRows);

        return view(client_view('bookings.index', 'staff'), [
            'bookings' => $paginator,
            'activeQueue' => $activeQueue,
            'filters' => $request->only(['search', 'status', 'payment_status', 'date_from', 'date_to', 'assigned_to_me', 'source', 'queue']),
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

        $supplierBookingEligible = $this->bookingProviderRouter->isBookingEligible($booking);
        $ticketingEligible = $this->ticketingService->isBookingEligibleForTicketing($booking);

        $staffShowView = client_view_exists('bookings.show', 'staff')
            ? client_view('bookings.show', 'staff')
            : 'dashboard.admin.bookings.show';

        return view($staffShowView, [
            'booking' => $booking,
            'portal' => 'staff',
            'allowedTransitions' => $allowed,
            'actionState' => $this->bookingActionStateService->build($booking, $supplierBookingEligible, $ticketingEligible),
            'assignableStaff' => collect(),
            'auditLogs' => $auditLogs,
            'supplierBookingEligible' => $supplierBookingEligible,
            'ticketingEligible' => $ticketingEligible,
            'supplierActions' => $this->adminBookingSupplierActions->build($booking, $supplierBookingEligible, $ticketingEligible),
            'sabrePnrReadiness' => $this->adminSabreDiagnosticPanels->pnrReadinessPanel($booking),
            'sabreHostClassification' => $this->adminSabreDiagnosticPanels->hostClassificationPanel($booking),
            'sabreHostSellDiagnostics' => $this->adminSabreDiagnosticPanels->hostSellDiagnosticsPanel($booking),
            'sabreContinuityDiagnostic' => $this->adminSabreDiagnosticPanels->continuityDiagnosticPanel($booking),
            'sabreCompactDiagnostic' => $this->adminSabreDiagnosticPanels->compactStatusPanel(
                $booking,
                $this->adminBookingSupplierActions->build($booking, $supplierBookingEligible, $ticketingEligible),
            ),
            'sabreGdsTicketing' => $this->adminSabreGdsTicketingPanels->gdsTicketingPanel($booking),
            'sabreGdsCancel' => $this->adminSabreGdsCancelPanels->gdsCancelPanel($booking),
            'sabreNdcOrder' => $this->adminSabreGdsTicketingPanels->ndcOrderPanel($booking),
        ]);
    }

    public function createSupplierBooking(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('createSupplierBooking', $booking);

        $validated = $request->validate([
            'admin_override' => ['nullable', 'boolean'],
        ]);

        $postBlock = $this->adminBookingSupplierActions->assertSupplierBookingPostAllowed(
            $booking,
            $this->bookingProviderRouter->isBookingEligible($booking),
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

    public function markManualPnr(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('createSupplierBooking', $booking);
        $validated = $request->validate([
            'pnr' => ['required', 'string', 'max:32'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->bookingProviderRouter->markManualPnr(
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

    protected function applyListFilters(Builder $q, Request $request, User $user): void
    {
        if ($request->boolean('assigned_to_me')) {
            $q->where('assigned_staff_id', $user->id);
        }

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
            $q->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->date('date_to'));
        }

        BookingSourceFilter::apply(
            $q,
            BookingSourceFilter::resolve($request->string('source')->toString() ?: null),
        );
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
}
