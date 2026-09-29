<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerQueryStatus;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\CustomerQuery;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerQueryController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', CustomerQuery::class);

        $queries = CustomerQuery::query()
            ->with(['assignedTo', 'conversation'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->when($request->filled('assigned_to'), function ($q) use ($request) {
                if ($request->string('assigned_to') === 'unassigned') {
                    $q->whereNull('assigned_to_user_id');
                } else {
                    $q->where('assigned_to_user_id', (int) $request->input('assigned_to'));
                }
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('query_reference', 'like', $term)
                        ->orWhere('origin', 'like', $term)
                        ->orWhere('destination', 'like', $term);
                });
            })
            ->orderByDesc('last_activity_at')
            ->paginate(25)
            ->withQueryString();

        $assignees = User::query()->where('account_type', 'staff')->orWhere('account_type', 'admin')->orderBy('name')->get(['id', 'name']);
        $statuses = CustomerQueryStatus::cases();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'queries' => collect($queries->items())->map(fn (CustomerQuery $query) => $this->serializeQuery($query))->values()->all(),
                'meta' => [
                    'current_page' => $queries->currentPage(),
                    'last_page' => $queries->lastPage(),
                    'per_page' => $queries->perPage(),
                    'total' => $queries->total(),
                ],
                'assignees' => $assignees->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                ])->values()->all(),
                'statuses' => collect($statuses)->map(fn (CustomerQueryStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->name,
                ])->values()->all(),
            ]);
        }

        return view('dashboard.admin.customer-queries.index', compact('queries', 'assignees', 'statuses'));
    }

    public function show(Request $request, CustomerQuery $customerQuery): View|JsonResponse
    {
        Gate::authorize('view', $customerQuery);

        $customerQuery->load(['assignedTo', 'conversation.messages', 'user']);
        $assignees = User::query()->whereIn('account_type', ['staff', 'admin'])->orderBy('name')->get(['id', 'name']);
        $statuses = CustomerQueryStatus::cases();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'query' => $this->serializeQuery($customerQuery, detailed: true),
                'assignees' => $assignees->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                ])->values()->all(),
                'statuses' => collect($statuses)->map(fn (CustomerQueryStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->name,
                ])->values()->all(),
            ]);
        }

        return view('dashboard.admin.customer-queries.show', [
            'query' => $customerQuery,
            'assignees' => $assignees,
            'statuses' => $statuses,
        ]);
    }

    public function updateStatus(Request $request, CustomerQuery $customerQuery): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $customerQuery);

        $data = $request->validate([
            'status' => ['required', 'string'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $customerQuery->status = CustomerQueryStatus::from($data['status']);
        $customerQuery->assigned_to_user_id = $data['assigned_to_user_id'] ?? null;
        if (array_key_exists('internal_notes', $data)) {
            $customerQuery->internal_notes = $data['internal_notes'];
        }
        if (in_array($customerQuery->status, [CustomerQueryStatus::Closed, CustomerQueryStatus::Converted, CustomerQueryStatus::Invalid], true)) {
            $customerQuery->closed_at = now();
        }
        $customerQuery->last_activity_at = now();
        $customerQuery->save();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Customer query updated.',
                'query' => $this->serializeQuery($customerQuery->fresh(['assignedTo', 'conversation', 'user']), detailed: true),
            ]);
        }

        return back()->with('status', 'Customer query updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeQuery(CustomerQuery $query, bool $detailed = false): array
    {
        $payload = [
            'id' => $query->id,
            'reference' => $query->query_reference,
            'name' => $query->name,
            'email' => $query->email,
            'phone' => $query->phone,
            'status' => $query->status instanceof CustomerQueryStatus ? $query->status->value : (string) $query->status,
            'source' => $query->source,
            'origin' => $query->origin,
            'destination' => $query->destination,
            'assigned_to' => $query->assignedTo ? [
                'id' => $query->assignedTo->id,
                'name' => $query->assignedTo->name,
            ] : null,
            'last_activity_at' => optional($query->last_activity_at)?->toIso8601String(),
            'created_at' => optional($query->created_at)?->toIso8601String(),
        ];

        if ($detailed) {
            $payload['message'] = $query->message;
            $payload['internal_notes'] = $query->internal_notes;
            $payload['closed_at'] = optional($query->closed_at)?->toIso8601String();
            $payload['conversation_id'] = $query->conversation_id;
            $payload['messages'] = $query->relationLoaded('conversation') && $query->conversation
                ? $query->conversation->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'body' => $message->body ?? $message->message ?? null,
                    'created_at' => optional($message->created_at)?->toIso8601String(),
                ])->values()->all()
                : [];
        }

        return $payload;
    }
}
