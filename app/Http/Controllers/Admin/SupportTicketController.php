<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\AssignSupportTicketRequest;
use App\Http\Requests\Support\ForwardSupportTicketRequest;
use App\Http\Requests\Support\ReplySupportTicketRequest;
use App\Http\Requests\Support\UpdateSupportTicketStatusRequest;
use App\Models\Agent;
use App\Models\SupportTicket;
use App\Models\User;
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

        return view(client_view('support.tickets.index', 'admin'), compact('tickets'));
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['booking', 'createdBy', 'assignedTo', 'forwardedToAgent.user', 'messages.author']);

        $assignees = User::query()
            ->where('current_agency_id', $ticket->agency_id)
            ->where('account_type', AccountType::Staff)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $agents = Agent::query()
            ->where('agency_id', $ticket->agency_id)
            ->with('user:id,name,email')
            ->orderBy('code')
            ->get(['id', 'code', 'user_id', 'agency_id']);

        return view(client_view('support.tickets.show', 'admin'), [
            'ticket' => $ticket,
            'statuses' => SupportTicketStatus::cases(),
            'assignees' => $assignees,
            'agents' => $agents,
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

    public function assign(AssignSupportTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('assign', $ticket);

        $assigneeId = $request->validated('assigned_to_user_id');
        $assignee = $assigneeId !== null
            ? User::query()->where('id', $assigneeId)->where('current_agency_id', $ticket->agency_id)->firstOrFail()
            : null;

        $this->tickets->assign($ticket, $assignee, $request->user());

        return back()->with('status', 'Assignment updated.');
    }

    public function forward(ForwardSupportTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('forward', $ticket);

        $agentId = $request->validated('forwarded_to_agent_id');
        $agent = $agentId !== null
            ? Agent::query()
                ->where('id', $agentId)
                ->where('agency_id', $ticket->agency_id)
                ->firstOrFail()
            : null;

        $this->tickets->forward($ticket, $agent, $request->user());

        return back()->with(
            'status',
            $agent !== null ? 'Ticket forwarded to agent.' : 'Forward cleared.',
        );
    }
}
