<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Concerns\StreamsFinanceStatementCsv;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Policies\FinanceStatementPolicy;
use App\Services\Finance\Statements\AgentStatementService;
use App\Support\AgentPortal\AgentPortalFinanceStatementPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Agent portal — read-only own-agency finance statement.
 */
class FinanceStatementController extends Controller
{
    use RespondsWithAgentPortalJson;
    use StreamsFinanceStatementCsv;

    public function __construct(
        protected AgentStatementService $statements,
        protected FinanceStatementPolicy $policy,
        protected AgentPortalFinanceStatementPresenter $statementPresenter,
    ) {}

    public function show(Request $request): View|JsonResponse
    {
        $agency = $this->resolveAgency($request);
        abort_unless($this->policy->view($request->user(), $agency), 403);

        try {
            $period = $this->statements->resolvePeriodFromRequest($request);
        } catch (InvalidArgumentException $e) {
            if ($this->wantsAgentPortalJson($request)) {
                return $this->agentPortalJson(
                    $this->statementPresenter->presentValidationError($e->getMessage()),
                    422,
                );
            }

            return back()->withErrors(['date_from' => $e->getMessage()]);
        }

        $statement = $this->statements->buildStatement($agency, $period['from'], $period['to']);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson(
                $this->statementPresenter->present(
                    $statement,
                    $request,
                    $this->policy->export($request->user(), $agency),
                ),
            );
        }

        $viewData = [
            'agency' => $agency,
            'statement' => $statement,
            'pageTitle' => 'Agency Statement',
            'routePrefix' => 'agent.finance.statement',
        ];

        return view(client_view('finance.statement.show', 'agent'), $viewData);
    }

    public function export(Request $request): StreamedResponse
    {
        $agency = $this->resolveAgency($request);
        abort_unless($this->policy->export($request->user(), $agency), 403);

        try {
            $period = $this->statements->resolvePeriodFromRequest($request);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $statement = $this->statements->buildStatement($agency, $period['from'], $period['to']);

        return $this->streamStatementCsv($agency, $statement);
    }

    protected function resolveAgency(Request $request): Agency
    {
        $agent = $request->user()?->agent();
        abort_if($agent === null, 403);

        return Agency::query()->findOrFail($agent->agency_id);
    }
}
