<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerQueryStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerQuery;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerQueryController extends Controller
{
    public function index(Request $request): View
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

        return view('dashboard.admin.customer-queries.index', compact('queries', 'assignees', 'statuses'));
    }

    public function show(CustomerQuery $customerQuery): View
    {
        Gate::authorize('view', $customerQuery);

        $customerQuery->load(['assignedTo', 'conversation.messages', 'user']);
        $assignees = User::query()->whereIn('account_type', ['staff', 'admin'])->orderBy('name')->get(['id', 'name']);
        $statuses = CustomerQueryStatus::cases();

        return view('dashboard.admin.customer-queries.show', [
            'query' => $customerQuery,
            'assignees' => $assignees,
            'statuses' => $statuses,
        ]);
    }

    public function updateStatus(Request $request, CustomerQuery $customerQuery): RedirectResponse
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

        return back()->with('status', 'Customer query updated.');
    }
}
