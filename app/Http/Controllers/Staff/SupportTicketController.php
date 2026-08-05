<?php

namespace App\Http\Controllers\Staff;

use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReplySupportTicketRequest;
use App\Http\Requests\Support\UpdateSupportTicketStatusRequest;
use App\Models\SupportTicket;
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

    /**
     * @return array<string, mixed>
     */
    private function presentTicket(SupportTicket $ticket): array
    {
        return [
            'id' => (string) $ticket->id,
            'status' => $ticket->status->value,
            'assigned_to_user_id' => $ticket->assigned_to_user_id !== null ? (string) $ticket->assigned_to_user_id : null,
            'forwarded_to_agent_id' => $ticket->forwarded_to_agent_id !== null ? (string) $ticket->forwarded_to_agent_id : null,
            'last_reply_at' => $ticket->last_reply_at?->toIso8601String(),
        ];
    }
}
