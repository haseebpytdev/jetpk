<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\AssignSupportTicketRequest;
use App\Http\Requests\Support\ForwardSupportTicketRequest;
use App\Http\Requests\Support\ReplySupportTicketRequest;
use App\Http\Requests\Support\UpdateSupportTicketStatusRequest;
use App\Models\Agent;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportTicketController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected SupportTicketService $tickets,
        protected BackOfficeCapabilitiesPresenter $capabilitiesPresenter,
    ) {}

    public function index(Request $request): View|JsonResponse
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

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'tickets' => $tickets->getCollection()
                    ->map(fn (SupportTicket $ticket): array => $this->presentTicket($ticket))
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ]);
        }

        return view(client_view('support.tickets.index', 'admin'), compact('tickets'));
    }

    public function show(Request $request, SupportTicket $ticket): View|JsonResponse
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

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'ticket' => $this->presentTicket($ticket),
                'assignees' => $assignees->map(fn (User $assignee): array => [
                    'id' => (string) $assignee->id,
                    'name' => $assignee->name,
                    'email' => $assignee->email,
                ])->values()->all(),
                'agents' => $agents->map(fn (Agent $agent): array => [
                    'id' => (string) $agent->id,
                    'code' => $agent->code,
                    'name' => $agent->user?->name,
                ])->values()->all(),
                'statuses' => array_map(
                    static fn (SupportTicketStatus $status): string => $status->value,
                    SupportTicketStatus::cases(),
                ),
                'capabilities' => $this->capabilitiesPresenter->presentSupportCapabilities($request->user(), $ticket),
            ]);
        }

        return view(client_view('support.tickets.show', 'admin'), [
            'ticket' => $ticket,
            'statuses' => SupportTicketStatus::cases(),
            'assignees' => $assignees,
            'agents' => $agents,
        ]);
    }

    public function reply(ReplySupportTicketRequest $request, SupportTicket $ticket): RedirectResponse|JsonResponse
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

        $ticket->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'ticket' => $this->presentTicket($ticket),
                'capabilities' => $this->capabilitiesPresenter->presentSupportCapabilities($request->user(), $ticket),
            ]);
        }

        return back()->with('status', 'Reply sent.');
    }

    public function updateStatus(UpdateSupportTicketStatusRequest $request, SupportTicket $ticket): RedirectResponse|JsonResponse
    {
        Gate::authorize('updateStatus', $ticket);

        $this->tickets->updateStatus(
            $ticket,
            SupportTicketStatus::from((string) $request->validated('status')),
            $request->user(),
        );

        $ticket->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'ticket' => $this->presentTicket($ticket),
                'capabilities' => $this->capabilitiesPresenter->presentSupportCapabilities($request->user(), $ticket),
            ]);
        }

        return back()->with('status', 'Status updated.');
    }

    public function assign(AssignSupportTicketRequest $request, SupportTicket $ticket): RedirectResponse|JsonResponse
    {
        Gate::authorize('assign', $ticket);

        $assigneeId = $request->validated('assigned_to_user_id');
        $assignee = $assigneeId !== null
            ? User::query()->where('id', $assigneeId)->where('current_agency_id', $ticket->agency_id)->firstOrFail()
            : null;

        $this->tickets->assign($ticket, $assignee, $request->user());

        $ticket->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'ticket' => $this->presentTicket($ticket),
                'capabilities' => $this->capabilitiesPresenter->presentSupportCapabilities($request->user(), $ticket),
            ]);
        }

        return back()->with('status', 'Assignment updated.');
    }

    public function forward(ForwardSupportTicketRequest $request, SupportTicket $ticket): RedirectResponse|JsonResponse
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

        $ticket->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'ticket' => $this->presentTicket($ticket),
                'capabilities' => $this->capabilitiesPresenter->presentSupportCapabilities($request->user(), $ticket),
            ]);
        }

        return back()->with(
            'status',
            $agent !== null ? 'Ticket forwarded to agent.' : 'Forward cleared.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTicket(SupportTicket $ticket): array
    {
        return [
            'id' => (string) $ticket->id,
            'subject' => (string) ($ticket->subject ?: 'Support ticket'),
            'status' => is_object($ticket->status) ? $ticket->status->value : (string) $ticket->status,
            'assigned_to_user_id' => $ticket->assigned_to_user_id !== null ? (string) $ticket->assigned_to_user_id : null,
            'assigned_to' => $ticket->assignedTo?->name,
            'forwarded_to_agent_id' => $ticket->forwarded_to_agent_id !== null ? (string) $ticket->forwarded_to_agent_id : null,
            'last_reply_at' => $ticket->last_reply_at?->toIso8601String(),
        ];
    }
}
