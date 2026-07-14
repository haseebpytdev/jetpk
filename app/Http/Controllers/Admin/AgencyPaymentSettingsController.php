<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAbhiPayGatewayRequest;
use App\Models\Agency;
use App\Services\Payments\PaymentGatewaySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Payment policy overview and AbhiPay gateway configuration.
 */
class AgencyPaymentSettingsController extends Controller
{
    public function __construct(
        protected PaymentGatewaySettingsService $gatewaySettingsService,
    ) {}

    public function index(Request $request): View
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('view', $agency);

        $abhiPay = $this->gatewaySettingsService->findOrNewAbhiPay($agency->id);

        return view('dashboard.admin.settings.payments', [
            'agency' => $agency,
            'abhiPay' => $abhiPay,
            'callbackUrl' => route('payments.abhipay.callback'),
            'successUrl' => route('payments.success'),
            'cancelUrl' => route('payments.cancel'),
            'declineUrl' => route('payments.decline'),
        ]);
    }

    public function updateAbhiPay(UpdateAbhiPayGatewayRequest $request): RedirectResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);

        $this->gatewaySettingsService->saveAbhiPay(
            $agency,
            $request->user(),
            $request->validated(),
        );

        return back()->with('status', 'AbhiPay settings saved.');
    }

    public function testAbhiPay(Request $request): RedirectResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);

        $gateway = $this->gatewaySettingsService->findOrNewAbhiPay($agency->id);
        $result = $this->gatewaySettingsService->testConnection($gateway);

        return back()->with(
            $result['ok'] ? 'status' : 'error',
            $result['message'],
        );
    }

    protected function resolveAgency(Request $request): Agency
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->isPlatformAdmin() && $request->filled('agency_id')) {
            return Agency::query()->findOrFail($request->integer('agency_id'));
        }

        $agencyId = $user->current_agency_id;
        abort_if($agencyId === null, 403, 'No agency context assigned.');

        return Agency::query()->findOrFail($agencyId);
    }
}
