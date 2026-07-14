<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\StreamsFinanceStatementCsv;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Policies\FinanceStatementPolicy;
use App\Services\Finance\Statements\AgentStatementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform admin — read-only agent finance statements.
 */
class FinanceStatementController extends Controller
{
    use StreamsFinanceStatementCsv;

    public function __construct(
        protected AgentStatementService $statements,
        protected FinanceStatementPolicy $policy,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->policy->viewIndex($request->user()), 403);

        return view('dashboard.admin.finance.statements.index', [
            'rows' => $this->statements->buildAgencyIndexRows(),
            'pageTitle' => 'Agent Statements',
            'routePrefix' => 'admin.finance.statements',
        ]);
    }

    public function show(Request $request, Agency $agency): View
    {
        abort_unless($this->policy->view($request->user(), $agency), 403);

        try {
            $period = $this->statements->resolvePeriodFromRequest($request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['date_from' => $e->getMessage()]);
        }

        $statement = $this->statements->buildStatement($agency, $period['from'], $period['to']);

        return view('dashboard.admin.finance.statements.show', [
            'agency' => $agency,
            'statement' => $statement,
            'pageTitle' => 'Agent Statement — '.$agency->name,
            'routePrefix' => 'admin.finance.statements',
            'indexRoute' => 'admin.finance.statements.index',
        ]);
    }

    public function export(Request $request, Agency $agency): StreamedResponse
    {
        abort_unless($this->policy->export($request->user(), $agency), 403);

        try {
            $period = $this->statements->resolvePeriodFromRequest($request);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $statement = $this->statements->buildStatement($agency, $period['from'], $period['to']);

        return $this->streamStatementCsv($agency, $statement);
    }
}
