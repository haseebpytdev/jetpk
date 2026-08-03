<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$inventoryPath = __DIR__.'/../storage/app/jp-ops-05-mutation-inventory.json';
/** @var array<string, mixed> $inventory */
$inventory = json_decode((string) file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);

/** Fully Next-bound: Laravel + dashboard service + production UI + Playwright. */
/** @var list<string> */
$fullyConnectedNames = [
    'admin.bookings.payments.verify',
    'admin.bookings.payments.reject',
    'staff.bookings.payments.verify',
    'staff.bookings.payments.reject',
    'admin.agent-deposits.approve',
    'admin.agent-deposits.reject',
];

/** Backend JSON ready; no production dashboard component or operational UI binding. */
/** @var list<string> */
$backendWithoutNextBindingNames = [
    'admin.bookings.cancellations.approve',
    'admin.bookings.cancellations.reject',
    'staff.bookings.cancellations.approve',
    'staff.bookings.cancellations.reject',
    'admin.bookings.refunds.approve',
    'admin.bookings.refunds.reject',
    'staff.bookings.refunds.approve',
    'staff.bookings.refunds.reject',
];

/** @var list<string> */
$blockedJsonNames = [
    'admin.bookings.cancellations.process',
    'staff.bookings.cancellations.process',
    'admin.bookings.refunds.mark-paid',
    'staff.bookings.refunds.mark-paid',
];

function classifyRow(array $row, array $fullyConnectedNames, array $backendWithoutNextBindingNames, array $blockedJsonNames): array
{
    $name = (string) ($row['name'] ?? '');
    $uri = (string) ($row['uri'] ?? '');
    $method = implode('|', $row['methods'] ?? []);

    if (in_array($name, $fullyConnectedNames, true)) {
        return [
            'disposition' => 'SAFE_TO_CONNECT_IN_JP_OPS_05',
            'next_binding' => 'CONNECTED',
            'deferral' => '—',
            'test_ref' => 'BackOfficeOperationalClosureTest / operational Playwright',
        ];
    }

    if (in_array($name, $backendWithoutNextBindingNames, true)) {
        return [
            'disposition' => 'PARTIALLY_CONNECTED_IN_JP_OPS_05',
            'next_binding' => 'BACKEND_WITHOUT_NEXT_BINDING',
            'deferral' => 'JSON contract only; dashboard review UI deferred',
            'test_ref' => 'BackOfficeOperationalClosureTest (Laravel JSON only)',
        ];
    }

    if (in_array($name, $blockedJsonNames, true)) {
        return [
            'disposition' => 'JP_OPS_06_EXECUTION_DEPENDENCY',
            'next_binding' => 'JSON_BLOCKED',
            'deferral' => 'JP-OPS-06 external_execution_required',
            'test_ref' => 'BackOfficeOperationalClosureTest',
        ];
    }

    if (str_contains($name, 'issue-ticket') || str_contains($uri, 'issue-ticket')) {
        return [
            'disposition' => 'JP_OPS_06_EXECUTION_DEPENDENCY',
            'next_binding' => 'BLADE_FALLBACK_RETAINED',
            'deferral' => 'Live ticketing execution',
            'test_ref' => 'BackOfficeTicketingBoundaryTest',
        ];
    }

    if (str_contains($uri, 'page-settings') || $row['portal'] === 'admin_page_settings') {
        return [
            'disposition' => 'BLADE_FALLBACK_RETAINED',
            'next_binding' => 'BLADE_OPERATIONAL',
            'deferral' => 'CMS/page-settings out of JP-OPS-05 scope',
            'test_ref' => '—',
        ];
    }

    if (str_contains($uri, 'cms') || str_contains($uri, 'settings') || str_contains($uri, 'markups') || str_contains($uri, 'promo')) {
        return [
            'disposition' => 'BLADE_FALLBACK_RETAINED',
            'next_binding' => 'BLADE_OPERATIONAL',
            'deferral' => 'Settings/CMS Blade-only',
            'test_ref' => '—',
        ];
    }

    if ($name === 'admin.' || $name === 'staff.') {
        return [
            'disposition' => 'BLADE_FALLBACK_RETAINED',
            'next_binding' => 'BLADE_OPERATIONAL',
            'deferral' => 'Fallback catch-all route record',
            'test_ref' => '—',
        ];
    }

    return [
        'disposition' => 'BLADE_FALLBACK_RETAINED',
        'next_binding' => 'BLADE_OPERATIONAL',
        'deferral' => 'Not in JP-OPS-05 safe review subset',
        'test_ref' => '—',
    ];
}

