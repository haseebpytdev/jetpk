<?php

namespace App\Http\Controllers\Customer;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketMessageVisibility;
use App\Http\Controllers\Concerns\ResolvesSupportTicketBookings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReplySupportTicketRequest;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Models\Agency;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportTicketController extends Controller
{
    use ResolvesSupportTicketBookings;

    public function __construct(
        protected SupportTicketService $tickets,
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function supportHub(): RedirectResponse
    {
        return redirect()->route('customer.support.tickets.index');
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SupportTicket::class);

        $tickets = SupportTicket::query()
            ->where('created_by_user_id', $request->user()->id)
            ->with(['booking'])
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.customer.support.index', compact('tickets'));
        }

        return view(client_view('support.tickets.index', 'customer'), compact('tickets'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SupportTicket::class);

        $viewData = [
            'bookings' => $this->bookableOptionsForUser($request->user()),
            'categories' => SupportTicketCategory::cases(),
        ];

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.customer.support.create', $viewData);
        }

        return view(client_view('support.tickets.create', 'customer'), $viewData);
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        Gate::authorize('create', SupportTicket::class);

        $user = $request->user();
        $agency = Agency::query()->findOrFail($user->current_agency_id);
        $booking = $this->resolveOptionalBooking($user, $request->integer('booking_id') ?: null);

        $ticket = $this->tickets->createTicket($user, $agency, $request->validated(), $booking);

        return redirect()
            ->route('customer.support.tickets.show', $ticket)
            ->with('status', 'Support ticket #'.$ticket->id.' created.');
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'booking',
            'messages' => fn ($q) => $q->where('visibility', SupportTicketMessageVisibility::CustomerVisible)->with('author'),
        ]);

        if ($this->mobileViewPreference->shouldUseMobileShell($request)) {
            return view('mobile.customer.support.show', compact('ticket'));
        }

        return view(client_view('support.tickets.show', 'customer'), compact('ticket'));
    }

    public function reply(ReplySupportTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);

        $this->tickets->reply(
            $ticket,
            $request->user(),
            (string) $request->validated('body'),
        );

        return back()->with('status', 'Reply sent.');
    }

    public function close(Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('close', $ticket);

        $this->tickets->closeByCustomer($ticket, $request->user());

        return back()->with('status', 'Ticket closed.');
    }
}
