<?php

namespace App\Http\Controllers\Agent;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Support\Booking\AgentBookingContext;
use App\Support\Bookings\BookingDetailTimelinePresenter;
use App\Support\Bookings\BookingItineraryOverviewPresenter;
use App\Support\Bookings\BookingPaymentSummaryPresenter;
use App\Support\Bookings\PaymentOperationalStatus;
use App\Support\Bookings\SupplierOperationalStatus;
use App\Support\Bookings\TicketingOperationalStatus;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AgentBookingController extends Controller
{
    public function __construct(
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Booking::class);

        $agent = $this->resolveCurrentAgent();
        $filter = (string) $request->query('filter', 'all');
        $allowed = ['all', 'pending_payment', 'pnr_created', 'needs_action', 'cancelled'];
        if (! in_array($filter, $allowed, true)) {
            $filter = 'all';
        }

        $query = Booking::query()
            ->where('agent_id', $agent->id)
            ->with(['passengers', 'contact', 'fareBreakdown', 'commissionEntries'])
            ->orderByDesc('created_at');

        $bookings = (clone $query)
            ->when($filter === 'pending_payment', function ($q): void {
                $q->where(function ($inner): void {
                    $inner->whereIn('payment_status', ['unpaid', 'partial'])
                        ->orWhere('status', BookingStatus::PaymentPending);
                });
            })
            ->when($filter === 'pnr_created', function ($q): void {
                $q->where(function ($inner): void {
                    $inner->whereNotNull('pnr')->where('pnr', '!=', '')
                        ->orWhereIn('supplier_booking_status', ['created', 'booked', 'pending_ticketing', 'ticketed']);
                });
            })
            ->when($filter === 'needs_action', function ($q): void {
                $q->where(function ($inner): void {
                    $inner->whereIn('supplier_booking_status', ['manual_review', 'failed'])
                        ->orWhereIn('status', [BookingStatus::FareReview, BookingStatus::Failed]);
                });
            })
            ->when($filter === 'cancelled', function ($q): void {
                $q->where(function ($inner): void {
                    $inner->where('status', BookingStatus::Cancelled)
                        ->orWhereNotNull('cancellation_status')
                        ->where('cancellation_status', '!=', 'none');
                });
            })
            ->paginate(20)
            ->withQueryString();

        $viewData = [
            'bookings' => $bookings,
            'filter' => $filter,
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.bookings.index', $viewData);
        }

        return view(client_view('bookings.index', 'agent'), $viewData);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Booking::class);
        $this->resolveCurrentAgent();

        $user = $request->user();
        AgentBookingContext::activate($request, $user);

        $agencyName = $user->agentDisplayAgencyName();

        session()->flash(
            'agent_booking_mode_notice',
            'Agency booking mode active — bookings will be linked to '.$agencyName.'.'
        );

        $viewData = [
            'agencyName' => $agencyName,
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.bookings.create', $viewData);
        }

        return view(client_view('bookings.create', 'agent'), $viewData);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Booking::class);

        Log::notice('agent_booking_store_deprecated', [
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('agent.bookings.create')
            ->with('status', 'Please use Search flights to start a new agency booking via the main booking flow.');
    }

    public function exitBookingMode(Request $request): RedirectResponse
    {
        Gate::authorize('create', Booking::class);
        $this->resolveCurrentAgent();

        AgentBookingContext::clear($request);

        return redirect()
            ->route('agent.dashboard')
            ->with('status', 'Agency booking mode ended.');
    }

    public function show(Request $request, Booking $booking): View
    {
        Gate::authorize('view', $booking);
        $this->resolveCurrentAgent();

        $booking->load([
            'passengers',
            'contact',
            'fareBreakdown',
            'statusLogs.user',
            'payments',
            'tickets',
            'documents',
            'supplierBookings',
            'cancellationRequests.requester',
            'refunds',
            'commissionEntries',
            'communicationLogs',
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $hasPnr = filled($booking->pnr)
            || $booking->supplierBookings->contains(fn ($sb) => filled($sb->pnr));
        $provider = (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? ''));

        $viewData = [
            'booking' => $booking,
            'itineraryOverview' => BookingItineraryOverviewPresenter::fromBookingMeta($meta, $hasPnr),
            'paymentOperational' => PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid')),
            'supplierOperational' => SupplierOperationalStatus::fromValues(
                (string) ($booking->supplier_booking_status ?? 'not_started'),
                $provider,
                $hasPnr,
                $meta,
            ),
            'ticketingOperational' => TicketingOperationalStatus::fromValues(
                (string) ($booking->ticketing_status ?? 'not_started'),
                (string) ($booking->payment_status ?? 'unpaid'),
                $hasPnr,
                $booking->tickets->isNotEmpty(),
                $provider,
                (string) ($booking->cancellation_status ?? ''),
            ),
            'commissionEntry' => $booking->commissionEntries->sortByDesc('created_at')->first(),
            'customerTimeline' => BookingDetailTimelinePresenter::forBooking($booking, $meta, $hasPnr),
            'paymentSummary' => BookingPaymentSummaryPresenter::forBooking(
                $booking,
                Gate::forUser(auth()->user())->allows('submitPaymentProof', $booking),
                'agent',
            ),
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.agent.bookings.show', $viewData);
        }

        return view(client_view('bookings.show', 'agent'), $viewData);
    }

    protected function resolveCurrentAgent()
    {
        $agent = auth()->user()?->agent();
        if ($agent === null) {
            abort(403, 'Agent profile is not configured for this agency.');
        }

        return $agent;
    }
}
