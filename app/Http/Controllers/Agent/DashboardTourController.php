<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\DashboardTourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DashboardTourController extends Controller
{
    public function __construct(
        protected DashboardTourService $tours,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || ! $user->isAgentPortalUser(), 403);

        return response()->json($this->tours->presentForAgent($user));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || ! $user->isAgentPortalUser(), 403);

        abort_if($request->filled('user_id') || $request->filled('userId'), 422, 'user_id is not accepted.');

        $validated = $request->validate([
            'tour_key' => ['required', 'string'],
            'status' => ['nullable', 'string', 'in:completed,skipped'],
            'restart' => ['sometimes', 'boolean'],
        ]);

        try {
            $tours = $this->tours->update(
                $user,
                (string) $validated['tour_key'],
                isset($validated['status']) ? (string) $validated['status'] : null,
                (bool) ($validated['restart'] ?? false),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'tours' => $tours,
        ]);
    }
}
