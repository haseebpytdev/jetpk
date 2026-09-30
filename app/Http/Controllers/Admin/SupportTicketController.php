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

        // Owner contract: SUPPORT_PAGE_SIZE_DEFAULT=10
        $pageSize = $request->filled('pageSize')
            ? max(1, min(50, (int) $request->integer('pageSize')))
            : 10;

        $tickets = $query
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at')
            ->paginate($pageSize)
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
                    'page' => $tickets->currentPage(),
                    'pageCount' => $tickets->lastPage(),
                    'pageSize' => $tickets->perPage(),
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

        $assigneeQuery = User::query()
            ->where('account_type', AccountType::Staff)
            ->orderBy('name');
        if ($request->user()?->isPlatformAdmin()) {
            // Platform admins may assign any staff user; agency staff remain agency-scoped.
        } else {
            $assigneeQuery->where('current_agency_id', $ticket->agency_id);
        }
        $assignees = $assigneeQuery->get(['id', 'name', 'email']);

        $agents = Agent::query()
            ->when(
                ! $request->user()?->isPlatformAdmin(),
                fn ($query) => $query->where('agency_id', $ticket->agency_id),
                fn ($query) => $query->when(
                    $ticket->agency_id !== null,
                    fn ($q) => $q->where('agency_id', $ticket->agency_id),
                ),
            )
            ->with('user:id,name,email')
            ->orderBy('code')
            ->get(['id', 'code', 'user_id', 'agency_id']);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'ticket' => $this->presentTicket($ticket, true),
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
        $assignee = null;
        if ($assigneeId !== null) {
            $assigneeQuery = User::query()
                ->where('id', $assigneeId)
                ->where('account_type', AccountType::Staff);
            if (! $request->user()?->isPlatformAdmin()) {
                $assigneeQuery->where('current_agency_id', $ticket->agency_id);
            }
            $assignee = $assigneeQuery->firstOrFail();
        }

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
    private function presentTicket(SupportTicket $ticket, bool $includeThread = false): array
    {
        $payload = [
            'id' => (string) $ticket->id,
            'subject' => (string) ($ticket->subject ?: 'Support ticket'),
            'status' => is_object($ticket->status) ? $ticket->status->value : (string) $ticket->status,
            'assigned_to_user_id' => $ticket->assigned_to_user_id !== null ? (string) $ticket->assigned_to_user_id : null,
            'assigned_to' => $ticket->assignedTo?->name,
            'forwarded_to_agent_id' => $ticket->forwarded_to_agent_id !== null ? (string) $ticket->forwarded_to_agent_id : null,
            'forwarded_to' => $ticket->forwardedToAgent?->user?->name ?? $ticket->forwardedToAgent?->code,
            'created_by' => $ticket->createdBy?->name,
            'booking_id' => $ticket->booking_id !== null ? (string) $ticket->booking_id : null,
            'booking_reference' => $ticket->booking?->booking_reference,
            'last_reply_at' => $ticket->last_reply_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];

        if ($includeThread) {
            if (! $ticket->relationLoaded('messages')) {
                $ticket->load(['messages.author']);
            }
            $payload['messages'] = $ticket->messages
                ->map(static function ($message): array {
                    $visibility = $message->visibility;
                    $visibilityValue = is_object($visibility) ? $visibility->value : (string) $visibility;

                    return [
                        'id' => (string) $message->id,
                        'body' => (string) $message->body,
                        'visibility' => $visibilityValue,
                        'author' => $message->author?->name,
                        'author_id' => $message->user_id !== null ? (string) $message->user_id : null,
                        'created_at' => $message->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all();
        }

        return $payload;
    }
}