$fullyConnected = 0;
$backendOnly = 0;
$deferred = 0;
$lines = [];
$lines[] = '# JP-OPS-05 Blade Next Mutation Matrix';
$lines[] = '';
$lines[] = '**Generated:** '.gmdate('Y-m-d H:i:s').' UTC';
$lines[] = '**Command:** `php scripts/jp-ops-05-route-inventory.php` + `php scripts/jp-ops-05-generate-mutation-matrix-md.php`';
$lines[] = '';
$lines[] = '## Denominator';
$lines[] = '';
$lines[] = '| Portal bucket | Mutation route records |';
$lines[] = '|---------------|----------------------:|';
$lines[] = '| Admin (`admin/*` incl. fallback) | 119 |';
$lines[] = '| Staff (`staff/*` incl. fallback) | 27 |';
$lines[] = '| Admin page settings (`admin/page-settings/*`) | 13 |';
$lines[] = '| **Canonical total** | **159** |';
$lines[] = '';
$lines[] = 'Semantics match JP-OPS-01 `blade_operational_mutations`: POST/PUT/PATCH/DELETE route records on Admin/Staff Blade fragments.';
$lines[] = '';
$lines[] = '## Fully connected mutations (6)';
$lines[] = '';
$lines[] = 'Reads (session, KPIs, deposit list/detail, capabilities) are **not** counted as mutations.';
$lines[] = 'A mutation is **CONNECTED** only when Laravel route, authorization, JSON contract, dashboard service, production UI, capability gate, duplicate protection, no-419-replay, and operational Playwright coverage are all present.';
$lines[] = '';
$lines[] = '| # | Method | URI | Route name | Next component |';
$lines[] = '|---|--------|-----|------------|----------------|';
$fullyConnectedList = [
    ['PATCH', 'admin/bookings/payments/{bookingPayment}/verify', 'admin.bookings.payments.verify', 'PaymentReviewActions'],
    ['PATCH', 'admin/bookings/payments/{bookingPayment}/reject', 'admin.bookings.payments.reject', 'PaymentReviewActions'],
    ['PATCH', 'staff/bookings/payments/{bookingPayment}/verify', 'staff.bookings.payments.verify', 'PaymentReviewActions'],
    ['PATCH', 'staff/bookings/payments/{bookingPayment}/reject', 'staff.bookings.payments.reject', 'PaymentReviewActions'],
    ['PATCH', 'admin/agent-deposits/{deposit}/approve', 'admin.agent-deposits.approve', 'DepositsWorkspace'],
    ['PATCH', 'admin/agent-deposits/{deposit}/reject', 'admin.agent-deposits.reject', 'DepositsWorkspace'],
];
foreach ($fullyConnectedList as $i => $item) {
    $lines[] = sprintf('| %d | %s | %s | %s | %s |', $i + 1, $item[0], $item[1], $item[2], $item[3]);
}
$lines[] = '';
$lines[] = '## Backend without Next binding (8)';
$lines[] = '';
$lines[] = 'Laravel JSON is additive on existing Blade routes; no production dashboard service function or reachable review UI.';
$lines[] = '';
$lines[] = '| # | Method | URI | Route name | Status |';
$lines[] = '|---|--------|-----|------------|--------|';
$backendOnlyList = [
    ['PATCH', 'admin/bookings/cancellations/{cancellationRequest}/approve', 'admin.bookings.cancellations.approve'],
    ['PATCH', 'admin/bookings/cancellations/{cancellationRequest}/reject', 'admin.bookings.cancellations.reject'],
    ['PATCH', 'staff/bookings/cancellations/{cancellationRequest}/approve', 'staff.bookings.cancellations.approve'],
    ['PATCH', 'staff/bookings/cancellations/{cancellationRequest}/reject', 'staff.bookings.cancellations.reject'],
    ['PATCH', 'admin/bookings/refunds/{bookingRefund}/approve', 'admin.bookings.refunds.approve'],
    ['PATCH', 'admin/bookings/refunds/{bookingRefund}/reject', 'admin.bookings.refunds.reject'],
    ['PATCH', 'staff/bookings/refunds/{bookingRefund}/approve', 'staff.bookings.refunds.approve'],
    ['PATCH', 'staff/bookings/refunds/{bookingRefund}/reject', 'staff.bookings.refunds.reject'],
];
foreach ($backendOnlyList as $i => $item) {
    $lines[] = sprintf('| %d | %s | %s | %s | BACKEND_WITHOUT_NEXT_BINDING |', $i + 1, $item[0], $item[1], $item[2]);
}
$lines[] = '';
$lines[] = '**CONNECTED (6) + BACKEND_WITHOUT_NEXT_BINDING (8) + DEFERRED (145) = 159**';
$lines[] = '';
$lines[] = '## Classification summary';
$lines[] = '';
$lines[] = '| Classification | Count |';
$lines[] = '|----------------|------:|';
$lines[] = '| CONNECTED (full Next binding) | 6 |';
$lines[] = '| BACKEND_WITHOUT_NEXT_BINDING | 8 |';
$lines[] = '| JP_OPS_06_EXECUTION_DEPENDENCY (JSON blocked or ticketing) | 6 |';
$lines[] = '| BLADE_FALLBACK_RETAINED | 139 |';
$lines[] = '| **Total** | **159** |';
$lines[] = '';
$lines[] = '## Full inventory';
$lines[] = '';
$lines[] = '| # | Portal | Method | URI | Route name | Controller | Blade | Next binding | Disposition | Deferral | Test |';
$lines[] = '|---|--------|--------|-----|------------|------------|-------|--------------|-------------|----------|------|';

