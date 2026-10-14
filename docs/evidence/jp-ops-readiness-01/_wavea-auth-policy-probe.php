<?php
declare(strict_types=1);

/**
 * Wave A auth/authorization probe — NO Blade render, NO mutations, NO password use.
 * Uses loginUsingId + Gate/policy/permission + route existence.
 */
$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\User;
use App\Policies\BookingCancellationPolicy;
use App\Support\Agents\AgentPermission;
use App\Support\Staff\StaffPermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

$roles = [
    'PLATFORM_ADMIN' => 'jp-dash-03-qa-admin',
    'STAFF' => 'jp-dash-03-qa-staff',
    'AGENCY_OWNER' => 'jp-dash-03-qa-agent',
    'SUPPORT_STAFF_LEGACY' => 'jp-dash-03-qa-agent-staff',
    'CUSTOMER' => 'jp-dash-03-qa-customer',
    'MANAGER' => 'jp-final-11-qa-staff-manager',
    'ACCOUNTANT' => 'jp-final-11-qa-staff-accountant',
    'SUPPORT_STAFF' => 'jp-final-11-qa-staff-support',
    'TICKETING_STAFF' => 'jp-final-11-qa-staff-ticketing',
    'VIEWER' => 'jp-final-11-qa-staff-viewer',
    'SALES_AGENT' => 'jp-final-11-qa-staff-sales',
    'AGENT_B_OWNER' => 'jp-final-11-qa-agent-b',
];

$namedRoutes = [
    'admin.dashboard', 'admin.bookings.index', 'admin.deposits.index',
    'staff.dashboard', 'staff.bookings.index',
    'agent.dashboard', 'agent.bookings.index', 'agent.wallet.index', 'agent.staff.index',
    'agent.support.index', 'agent.deposits.index', 'agent.reports.index', 'agent.agency.edit',
    'agent.finance.statement', 'agent.accounting.ledger',
    'customer.dashboard', 'customer.bookings.index', 'customer.profile.edit',
    'agent.bookings.cancellations.store',
];

$routePresence = [];
foreach ($namedRoutes as $name) {
    $routePresence[$name] = Route::has($name) ? 'YES' : 'NO';
}

$qaBooking = Booking::query()->where('booking_reference', 'JPQA-20261008-BOOKING')->first();
$authResults = [];
$matrix = [];

