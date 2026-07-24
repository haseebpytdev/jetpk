<?php

namespace App\Http\Controllers\Agent;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketMessageVisibility;
use App\Http\Controllers\Concerns\ResolvesSupportTicketBookings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReplySupportTicketRequest;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Models\Agency;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportTicketController extends Controller
{
    use ResolvesSupportTicketBookings;

    public function __construct(
        protected SupportTicketService $tickets,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SupportTicket::class);

        $tickets = SupportTicket::query()
            ->forAgentPortalUser($request->user())
            ->with(['booking'])
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view(client_view('support.tickets.index', 'agent'), compact('tickets'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SupportTicket::class);

        $viewData = [
            'bookings' => $this->bookableOptionsForUser($request->user()),
            'categories' => SupportTicketCategory::cases(),
        ];

        return view(client_view('support.tickets.create', 'agent'), $viewData);
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse
    {
        Gate::authorize('create', SupportTicket::class);

        $user = $request->user();
        $agency = Agency::query()->findOrFail($user->current_agency_id);
        $booking = $this->resolveOptionalBooking($user, $request->integer('booking_id') ?: null);

        $ticket = $this->tickets->createTicket($user, $agency, $request->validated(), $booking);

        return redirect()
            ->route('agent.support.tickets.show', $ticket)
            ->with('status', 'Support ticket #'.$ticket->id.' created.');
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'booking',
            'messages' => fn ($q) => $q->where('visibility', SupportTicketMessageVisibility::CustomerVisible)->with('author'),
        ]);

        return view(client_view('support.tickets.show', 'agent'), compact('ticket'));
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
}
