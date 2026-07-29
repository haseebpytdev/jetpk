<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\RespondsWithCustomerPortalJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Support\CustomerPortal\CustomerPortalPaymentsPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerPaymentController extends Controller
{
    use RespondsWithCustomerPortalJson;

    public function __construct(
        protected CustomerPortalPaymentsPresenter $presenter,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filter = (string) $request->query('filter', 'all');
        $allowed = ['all', 'pending', 'paid', 'failed'];
        if (! in_array($filter, $allowed, true)) {
            $filter = 'all';
        }

        /** @var User $user */
        $user = $request->user();
        $rows = $this->presenter->collectPaymentRows($user, $filter);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));
        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        if ($this->wantsCustomerPortalJson($request)) {
            return $this->customerPortalJson($this->presenter->presentIndex($user, $paginator, $filter));
        }

        abort(404);
    }
}