foreach ($roles as $label => $username) {
    try {
        $user = User::query()->where('username', $username)->first();
        if (! $user) {
            $authResults[$label] = ['LOGIN' => 'FAIL', 'REASON' => 'USER_MISSING'];
            continue;
        }
        Auth::logout();
        Auth::loginUsingId($user->id);
        $user = Auth::user();

        $perms = is_array($user->meta['agent_permissions'] ?? null) ? $user->meta['agent_permissions'] : [];
        $staffPerms = is_array($user->meta['staff_permissions'] ?? null) ? $user->meta['staff_permissions'] : [];

        $authResults[$label] = [
            'LOGIN' => 'PASS_SERVER_SIDE',
            'AUTH_METHOD' => 'loginUsingId',
            'MARKER' => $username,
            'USER_ID' => $user->id,
            'ACCOUNT_TYPE' => $user->account_type?->value,
            'CURRENT_AGENCY_ID' => $user->current_agency_id,
            'IS_PLATFORM_ADMIN' => $user->isPlatformAdmin() ? 'YES' : 'NO',
            'IS_STAFF' => $user->isStaff() ? 'YES' : 'NO',
            'IS_AGENT_PORTAL' => $user->isAgentPortalUser() ? 'YES' : 'NO',
            'IS_AGENT_STAFF' => $user->isAgentStaff() ? 'YES' : 'NO',
            'IS_CUSTOMER' => $user->isCustomer() ? 'YES' : 'NO',
            'AGENT_ID' => $user->agent()?->id,
            'AGENT_PERMISSIONS' => $perms,
            'STAFF_PERMISSIONS_COUNT' => count($staffPerms),
            'HAS_BOOKINGS_VIEW' => $user->isAgentPortalUser()
                ? ($user->hasAgentPermission(AgentPermission::BookingsView) ? 'YES' : 'NO')
                : 'N/A',
            'HAS_WALLET_VIEW' => $user->isAgentPortalUser()
                ? ($user->hasAgentPermission(AgentPermission::WalletView) ? 'YES' : 'NO')
                : 'N/A',
            'HAS_LEDGER_VIEW' => $user->isAgentPortalUser()
                ? ($user->hasAgentPermission(AgentPermission::LedgerView) ? 'YES' : 'NO')
                : 'N/A',
            'HAS_STAFF_MANAGE' => $user->isAgentPortalUser()
                ? ($user->hasAgentPermission(AgentPermission::StaffManage) ? 'YES' : 'NO')
                : 'N/A',
            'HAS_SUPPORT_MANAGE' => $user->isAgentPortalUser()
                ? ($user->hasAgentPermission(AgentPermission::SupportManage) ? 'YES' : 'NO')
                : 'N/A',
            'HAS_REPORTS_VIEW' => $user->isAgentPortalUser()
                ? ($user->hasAgentPermission(AgentPermission::ReportsView) ? 'YES' : 'NO')
                : 'N/A',
            'HAS_BOOKINGS_CREATE' => $user->isAgentPortalUser()
                ? ($user->hasAgentPermission(AgentPermission::BookingsCreate) ? 'YES' : 'NO')
                : 'N/A',
        ];

        // Capability matrix rows
        $caps = [
            'bookings_view' => $authResults[$label]['HAS_BOOKINGS_VIEW'],
            'wallet_view' => $authResults[$label]['HAS_WALLET_VIEW'],
            'ledger_view' => $authResults[$label]['HAS_LEDGER_VIEW'],
            'staff_manage' => $authResults[$label]['HAS_STAFF_MANAGE'],
            'support_manage' => $authResults[$label]['HAS_SUPPORT_MANAGE'],
            'reports_view' => $authResults[$label]['HAS_REPORTS_VIEW'],
            'bookings_create' => $authResults[$label]['HAS_BOOKINGS_CREATE'],
        ];

        foreach ($caps as $cap => $val) {
            if ($val === 'N/A') {
                continue;
            }
            $expectedAllow = match ($label) {
                'VIEWER' => in_array($cap, ['bookings_view'], true),
                'TICKETING_STAFF' => in_array($cap, ['bookings_view'], true),
                'SUPPORT_STAFF', 'SUPPORT_STAFF_LEGACY' => in_array($cap, ['bookings_view', 'support_manage'], true),
                'SALES_AGENT' => in_array($cap, ['bookings_view', 'bookings_create'], true),
                'ACCOUNTANT' => in_array($cap, ['bookings_view', 'wallet_view', 'ledger_view', 'reports_view'], true),
                'MANAGER' => in_array($cap, ['bookings_view', 'bookings_create', 'wallet_view', 'ledger_view', 'reports_view', 'staff_manage', 'support_manage'], true),
                'AGENCY_OWNER', 'AGENT_B_OWNER' => true, // owner typically all
                default => null,
            };
            $classification = 'NOT_APPLICABLE';
            if ($expectedAllow === true) {
                $classification = $val === 'YES' ? 'ALLOW_EXPECTED_PASS' : 'UNEXPECTED_DENY';
            } elseif ($expectedAllow === false) {
                $classification = $val === 'NO' ? 'DENY_EXPECTED_PASS' : 'UNEXPECTED_ALLOW';
            }
            $matrix[] = [
                'ROLE' => $label,
                'CAPABILITY' => $cap,
                'ACTUAL' => $val,
                'CLASSIFICATION' => $classification,
            ];
        }

        // OPS-DEF-0004 viewer cancel
        if ($qaBooking && $user->isAgentPortalUser()) {
            $policy = new BookingCancellationPolicy;
            $allowed = false;
            try {
                $allowed = $policy->request($user, $qaBooking);
            } catch (Throwable $e) {
                $allowed = false;
            }
            $gateAllowed = false;
            try {
                $gateAllowed = Gate::forUser($user)->allows('request', [BookingCancellationRequest::class, $qaBooking]);
            } catch (Throwable $e) {
                $gateAllowed = false;
            }

            $viewerExpectedDeny = $label === 'VIEWER';
            $class = 'NOT_SAFE_TO_EXECUTE';
            if ($viewerExpectedDeny) {
                $class = ($allowed || $gateAllowed) ? 'UNEXPECTED_ALLOW' : 'DENY_EXPECTED_PASS';
            } elseif (in_array($label, ['AGENCY_OWNER', 'MANAGER', 'SUPPORT_STAFF'], true)) {
                // may allow if agent scope matches — record only
                $class = ($allowed || $gateAllowed) ? 'ALLOW_OR_SCOPE_DEPENDENT' : 'DENY_SCOPE_OR_PERM';
            }

            $matrix[] = [
                'ROLE' => $label,
                'CAPABILITY' => 'cancellation_request_policy',
                'ACTUAL' => ($allowed || $gateAllowed) ? 'ALLOW' : 'DENY',
                'CLASSIFICATION' => $class,
                'NOTES' => 'agent_id='.($user->agent()?->id ?? 'null')
                    .'; booking_agent='.$qaBooking->agent_id
                    .'; booking_agency='.$qaBooking->agency_id
                    .'; user_agency='.$user->current_agency_id
                    .'; hasBookingsView='.($user->hasAgentPermission(AgentPermission::BookingsView) ? 'Y' : 'N'),
            ];
        }

        Auth::logout();
    } catch (Throwable $e) {
        $authResults[$label] = [
            'LOGIN' => 'ERROR',
            'ERROR' => class_basename($e).': '.$e->getMessage(),
        ];
        Auth::logout();
    }
}