/** @var list<array<string, mixed>> $rows */
$rows = $inventory['rows'];
foreach ($rows as $row) {
    $class = classifyRow($row, $fullyConnectedNames, $backendWithoutNextBindingNames, $blockedJsonNames);
    if ($class['next_binding'] === 'CONNECTED') {
        $fullyConnected++;
    } elseif ($class['next_binding'] === 'BACKEND_WITHOUT_NEXT_BINDING') {
        $backendOnly++;
    } else {
        $deferred++;
    }
    $lines[] = sprintf(
        '| %d | %s | %s | %s | %s | %s | operational | %s | %s | %s | %s |',
        $row['number'],
        $row['portal'],
        implode('|', $row['methods']),
        $row['uri'],
        $row['name'],
        str_replace('\\', '\\\\', (string) $row['action']),
        $class['next_binding'],
        $class['disposition'],
        $class['deferral'],
        $class['test_ref'],
    );
}

$lines[] = '';
$lines[] = "_Verified connected={$fullyConnected}, backend_without_next_binding={$backendOnly}, deferred={$deferred}, total=".count($rows).'_';

$out = __DIR__.'/../docs/operations/JP-OPS-05-BLADE-NEXT-MUTATION-MATRIX.md';
file_put_contents($out, implode("\n", $lines)."\n");
echo "written={$out}\nconnected={$fullyConnected}\nbackend_without_next_binding={$backendOnly}\ndeferred={$deferred}\ntotal=".count($rows)."\n";
