<?php

namespace App\Http\Controllers;

use App\Services\Client\ClientRedirectResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request, ClientRedirectResolver $clientRedirectResolver): RedirectResponse
    {
        return redirect()->to($clientRedirectResolver->dashboardPathForUser($request->user()));
    }
}