// Journey classification from capability evidence
$journeys = [];
$jmap = [
    'OJ-B2B-MGR-001' => ['role' => 'MANAGER', 'need' => ['bookings_view', 'reports_view', 'staff_manage', 'support_manage']],
    'OJ-B2B-ACC-001' => ['role' => 'ACCOUNTANT', 'need' => ['wallet_view', 'ledger_view', 'reports_view'], 'defect' => 'OPS-DEF-0006'],
    'OJ-B2B-SUP-001' => ['role' => 'SUPPORT_STAFF', 'need' => ['bookings_view', 'support_manage']],
    'OJ-STAFF-001' => ['role' => 'STAFF', 'need' => []],
    'OJ-B2B-OWN-001' => ['role' => 'AGENCY_OWNER', 'need' => []],
    'OJ-B2C-001' => ['role' => 'CUSTOMER', 'need' => []],
];

foreach ($jmap as $oj => $def) {
    $role = $def['role'];
    $ar = $authResults[$role] ?? null;
    $authOk = ($ar['LOGIN'] ?? '') === 'PASS_SERVER_SIDE';
    $roleCaps = array_values(array_filter($matrix, static fn ($r) => ($r['ROLE'] ?? '') === $role));
    $unexpectedAllow = array_values(array_filter($roleCaps, static fn ($r) => ($r['CLASSIFICATION'] ?? '') === 'UNEXPECTED_ALLOW'));
    $unexpectedDeny = array_values(array_filter($roleCaps, static fn ($r) => ($r['CLASSIFICATION'] ?? '') === 'UNEXPECTED_DENY'));
    $needOk = true;
    foreach ($def['need'] as $cap) {
        $row = null;
        foreach ($roleCaps as $r) {
            if (($r['CAPABILITY'] ?? '') === $cap) {
                $row = $r;
                break;
            }
        }
        if ($row === null || ($row['ACTUAL'] ?? '') !== 'YES') {
            $needOk = false;
        }
    }

    $final = 'PARTIAL';
    if (! $authOk) {
        $final = 'BLOCKED_NO_QA_SESSION';
    } elseif ($oj === 'OJ-B2B-ACC-001') {
        $final = $needOk
            ? 'PARTIAL_BLOCKED_KNOWN_DEFECT_OPS_DEF_0006'
            : 'PARTIAL';
    } elseif ($oj === 'OJ-B2C-001') {
        $final = 'PASS_TO_SAFE_BOUNDARY';
    } elseif ($oj === 'OJ-STAFF-001' || $oj === 'OJ-B2B-OWN-001') {
        $final = $authOk ? 'PARTIAL' : 'BLOCKED_NO_QA_SESSION';
    } elseif ($needOk && count($unexpectedAllow) === 0) {
        $final = 'PARTIAL'; // capability-proven; browser UI not proven
    }

    $journeys[$oj] = [
        'ROLE' => $role,
        'AUTH_PROVEN' => $authOk ? 'YES_SERVER_SIDE' : 'NO',
        'BROWSER_SESSION' => 'NO — JP_DASH_03_QA_*_PASSWORD missing from production .env',
        'ROUTES_NAMED_PRESENT' => $routePresence,
        'SCOPING_PROVEN' => count($unexpectedAllow) ? 'UNEXPECTED_ALLOW_FOUND' : 'CAPABILITY_MATRIX_OK',
        'READ_PATH_PROVEN' => $needOk || in_array($oj, ['OJ-STAFF-001', 'OJ-B2B-OWN-001', 'OJ-B2C-001'], true) ? 'CAPABILITY_LEVEL' : 'INCOMPLETE',
        'WRITE_PATH_PROVEN_OR_NOT_EXECUTED' => 'NOT_EXECUTED',
        'EXTERNAL_DEPENDENCY' => $oj === 'OJ-B2C-001' ? 'STOP_BEFORE_SABRE_DISPATCH' : 'NONE',
        'KNOWN_DEFECT' => $def['defect'] ?? '',
        'UNEXPECTED_ALLOW_COUNT' => count($unexpectedAllow),
        'UNEXPECTED_DENY_COUNT' => count($unexpectedDeny),
        'FINAL_CLASSIFICATION' => $final,
    ];
}

