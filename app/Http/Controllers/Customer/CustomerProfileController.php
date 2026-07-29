<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\RespondsWithCustomerPortalJson;
use App\Http\Controllers\Controller;
use App\Support\CustomerPortal\CustomerPortalProfilePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProfileController extends Controller
{
    use RespondsWithCustomerPortalJson;

    public function __construct(
        protected CustomerPortalProfilePresenter $presenter,
    ) {}

    public function show(Request $request): JsonResponse
    {
        if (! $this->wantsCustomerPortalJson($request)) {
            abort(404);
        }

        return $this->customerPortalJson($this->presenter->present($request->user()));
    }
}
