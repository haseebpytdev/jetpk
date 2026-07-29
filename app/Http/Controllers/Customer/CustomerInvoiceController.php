<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\RespondsWithCustomerPortalJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Support\CustomerPortal\CustomerPortalInvoicesPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerInvoiceController extends Controller
{
    use RespondsWithCustomerPortalJson;

    public function __construct(
        protected CustomerPortalInvoicesPresenter $presenter,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));
        $documents = $this->presenter
            ->invoiceQuery($request->user())
            ->paginate($perPage)
            ->withQueryString();

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->presenter->presentIndex($documents));
        }

        abort(404);
    }

    public function show(Request $request, Booking $booking): View|JsonResponse
    {
        Gate::authorize('view', $booking);
        if ($booking->customer_id !== $request->user()->id) {
            abort(403);
        }

        $booking->load(['documents']);

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->presenter->presentDetail($booking));
        }

        abort(404);
    }
}
