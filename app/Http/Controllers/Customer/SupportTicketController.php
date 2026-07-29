<?php

namespace App\Http\Controllers\Customer;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketMessageVisibility;
use App\Http\Controllers\Concerns\RespondsWithCustomerPortalJson;
use App\Http\Controllers\Concerns\ResolvesSupportTicketBookings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReplySupportTicketRequest;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Models\Agency;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use App\Support\CustomerPortal\CustomerPortalSupportPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportTicketController extends Controller
{
    use ResolvesSupportTicketBookings;
    use RespondsWithCustomerPortalJson;

    public function __construct(
        protected SupportTicketService $tickets,
        protected CustomerPortalSupportPresenter $supportPresenter,
    ) {}

    public function supportHub(): RedirectResponse
    {
        return redirect()->route('customer.support.tickets.index');
    }

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', SupportTicket::class);

        $tickets = SupportTicket::query()
            ->where('created_by_user_id', $request->user()->id)
            ->with(['booking'])
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->supportPresenter->presentIndex($tickets));
        }

        return view(client_view('support.tickets.index', 'customer'), compact('tickets'));
    }

    public function create(Request $request): View|JsonResponse
    {
        Gate::authorize('create', SupportTicket::class);

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->supportPresenter->presentCreateForm($request->user()));
        }

        $viewData = [
            'bookings' => $this->bookableOptionsForUser($request->user()),
            'categories' => SupportTicketCategory::cases(),
        ];

        return view(client_view('support.tickets.create', 'customer'), $viewData);
    }

    public function store(StoreSupportTicketRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', SupportTicket::class);

        $user = $request->user();
        $agency = Agency::query()->findOrFail($user->current_agency_id);
        $booking = $this->resolveOptionalBooking($user, $request->integer('booking_id') ?: null);

        $ticket = $this->tickets->createTicket($user, $agency, $request->validated(), $booking);

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson([
                'ok' => true,
                'ticket' => $this->supportPresenter->presentListItem($ticket->fresh(['booking'])),
                'redirect_url' => '/customer/support/'.$ticket->ticket_reference,
            ], 201);
        }

        return redirect()
            ->route('customer.support.tickets.show', $ticket)
            ->with('status', 'Support ticket #'.$ticket->id.' created.');
    }

    public function show(Request $request, SupportTicket $ticket): View|JsonResponse
    {
        Gate::authorize('view', $ticket);

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->supportPresenter->presentDetail($ticket));
        }

        $ticket->load([
            'booking',
            'messages' => fn ($q) => $q->where('visibility', SupportTicketMessageVisibility::CustomerVisible)->with('author'),
        ]);

        return view(client_view('support.tickets.show', 'customer'), compact('ticket'));
    }

    public function reply(ReplySupportTicketRequest $request, SupportTicket $ticket): RedirectResponse|JsonResponse
    {
        Gate::authorize('reply', $ticket);

        $this->tickets->reply(
            $ticket,
            $request->user(),
            (string) $request->validated('body'),
        );

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->supportPresenter->presentDetail($ticket->fresh()));
        }

        return back()->with('status', 'Reply sent.');
    }

    public function close(Request $request, SupportTicket $ticket): RedirectResponse|JsonResponse
    {
        Gate::authorize('close', $ticket);

        $this->tickets->closeByCustomer($ticket, $request->user());

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->supportPresenter->presentDetail($ticket->fresh()));
        }

        return back()->with('status', 'Ticket closed.');
    }
}
