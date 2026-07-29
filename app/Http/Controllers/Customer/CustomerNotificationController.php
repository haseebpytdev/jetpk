<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\RespondsWithCustomerPortalJson;
use App\Http\Controllers\Controller;
use App\Support\CustomerPortal\CustomerPortalNotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerNotificationController extends Controller
{
    use RespondsWithCustomerPortalJson;

    public function __construct(
        protected CustomerPortalNotificationPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->wantsCustomerPortalJson($request)) {
            abort(404);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));

        return $this->customerPortalJson(
            $this->presenter->presentIndex($request->user(), $page, $perPage),
        );
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        if (! $this->wantsCustomerPortalJson($request)) {
            abort(404);
        }

        return $this->customerPortalJson($this->presenter->presentUnreadSummary($request->user()));
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        if (! $this->wantsCustomerPortalJson($request)) {
            abort(404);
        }

        return $this->customerPortalJson($this->presenter->markReadUnavailable(), 501);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        if (! $this->wantsCustomerPortalJson($request)) {
            abort(404);
        }

        return $this->customerPortalJson($this->presenter->markReadUnavailable(), 501);
    }
}