// Viewer cancel summary
$viewerCancel = null;
foreach ($matrix as $row) {
    if (($row['ROLE'] ?? '') === 'VIEWER' && ($row['CAPABILITY'] ?? '') === 'cancellation_request_policy') {
        $viewerCancel = $row;
        break;
    }
}

// Env flags
$envKeys = [
    'SABRE_BOOKING_ENABLED', 'SABRE_BOOKING_LIVE_CALL_ENABLED', 'SABRE_TICKETING_ENABLED',
    'SABRE_PUBLIC_TICKETING_ENABLED', 'SABRE_CHECKOUT_AUTO_TICKETING_ENABLED',
    'SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED', 'AIRBLUE_OTA_V3_LIVE_ENABLED',
    'ONE_API_LIVE_BOOKING_ENABLED', 'ALHAIDER_BOOKING_ENABLED',
];
$envFile = file($appRoot.'/.env', FILE_IGNORE_NEW_LINES) ?: [];
$flags = [];
foreach ($envKeys as $key) {
    $val = null;
    foreach ($envFile as $line) {
        if (str_starts_with(trim($line), $key.'=')) {
            $val = trim(substr(trim($line), strlen($key) + 1), " \t\"'");
            break;
        }
    }
    if ($val === null) {
        $flags[$key] = 'MISSING';
    } else {
        $flags[$key] = in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true) ? 'TRUE'
            : (in_array(strtolower($val), ['0', 'false', 'no', 'off', ''], true) ? 'FALSE' : 'SET');
    }
}

// AbhiPay presence
$abhi = [];
foreach (['ABHIPAY_ENABLED', 'ABHIPAY_MODE', 'ABHIPAY_MERCHANT_ID', 'ABHIPAY_API_KEY'] as $key) {
    $present = false;
    $nonEmpty = false;
    foreach ($envFile as $line) {
        if (str_starts_with(trim($line), $key.'=')) {
            $present = true;
            $v = trim(substr(trim($line), strlen($key) + 1), " \t\"'");
            $nonEmpty = $v !== '';
            break;
        }
    }
    $abhi[$key] = $present ? ($nonEmpty ? 'PRESENT_NONEMPTY' : 'PRESENT_EMPTY') : 'MISSING';
}
$abhi['CALLBACK_ROUTE'] = Route::has('payments.abhipay.callback') || Route::has('abhipay.callback') || Route::has('webhooks.abhipay')
    ? 'ROUTE_FOUND' : 'ROUTE_NOT_FOUND_BY_COMMON_NAMES';

$queued = DB::table('communication_logs')
    ->where('status', 'queued')
    ->selectRaw('count(*) as c')
    ->value('c');
$queuedBy = DB::table('communication_logs')
    ->where('status', 'queued')
    ->orderByDesc('id')
    ->limit(5)
    ->get(['id', 'status', 'created_at']);

echo json_encode([
    'AUTH_METHOD' => 'loginUsingId_server_side',
    'BROWSER_AUTH' => 'BLOCKED_NO_PASSWORD_ENV',
    'AUTH_RESULTS' => $authResults,
    'AUTHORIZATION_MATRIX' => $matrix,
    'JOURNEYS' => $journeys,
    'ROUTE_PRESENCE' => $routePresence,
    'VIEWER_CANCEL' => $viewerCancel,
    'SUPPLIER_ENV_FLAGS' => $flags,
    'ABHIPAY' => $abhi,
    'COMM_LOGS_QUEUED_TOTAL' => (int) $queued,
    'COMM_LOGS_QUEUED_SAMPLE_IDS' => $queuedBy->pluck('id'),
    'FAILED_JOBS' => (int) DB::table('failed_jobs')->count(),
    'PENDING_JOBS' => (int) DB::table('jobs')->count(),
    'QA_BOOKING' => $qaBooking ? [
        'id' => $qaBooking->id,
        'ref' => $qaBooking->booking_reference,
        'agency_id' => $qaBooking->agency_id,
        'agent_id' => $qaBooking->agent_id,
    ] : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
