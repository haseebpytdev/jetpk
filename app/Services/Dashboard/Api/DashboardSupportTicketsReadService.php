<?php

namespace App\Services\Dashboard\Api;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardSupportTicketsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', SupportTicket::class);

        $query = SupportTicket::query()
            ->forAgency($user)
            ->with(['assignedTo']);

        SupportTicket::applyIndexFilters($query, [
            'queue' => $request->query('queue'),
            'assigned' => $request->query('assigned'),
            'assigned_to_me' => $request->query('assigned_to_me'),
            'source' => $request->query('source'),
            'recent' => $request->query('recent'),
            'status' => $request->query('status'),
        ], $user);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 10)));

        $paginator = (clone $query)
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at')
            ->paginate($pageSize, ['*'], 'page', $page);

        $items = $paginator->getCollection()
            ->map(fn (SupportTicket $ticket): array => $this->present($ticket))
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pageCount' => $paginator->lastPage(),
            ],
            'filters' => array_filter([
                'status' => $request->query('status'),
                'queue' => $request->query('queue'),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        $ticket = $this->resolve($user, $id);
        if ($ticket === null) {
            return null;
        }

        Gate::authorize('view', $ticket);

        return $this->present($ticket);
    }

    protected function resolve(User $user, string $id): ?SupportTicket
    {
        $query = SupportTicket::query()->forAgency($user);

        if (ctype_digit($id)) {
            return (clone $query)->whereKey((int) $id)->first();
        }

        return (clone $query)->where('ticket_reference', $id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(SupportTicket $ticket): array
    {
        $status = $ticket->status;
        $statusValue = is_object($status) && property_exists($status, 'value')
            ? (string) $status->value
            : (string) $status;

        return [
            'id' => (string) $ticket->id,
            'subject' => (string) ($ticket->subject ?? 'Support ticket'),
            'status' => $statusValue,
            'assignedTo' => $ticket->assignedTo?->name,
        ];
    }
}
