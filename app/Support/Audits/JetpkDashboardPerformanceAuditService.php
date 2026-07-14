<?php

namespace App\Support\Audits;

use App\Enums\AccountType;
use App\Http\Controllers\Admin\CustomerManagementController;
use App\Models\User;
use App\Services\Customers\CustomerIndexMetricsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Read-only static + lightweight DB timing checks for JetPK dashboard GET performance.
 */
final class JetpkDashboardPerformanceAuditService
{
    /**
     * @return array{rows: list<array<string, mixed>>, summary: array{pass: int, warn: int, fail: int}}
     */
    public function run(): array
    {
        $rows = [];
        $pass = 0;
        $warn = 0;
        $fail = 0;

        foreach ($this->staticChecks() as $check) {
            $rows[] = $check;
            match ($check['status']) {
                'pass' => $pass++,
                'warn' => $warn++,
                default => $fail++,
            };
        }

        $customerTiming = $this->measureCustomerIndexQueries();
        $rows[] = $customerTiming;
        match ($customerTiming['status']) {
            'pass' => $pass++,
            'warn' => $warn++,
            default => $fail++,
        };

        return [
            'rows' => $rows,
            'summary' => ['pass' => $pass, 'warn' => $warn, 'fail' => $fail],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staticChecks(): array
    {
        $controllerPath = app_path('Http/Controllers/Admin/CustomerManagementController.php');
        $source = File::exists($controllerPath) ? File::get($controllerPath) : '';

        $checks = [
            [
                'route' => '/admin/customers',
                'controller' => CustomerManagementController::class.'@index',
                'check' => 'customer index uses paginate',
                'status' => str_contains($source, '->paginate(20)') ? 'pass' : 'fail',
                'detail' => 'paginate(20)',
            ],
            [
                'route' => '/admin/customers',
                'controller' => CustomerManagementController::class.'@index',
                'check' => 'customer KPI service (single aggregate)',
                'status' => str_contains($source, 'CustomerIndexMetricsService') ? 'pass' : 'fail',
                'detail' => 'CustomerIndexMetricsService::registeredKpis',
            ],
            [
                'route' => '/admin/customers',
                'controller' => CustomerManagementController::class.'@index',
                'check' => 'no guest count on registered segment',
                'status' => ! str_contains($source, 'countGuests($actor, $request)') ? 'pass' : 'fail',
                'detail' => 'guest_total deferred to guests tab',
            ],
            [
                'route' => '/admin/customers',
                'controller' => CustomerManagementController::class.'@index',
                'check' => 'column select on list query',
                'status' => str_contains($source, "->select(['id', 'name', 'email'") ? 'pass' : 'warn',
                'detail' => 'limited user columns',
            ],
            [
                'route' => '/admin/ledger',
                'controller' => 'AdminLedgerController@index',
                'check' => 'master ledger paginated',
                'status' => str_contains(File::get(app_path('Services/Finance/MasterLedgerService.php')), '->paginate(25)') ? 'pass' : 'fail',
                'detail' => 'paginate(25)',
            ],
            [
                'route' => '/admin/accounting/ledger',
                'controller' => 'AccountingLedgerController@index',
                'check' => 'accounting ledger paginated',
                'status' => str_contains(File::get(app_path('Services/Finance/Ledger/LedgerQueryService.php')), '->paginate(') ? 'pass' : 'fail',
                'detail' => 'LedgerQueryService::paginate',
            ],
            [
                'route' => 'n/a',
                'controller' => 'JetpkDashboardPerformanceAuditCommand',
                'check' => 'performance audit command registered',
                'status' => class_exists(\App\Console\Commands\JetpkDashboardPerformanceAuditCommand::class) ? 'pass' : 'fail',
                'detail' => 'jetpk:dashboard-performance-audit',
            ],
            [
                'route' => 'n/a',
                'controller' => 'migration',
                'check' => 'additive customer indexes migration',
                'status' => File::exists(base_path('database/migrations/2026_07_10_150000_add_jetpk_dashboard_performance_indexes.php')) ? 'pass' : 'warn',
                'detail' => 'users + social_accounts indexes',
            ],
        ];

        $route = Route::getRoutes()->getByName('admin.customers.index');
        if ($route === null) {
            $checks[] = [
                'route' => '/admin/customers',
                'controller' => 'route',
                'check' => 'customers route exists',
                'status' => 'fail',
                'detail' => 'admin.customers.index missing',
            ];
        }

        return $checks;
    }

    /**
     * @return array<string, mixed>
     */
    private function measureCustomerIndexQueries(): array
    {
        $started = microtime(true);
        $memoryBefore = memory_get_usage(true);

        try {
            DB::enableQueryLog();
            DB::connection()->getPdo();

            $scoped = User::query()->where('account_type', AccountType::Customer);
            (clone $scoped)
                ->select(['id', 'name', 'email', 'status', 'created_at', 'meta'])
                ->orderByDesc('id')
                ->paginate(20);

            app(CustomerIndexMetricsService::class)->registeredKpis(User::query()->where('account_type', AccountType::Customer));

            $queries = DB::getQueryLog();
            $elapsedMs = round((microtime(true) - $started) * 1000, 1);
            $memoryMb = round((memory_get_usage(true) - $memoryBefore) / 1048576, 2);
            $queryCount = count($queries);

            $status = 'pass';
            if ($queryCount > 8) {
                $status = 'warn';
            }
            if ($elapsedMs > 2000) {
                $status = 'fail';
            }

            return [
                'route' => '/admin/customers (simulated)',
                'controller' => CustomerManagementController::class.'@index',
                'check' => 'local query simulation',
                'status' => $status,
                'detail' => "queries={$queryCount} elapsed_ms={$elapsedMs} memory_mb={$memoryMb}",
                'query_count' => $queryCount,
                'elapsed_ms' => $elapsedMs,
                'memory_mb' => $memoryMb,
                'pagination' => 'yes',
            ];
        } catch (\Throwable $e) {
            return [
                'route' => '/admin/customers (simulated)',
                'controller' => CustomerManagementController::class.'@index',
                'check' => 'local query simulation',
                'status' => 'warn',
                'detail' => 'DB unavailable: '.$e->getMessage(),
                'query_count' => 0,
                'elapsed_ms' => 0,
                'memory_mb' => 0,
                'pagination' => 'unknown',
            ];
        } finally {
            DB::disableQueryLog();
        }
    }
}
