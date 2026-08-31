<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\SavedTraveler;
use App\Support\CustomerPortal\CustomerPortalTravelersPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Checkout-scoped saved traveler list/fill for authenticated Customers.
 */
class CheckoutSavedTravelerController extends Controller
{
    public function index(Request $request, CustomerPortalTravelersPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }
        if (! $user->isCustomer()) {
            return response()->json(['ok' => false, 'message' => 'Forbidden.'], 403);
        }

        Gate::authorize('viewAny', SavedTraveler::class);

        $travelers = SavedTraveler::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50)
            ->get();

        $defaultId = $travelers->firstWhere('is_default', true)?->id
            ?? ($travelers->count() === 1 ? $travelers->first()?->id : null);

        return response()->json($presenter->presentCheckoutIndex($travelers, $defaultId))
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, SavedTraveler $traveler, CustomerPortalTravelersPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }
        if (! $user->isCustomer()) {
            return response()->json(['ok' => false, 'message' => 'Forbidden.'], 403);
        }

        Gate::authorize('view', $traveler);

        return response()->json($presenter->presentCheckoutShow($traveler))
            ->header('Cache-Control', 'no-store, private');
    }
}
