<?php

namespace App\Http\Controllers\Staff;

use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReplySupportTicketRequest;
use App\Http\Requests\Support\UpdateSupportTicketStatusRequest;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportTicketController extends Controller
{
    public function __construct(
        protected SupportTicketService $tickets,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SupportTicket::class);

        $user = $request->user();
        $query = SupportTicket::query()
            ->forAgency($user)
            ->with(['booking', 'createdBy', 'assignedTo']);

        SupportTicket::applyIndexFilters($query, [
            'queue' => $request->query('queue'),
            'assigned' => $request->query('assigned'),
            'assigned_to_me' => $request->query('assigned_to_me'),
            'source' => $request->query('source'),
            'recent' => $request->query('recent'),
            'status' => $request->query('status'),
        ], $user);

        $tickets = $query
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.staff.support.tickets.index', compact('tickets'));
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['booking', 'createdBy', 'assignedTo', 'forwardedToAgent.user', 'messages.author']);

        return view('dashboard.staff.support.tickets.show', [
            'ticket' => $ticket,
            'statuses' => SupportTicketStatus::cases(),
        ]);
    }

    public function reply(ReplySupportTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);

        $visibility = ($request->validated('visibility') ?? 'customer_visible') === 'internal'
            ? SupportTicketMessageVisibility::Internal
            : SupportTicketMessageVisibility::CustomerVisible;

        $this->tickets->reply(
            $ticket,
            $request->user(),
            (string) $request->validated('body'),
            $visibility,
        );

        return back()->with('status', 'Reply sent.');
    }

    public function updateStatus(UpdateSupportTicketStatusRequest $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('updateStatus', $ticket);

        $this->tickets->updateStatus(
            $ticket,
            SupportTicketStatus::from((string) $request->validated('status')),
            $request->user(),
        );

        return back()->with('status', 'Status updated.');
    }
}
