<?php

namespace App\Http\Controllers\Customer;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\SupportTicket;
use App\Services\Payments\BookingPaymentService;
use App\Support\Bookings\BookingDetailTimelinePresenter;
use App\Support\Bookings\BookingItineraryOverviewPresenter;
use App\Support\Bookings\BookingPaymentSummaryPresenter;
use App\Support\Bookings\PaymentOperationalStatus;
use App\Support\Bookings\SupplierOperationalStatus;
use App\Support\Bookings\TicketingOperationalStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerBookingController extends Controller
{
    public function __construct(
        protected BookingPaymentService $paymentService,
    ) {}

    public function dashboard(Request $request): View
    {
        $customerId = (int) $request->user()->id;
        $bookings = Booking::query()->where('customer_id', $customerId);

        $recentBookings = (clone $bookings)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $pendingPaymentQuery = (clone $bookings)->where(function ($q): void {
            $q->whereIn('payment_status', ['unpaid', 'partial'])
                ->orWhere('status', BookingStatus::PaymentPending);
        });

        $today = now()->toDateString();
        $upcomingQuery = (clone $bookings)
            ->whereNotNull('travel_date')
            ->whereDate('travel_date', '>=', $today)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->orderBy('travel_date');

        $viewData = [
            'kpis' => [
                'total' => (clone $bookings)->count(),
                'pending_payment' => $pendingPaymentQuery->count(),
                'pnr_confirmed' => (clone $bookings)->where(function ($q): void {
                    $q->whereNotNull('pnr')->where('pnr', '!=', '')
                        ->orWhereIn('supplier_booking_status', ['created', 'booked', 'pending_ticketing', 'ticketed']);
                })->count(),
                'cancellation_activity' => (clone $bookings)->where(function ($q): void {
                    $q->where(function ($inner): void {
                        $inner->whereNotNull('cancellation_status')
                            ->where('cancellation_status', '!=', 'none');
                    })->orWhereHas('cancellationRequests');
                })->count(),
            ],
            'recentBookings' => $recentBookings,
            'hasPendingPaymentBooking' => $pendingPaymentQuery->exists(),
            'firstPendingPaymentBooking' => $pendingPaymentQuery->orderByDesc('created_at')->first(),
            'upcomingBooking' => (clone $upcomingQuery)->first(),
            'upcomingCount' => (clone $upcomingQuery)->count(),
            'supportTicketsCount' => SupportTicket::query()
                ->where('created_by_user_id', $customerId)
                ->count(),
        ];

        return view(client_view('dashboard', 'customer'), $viewData);
    }

    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter', 'all');
        $allowed = ['all', 'pending_payment', 'pnr_created', 'needs_action', 'cancelled'];
        if (! in_array($filter, $allowed, true)) {
            $filter = 'all';
        }

        $bookings = Booking::query()
            ->where('customer_id', $request->user()->id)
            ->with(['contact', 'documents'])
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
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $viewData = [
            'bookings' => $bookings,
            'filter' => $filter,
        ];

        return view(client_view('bookings.index', 'customer'), $viewData);
    }

    public function show(Request $request, Booking $booking): View
    {
        Gate::authorize('view', $booking);
        $this->ensureCustomerOwnsBooking($request, $booking);

        $booking->load([
            'passengers',
            'contact',
            'fareBreakdown',
            'payments',
            'tickets',
            'documents',
            'communicationLogs',
            'cancellationRequests.requester',
            'refunds',
            'supplierBookings',
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $hasPnr = filled($booking->pnr)
            || $booking->supplierBookings->contains(fn ($sb) => filled($sb->pnr));
        $provider = (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? ''));

        $viewData = [
            'booking' => $booking,
            'viewerMode' => 'customer',
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
            'customerTimeline' => BookingDetailTimelinePresenter::forBooking($booking, $meta, $hasPnr),
            'canSubmitPaymentProof' => $this->canSubmitPaymentProof($request, $booking),
            'canRequestCancellation' => $this->canRequestCancellation($booking),
            'paymentSummary' => BookingPaymentSummaryPresenter::forBooking(
                $booking,
                $this->canSubmitPaymentProof($request, $booking),
                'customer',
            ),
        ];

        return view(client_view('bookings.show', 'customer'), $viewData);
    }

    public function downloadDocument(Request $request, BookingDocument $bookingDocument): BinaryFileResponse
    {
        Gate::authorize('view', $bookingDocument);
        if ($bookingDocument->booking?->customer_id !== $request->user()->id) {
            abort(403);
        }

        if ($bookingDocument->file_path === null || ! Storage::disk('local')->exists($bookingDocument->file_path)) {
            abort(404);
        }

        return response()->download(Storage::disk('local')->path($bookingDocument->file_path), basename((string) $bookingDocument->file_path));
    }

    public function submitPaymentProof(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('submitPaymentProof', $booking);
        $this->ensureCustomerOwnsBooking($request, $booking);

        $validated = $request->validate([
            'method' => ['required', Rule::enum(BookingPaymentMethod::class)],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->paymentService->submitPaymentProof($booking, $request->user(), $validated);

        return back()->with('status', 'payment-proof-submitted');
    }

    protected function canSubmitPaymentProof(Request $request, Booking $booking): bool
    {
        if (! Gate::forUser($request->user())->allows('submitPaymentProof', $booking)) {
            return false;
        }

        return BookingPaymentSummaryPresenter::canUploadProof($booking, true);
    }

    protected function canRequestCancellation(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Cancelled) {
            return false;
        }

        $openRequest = $booking->cancellationRequests->contains(
            fn ($r) => in_array($r->status->value, [
                BookingCancellationStatus::Requested->value,
                BookingCancellationStatus::Approved->value,
            ], true)
        );

        return ! $openRequest;
    }

    protected function ensureCustomerOwnsBooking(Request $request, Booking $booking): void
    {
        if ($booking->customer_id !== $request->user()->id) {
            abort(403);
        }
    }
}
