<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Concerns\StreamsFinanceStatementCsv;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Policies\FinanceStatementPolicy;
use App\Services\Finance\Statements\AgentStatementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform admin — read-only agent finance statements.
 */
class FinanceStatementController extends Controller
{
    use RespondsWithBackOfficeJson;
    use StreamsFinanceStatementCsv;

    public function __construct(
        protected AgentStatementService $statements,
        protected FinanceStatementPolicy $policy,
    ) {}

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        abort_unless($this->policy->viewIndex($request->user()), 403);

        $rows = $this->statements->buildAgencyIndexRows();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'rows' => collect($rows)->map(static function (array $row): array {
                    $agency = $row['agency'] ?? null;

                    return [
                        'agency_id' => $agency instanceof Agency ? (string) $agency->id : '',
                        'agency_name' => $agency instanceof Agency ? (string) $agency->name : '',
                        'wallet_balance' => (float) ($row['wallet_balance'] ?? 0),
                        'ledger_liability' => (float) ($row['ledger_liability'] ?? 0),
                        'difference' => (float) ($row['difference'] ?? 0),
                        'last_movement_at' => isset($row['last_movement_at'])
                            ? (string) $row['last_movement_at']
                            : null,
                        'reconciliation_status' => (string) ($row['reconciliation_status'] ?? ''),
                    ];
                })->values()->all(),
            ]);
        }

        return redirect()->to('/admin/dashboard/accounting?tab=statements');
    }

    public function show(Request $request, Agency $agency): View|JsonResponse|RedirectResponse
    {
        abort_unless($this->policy->view($request->user(), $agency), 403);

        try {
            $period = $this->statements->resolvePeriodFromRequest($request);
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 422, 'validation_error');
            }

            return back()->withErrors(['date_from' => $e->getMessage()]);
        }

        $statement = $this->statements->buildStatement($agency, $period['from'], $period['to']);

        if ($this->wantsBackOfficeJson($request)) {
            $canExport = $this->policy->export($request->user(), $agency);
            $query = array_filter([
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
            ], static fn ($value): bool => filled($value));
            $exportUrl = $canExport
                ? route('admin.finance.statements.export', $agency).($query !== [] ? '?'.http_build_query($query) : '')
                : null;

            return $this->backOfficeJson([
                'ok' => true,
                'agency' => [
                    'id' => (string) $agency->id,
                    'name' => (string) $agency->name,
                ],
                'period' => [
                    'from' => $period['from']->toDateString(),
                    'to' => $period['to']->toDateString(),
                ],
                'currency' => (string) ($statement['currency'] ?? 'PKR'),
                'opening_balance' => (float) ($statement['opening_balance'] ?? 0),
                'closing_balance' => (float) ($statement['closing_balance'] ?? 0),
                'total_debits' => (float) ($statement['total_debits'] ?? 0),
                'total_credits' => (float) ($statement['total_credits'] ?? 0),
                'movements' => collect($statement['movements'] ?? [])
                    ->map(static fn (array $movement): array => [
                        'date' => (string) ($movement['date'] ?? ''),
                        'type' => (string) ($movement['type'] ?? ''),
                        'description' => (string) ($movement['description'] ?? ''),
                        'reference' => (string) ($movement['reference'] ?? ''),
                        'debit' => (float) ($movement['debit'] ?? 0),
                        'credit' => (float) ($movement['credit'] ?? 0),
                        'running_balance' => (float) ($movement['running_balance'] ?? 0),
                    ])
                    ->values()
                    ->all(),
                'reconciliation' => [
                    'wallet_balance' => (float) ($statement['reconciliation']['wallet_balance'] ?? 0),
                    'ledger_liability' => (float) ($statement['reconciliation']['ledger_liability'] ?? 0),
                    'difference' => (float) ($statement['reconciliation']['difference'] ?? 0),
                    'status' => (string) ($statement['reconciliation']['status'] ?? ''),
                    'matches' => (bool) ($statement['reconciliation']['matches'] ?? false),
                ],
                'export_url' => $exportUrl,
            ]);
        }

        return redirect()->to('/admin/dashboard/accounting?tab=statements&agency='.$agency->id);
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
