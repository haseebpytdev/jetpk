<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SupplierConnection;
use App\Support\FlightSearch\AirBlueQaSearchScope;
use App\Support\FlightSearch\QaFlightSearchAccessGuard;
use App\Support\FlightSearch\QaStaffAirBlueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bootstrap staff/platform-admin AirBlue QA search session, then reuse public Next results UI.
 */
class QaStaffAirBlueSearchController extends Controller
{
    public function __construct(
        private readonly AirBlueQaSearchScope $scope,
        private readonly QaFlightSearchAccessGuard $accessGuard,
    ) {}

    public function show(Request $request): View
    {
        $this->accessGuard->assertCanInitiate($request);

        $connectionId = (int) config('ota.qa_staff_airblue.connection_id', 23);
        $connection = SupplierConnection::query()->find($connectionId);
        $readiness = $this->scope->evaluate($connection, $request->user());

        return view('dashboard.staff.qa-airblue-search', [
            'readiness' => $readiness,
            'connectionId' => $connectionId,
            'qaActive' => QaStaffAirBlueContext::isActive($request),
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $user = $this->accessGuard->assertCanInitiate($request);

        $connectionId = (int) config('ota.qa_staff_airblue.connection_id', 23);
        $connection = SupplierConnection::query()->find($connectionId);
        $readiness = $this->scope->evaluate($connection, $user);
        if (! ($readiness['allowed'] ?? false)) {
            return redirect()
                ->route('staff.qa.airblue-search')
                ->with('error', 'AirBlue QA scope rejected: '.($readiness['reason'] ?? 'unknown'));
        }

        QaStaffAirBlueContext::activate($request, $user);

        $from = strtoupper(trim((string) $request->input('from', 'LHE')));
        $to = strtoupper(trim((string) $request->input('to', 'KHI')));
        $depart = trim((string) $request->input('depart', now()->addWeeks(3)->format('Y-m-d')));

        $query = http_build_query([
            'from' => $from !== '' ? $from : 'LHE',
            'to' => $to !== '' ? $to : 'KHI',
            'depart' => $depart !== '' ? $depart : now()->addWeeks(3)->format('Y-m-d'),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);

        return redirect('/flights/results?'.$query);
    }

    public function deactivate(Request $request): RedirectResponse
    {
        $this->accessGuard->assertCanInitiate($request);
        QaStaffAirBlueContext::clear($request);

        return redirect()
            ->route('staff.qa.airblue-search')
            ->with('status', 'AirBlue QA session cleared.');
    }
}
