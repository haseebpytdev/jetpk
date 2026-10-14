<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$rawPath = __DIR__.'/_raw-route-list.json';
$outPath = __DIR__.'/MASTER-SURFACE-INVENTORY.csv';

$raw = file_get_contents($rawPath);
if ($raw === false) {
    fwrite(STDERR, "Missing route list\n");
    exit(1);
}
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
$routes = json_decode($raw, true);
if (! is_array($routes)) {
    fwrite(STDERR, 'JSON decode failed: '.json_last_error_msg()."\n");
    exit(1);
}

$out = fopen($outPath, 'w');
if ($out === false) {
    fwrite(STDERR, "Cannot write inventory\n");
    exit(1);
}

fputcsv($out, [
    'SURFACE_ID', 'AREA', 'PORTAL', 'ROLE', 'ROUTE', 'METHOD', 'PAGE_OR_ENDPOINT',
    'SOURCE_FILE', 'CONTROLLER_OR_HANDLER', 'AUTH_REQUIRED', 'CAPABILITY_REQUIRED',
    'DATA_SOURCE', 'MUTATION', 'EXTERNAL_EFFECT', 'LIVE_REACHABLE', 'AUDIT_STATUS',
]);

$i = 0;
foreach ($routes as $r) {
    $i++;
    $uri = (string) ($r['uri'] ?? '');
    $name = (string) ($r['name'] ?? '');
    $methods = $r['method'] ?? ($r['methods'] ?? '');
    if (is_array($methods)) {
        $methods = implode('|', $methods);
    }
    $methods = (string) $methods;
    $action = $r['action'] ?? '';
    $middleware = $r['middleware'] ?? [];
    if (is_string($middleware)) {
        $middleware = array_map('trim', explode(',', $middleware));
    }
    $mwList = array_values(array_filter(array_map(
        static fn ($m) => is_string($m) ? $m : '',
        (array) $middleware
    )));

    $portal = 'public';
    $area = 'WEB';
    $role = 'guest';
    $auth = 'N';

    if (str_starts_with($uri, 'api/dashboard')) {
        $portal = 'dashboard_bff';
        $area = 'API';
        $role = 'multi';
        $auth = 'Y';
    } elseif (str_starts_with($uri, 'api/')) {
        $portal = 'public';
        $area = 'API';
        $role = 'guest';
    } elseif (str_starts_with($uri, 'admin')) {
        $portal = 'admin';
        $area = 'WEB';
        $role = 'platform_admin';
        $auth = 'Y';
    } elseif (str_starts_with($uri, 'staff')) {
        $portal = 'staff';
        $area = 'WEB';
        $role = 'staff';
        $auth = 'Y';
    } elseif (str_starts_with($uri, 'agent')) {
        $portal = 'agent';
        $area = 'WEB';
        $role = 'agent|agent_staff';
        $auth = 'Y';
    } elseif (str_starts_with($uri, 'customer')) {
        $portal = 'customer';
        $area = 'WEB';
        $role = 'customer';
        $auth = 'Y';
    } elseif (str_starts_with($uri, 'dev/cp')) {
        $portal = 'dev_cp';
        $area = 'INTERNAL_OPS';
        $role = 'developer';
        $auth = 'Y';
    } elseif (preg_match('#^(login|register|password|forgot|reset|verify-email|auth/)#', $uri) === 1) {
        $portal = 'auth';
        $area = 'AUTH';
        $role = 'guest|user';
        $auth = 'mixed';
    } elseif (str_contains($uri, 'payment') || str_contains($uri, 'abhipay')) {
        $portal = 'payment';
        $area = 'PAYMENT';
        $role = 'mixed';
        $auth = 'mixed';
    } elseif (str_starts_with($uri, 'flights') || str_starts_with($uri, 'booking') || str_starts_with($uri, 'groups')) {
        $portal = 'public';
        $area = 'BOOKING';
        $role = 'guest|auth';
    }

    $mutation = preg_match('/POST|PUT|PATCH|DELETE/i', $methods) === 1 ? 'Y' : 'N';
    $blob = $uri.' '.$name;
    $ext = 'none';
    if (preg_match('/abhipay|payment/i', $blob) === 1) {
        $ext = 'payment';
    } elseif (preg_match('/ticket|pnr|supplier|sabre|pia|airblue|flight|book/i', $blob) === 1) {
        $ext = 'supplier_or_booking';
    } elseif (preg_match('/mail|notif|support/i', $blob) === 1) {
        $ext = 'email';
    } elseif (preg_match('/download|export|upload|document|invoice|voucher/i', $blob) === 1) {
        $ext = 'file';
    }

    $capParts = [];
    foreach ($mwList as $m) {
        if (str_contains($m, 'permission')
            || str_contains($m, 'account.type')
            || str_contains($m, 'platform.module')
            || str_contains($m, 'dashboard.permission')
        ) {
            $capParts[] = $m;
        }
    }
    if (in_array('auth', $mwList, true) || str_contains(implode(';', $mwList), 'auth')) {
        $auth = 'Y';
    }

    fputcsv($out, [
        sprintf('LR-%04d', $i),
        $area,
        $portal,
        $role,
        '/'.ltrim($uri, '/'),
        $methods,
        $name !== '' ? $name : $uri,
        'routes via artisan route:list',
        is_string($action) ? $action : json_encode($action),
        $auth,
        implode(';', $capParts),
        'laravel_route',
        $mutation,
        $ext,
        'UNKNOWN',
        'INVENTORIED_NOT_AUDITED',
    ]);
}

// Append Next.js surfaces (filesystem inventory)
$nextRows = [
    // dashboard (46 templates x note both portals)
    ['NX-D-001', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard', 'GET', 'dashboard home', 'dashboard/app/[portal]/dashboard/page.tsx', 'Next page', 'Y', 'portal session', 'next_dashboard', 'N', 'none', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-D-002', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard/bookings', 'GET', 'bookings list', 'dashboard/app/[portal]/dashboard/bookings/page.tsx', 'Next page', 'Y', 'bookings.view', 'next_dashboard', 'N', 'none', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-D-003', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard/bookings/[id]', 'GET', 'booking detail', 'dashboard/app/[portal]/dashboard/bookings/[id]/page.tsx', 'Next page', 'Y', 'bookings.view', 'next_dashboard', 'Y', 'supplier_or_booking', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-D-004', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard/payments', 'GET', 'payments', 'dashboard/app/[portal]/dashboard/payments/page.tsx', 'Next page', 'Y', 'payments.view', 'next_dashboard', 'Y', 'payment', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-D-005', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard/deposits', 'GET', 'deposits', 'dashboard/app/[portal]/dashboard/deposits/page.tsx', 'Next page', 'Y', 'agents.view', 'next_dashboard', 'Y', 'none', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-D-006', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard/pnrs', 'GET', 'pnrs', 'dashboard/app/[portal]/dashboard/pnrs/page.tsx', 'Next page', 'Y', 'pnrs.view', 'next_dashboard', 'N', 'supplier_or_booking', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-D-007', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard/tickets', 'GET', 'tickets', 'dashboard/app/[portal]/dashboard/tickets/page.tsx', 'Next page', 'Y', 'tickets.view', 'next_dashboard', 'N', 'supplier_or_booking', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-D-008', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard/system/go-live', 'GET', 'go-live', 'dashboard/app/[portal]/dashboard/system/go-live/page.tsx', 'Next page', 'Y', 'settings', 'next_dashboard', 'N', 'none', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-D-009', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard/api-connections', 'GET', 'api connections', 'dashboard/app/[portal]/dashboard/api-connections/page.tsx', 'Next page', 'Y', 'suppliers', 'next_dashboard', 'Y', 'supplier_or_booking', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-D-010', 'DASHBOARD_UI', 'admin|staff', 'platform_admin|staff', '/{portal}/dashboard/*', 'GET', 'remaining 37 dashboard templates aggregated — see PHASE1-NEXT-SURFACES.md', 'dashboard/app/[portal]/dashboard/**', 'Next page', 'Y', 'RBAC', 'next_dashboard', 'mixed', 'mixed', 'UNKNOWN', 'INVENTORIED_AGGREGATE'],
    // frontend aggregates
    ['NX-F-001', 'PUBLIC_UI', 'public', 'guest', '/', 'GET', 'homepage', 'frontend/app/(public)/page.tsx', 'Next page', 'N', 'none', 'next_frontend', 'N', 'none', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-F-002', 'PUBLIC_UI', 'public', 'guest', '/flights/*', 'GET', 'flight search/results/fare/return', 'frontend/app/flights/**', 'Next page', 'N', 'none', 'next_frontend', 'Y', 'supplier_or_booking', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-F-003', 'PUBLIC_UI', 'public', 'guest', '/booking/*', 'GET', 'checkout/payment/confirmation', 'frontend/app/(public)/booking/**', 'Next page', 'N', 'none', 'next_frontend', 'Y', 'payment', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-F-004', 'AUTH_UI', 'auth', 'guest', '/login|/register|/forgot-password|/reset-password/*', 'GET', 'auth pages', 'frontend/app/(auth)/**', 'Next page', 'N', 'none', 'next_frontend', 'Y', 'email', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-F-005', 'AGENT_UI', 'agent', 'agent|agent_staff', '/agent/*', 'GET', 'agent portal pages (27)', 'frontend/app/agent/**', 'Next page', 'Y', 'agent.permission', 'next_frontend', 'mixed', 'mixed', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-F-006', 'CUSTOMER_UI', 'customer', 'customer', '/customer/*', 'GET', 'customer portal pages (13)', 'frontend/app/customer/**', 'Next page', 'Y', 'customer portal', 'next_frontend', 'mixed', 'mixed', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-F-007', 'PUBLIC_UI', 'public', 'guest', '/groups/*', 'GET', 'group ticketing public flow', 'frontend/app/(public)/groups/**', 'Next page', 'N', 'module', 'next_frontend', 'Y', 'payment', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
    ['NX-F-API-001', 'API', 'internal', 'n/a', '/api/internal/revalidate/*', 'POST', 'Next revalidate secrets', 'frontend/app/api/internal/**', 'route.ts', 'Y', 'revalidate secret', 'next_frontend', 'Y', 'none', 'UNKNOWN', 'INVENTORIED_NOT_AUDITED'],
];

// Expand next page list from filesystem for completeness
$dashPages = glob($root.'/dashboard/app/[portal]/dashboard/**/page.tsx', GLOB_BRACE) ?: [];
// glob with brackets may fail on Windows — use RecursiveDirectoryIterator
$dashDir = $root.'/dashboard/app';
$feDir = $root.'/frontend/app';
$nx = 0;

$addNextPages = static function (string $baseDir, string $prefix, string $area, string $portal, string $role, string $auth) use (&$out, &$nx): int {
    $count = 0;
    if (! is_dir($baseDir)) {
        return 0;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getFilename() !== 'page.tsx') {
            continue;
        }
        $count++;
        $nx++;
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(dirname($baseDir, 2)) + 1));
        $routeHint = preg_replace('#^.*/app/#', '/', str_replace('\\', '/', $file->getPathname()));
        $routeHint = preg_replace('#/\([^)]+\)#', '', (string) $routeHint);
        $routeHint = preg_replace('#/page\.tsx$#', '', (string) $routeHint);
        $routeHint = str_replace(['[portal]', '[id]', '[reference]', '[slug]', '[token]', '[booking]', '[packageId]', '[bookingRef]', '[pathToken]', '[embedKey]'],
            ['{portal}', '{id}', '{reference}', '{slug}', '{token}', '{booking}', '{packageId}', '{bookingRef}', '{pathToken}', '{embedKey}'],
            (string) $routeHint);
        fputcsv($out, [
            sprintf('%s-%03d', $prefix, $nx),
            $area,
            $portal,
            $role,
            $routeHint !== '' ? $routeHint : '/',
            'GET',
            basename(dirname($file->getPathname())),
            $rel,
            'Next page.tsx',
            $auth,
            '',
            str_starts_with($prefix, 'NXD') ? 'next_dashboard' : 'next_frontend',
            'N',
            'none',
            'UNKNOWN',
            'INVENTORIED_NOT_AUDITED',
        ]);
    }

    return $count;
};

// Prefer filesystem expansion over aggregates for Next
$dashCount = 0;
$feCount = 0;
if (is_dir($dashDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dashDir));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getFilename() !== 'page.tsx') {
            continue;
        }
        $dashCount++;
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $path = str_replace('\\', '/', $file->getPathname());
        $route = preg_replace('#^.*/dashboard/app#', '', $path) ?? '';
        $route = preg_replace('#/page\.tsx$#', '', $route) ?? '';
        $route = str_replace('[portal]', '{portal}', $route);
        $route = preg_replace('#\[([^\]]+)\]#', '{$1}', $route) ?? $route;
        fputcsv($out, [
            sprintf('NXD-%03d', $dashCount),
            'DASHBOARD_UI',
            'admin|staff',
            'platform_admin|staff',
            $route,
            'GET',
            basename(dirname($file->getPathname())),
            $rel,
            'Next page.tsx',
            'Y',
            'portal session',
            'next_dashboard',
            'N',
            'none',
            'UNKNOWN',
            'INVENTORIED_NOT_AUDITED',
        ]);
    }
}
if (is_dir($feDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($feDir));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getFilename() !== 'page.tsx') {
            continue;
        }
        $feCount++;
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $path = str_replace('\\', '/', $file->getPathname());
        $route = preg_replace('#^.*/frontend/app#', '', $path) ?? '';
        $route = preg_replace('#/\([^/]+\)#', '', $route) ?? $route;
        $route = preg_replace('#/page\.tsx$#', '', $route) ?? '';
        $route = $route === '' ? '/' : $route;
        $route = preg_replace('#\[([^\]]+)\]#', '{$1}', $route) ?? $route;
        $portal = 'public';
        $role = 'guest';
        $auth = 'N';
        $area = 'PUBLIC_UI';
        if (str_starts_with($route, '/agent')) {
            $portal = 'agent';
            $role = 'agent|agent_staff';
            $auth = 'Y';
            $area = 'AGENT_UI';
        } elseif (str_starts_with($route, '/customer')) {
            $portal = 'customer';
            $role = 'customer';
            $auth = 'Y';
            $area = 'CUSTOMER_UI';
        } elseif (preg_match('#^/(login|register|forgot-password|reset-password|password|verify-email|agent/register)#', $route) === 1) {
            $portal = 'auth';
            $role = 'guest';
            $area = 'AUTH_UI';
        } elseif (str_starts_with($route, '/flights') || str_starts_with($route, '/booking')) {
            $area = 'BOOKING_UI';
        }
        fputcsv($out, [
            sprintf('NXF-%03d', $feCount),
            $area,
            $portal,
            $role,
            $route,
            'GET',
            basename(dirname($file->getPathname())),
            $rel,
            'Next page.tsx',
            $auth,
            '',
            'next_frontend',
            'N',
            'none',
            'UNKNOWN',
            'INVENTORIED_NOT_AUDITED',
        ]);
    }
}

// Schedule + jobs
$sched = [
    ['SCHED-001', 'BACKGROUND', 'system', 'n/a', 'schedule:ota:cleanup-expired-access', 'CLI', 'hourly', 'routes/console.php', 'ota:cleanup-expired-access', 'N', '', 'scheduler', 'Y', 'none', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-002', 'BACKGROUND', 'ops', 'n/a', 'schedule:ota:send-daily-report', 'CLI', 'daily 08:00', 'routes/console.php', 'ota:send-daily-report', 'N', '', 'scheduler', 'Y', 'email', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-003', 'BACKGROUND', 'ops', 'n/a', 'schedule:ota:send-agency-booking-activity-summary', 'CLI', 'daily 07:10', 'routes/console.php', 'ota:send-agency-booking-activity-summary', 'N', '', 'scheduler', 'Y', 'email', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-004', 'BACKGROUND', 'ops', 'n/a', 'schedule:ota:send-weekly-report', 'CLI', 'weekly Mon 08:00', 'routes/console.php', 'ota:send-weekly-report', 'N', '', 'scheduler', 'Y', 'email', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-005', 'BACKGROUND', 'ops', 'n/a', 'schedule:ota:send-monthly-report', 'CLI', 'monthly 1st 08:00', 'routes/console.php', 'ota:send-monthly-report', 'N', '', 'scheduler', 'Y', 'email', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-006', 'BACKGROUND', 'finance', 'n/a', 'schedule:ota:send-monthly-ledgers', 'CLI', 'monthly 1st 09:00', 'routes/console.php', 'ota:send-monthly-ledgers', 'N', '', 'scheduler', 'Y', 'email', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-007', 'BACKGROUND', 'cms', 'n/a', 'schedule:homepage:refresh-featured-fares', 'CLI', 'daily 05:00', 'routes/console.php', 'homepage:refresh-featured-fares', 'N', '', 'scheduler', 'Y', 'supplier_or_booking', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-008', 'BACKGROUND', 'cms', 'n/a', 'schedule:jetpk:homepage-route-fares-refresh', 'CLI', 'daily 00:30', 'routes/console.php', 'jetpk:homepage-route-fares-refresh', 'N', '', 'scheduler', 'Y', 'supplier_or_booking', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-009', 'BACKGROUND', 'marketing', 'n/a', 'schedule:ota:process-abandoned-flight-searches', 'CLI', 'every 15m', 'routes/console.php', 'ota:process-abandoned-flight-searches', 'N', '', 'scheduler', 'Y', 'none', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-010', 'BACKGROUND', 'marketing', 'n/a', 'schedule:ota:send-abandoned-flight-searches', 'CLI', 'every 15m', 'routes/console.php', 'ota:send-abandoned-flight-searches', 'N', '', 'scheduler', 'Y', 'email', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-011', 'BACKGROUND', 'groups', 'n/a', 'schedule:group-ticketing:sync-inventory', 'CLI', 'daily 02:00', 'routes/console.php', 'group-ticketing:sync-inventory', 'N', '', 'scheduler', 'Y', 'supplier_or_booking', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-012', 'BACKGROUND', 'groups', 'n/a', 'schedule:group-ticketing:release-expired', 'CLI', 'every minute', 'routes/console.php', 'group-ticketing:release-expired', 'N', '', 'scheduler', 'Y', 'none', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['SCHED-013', 'BACKGROUND', 'branding', 'n/a', 'schedule:jetpk:branding-background-cleanup', 'CLI', 'daily 03:15', 'routes/console.php', 'jetpk:branding-background-cleanup', 'N', '', 'scheduler', 'Y', 'file', 'N/A', 'INVENTORIED_NOT_AUDITED'],
    ['JOB-001', 'BACKGROUND', 'admin', 'n/a', 'queue:RemoveBrandLogoBackground', 'QUEUE', 'branding bg removal', 'app/Jobs/Branding/RemoveBrandLogoBackground.php', 'RemoveBrandLogoBackground', 'N', '', 'queue', 'Y', 'file', 'N/A', 'INVENTORIED_NOT_AUDITED'],
];
foreach ($sched as $row) {
    fputcsv($out, $row);
}

fclose($out);

$summary = [
    'laravel_routes' => $i,
    'next_dashboard_pages' => $dashCount,
    'next_frontend_pages' => $feCount,
    'schedule_and_jobs' => count($sched),
    'master_rows_approx' => $i + $dashCount + $feCount + count($sched),
    'unclassified' => 0,
];
file_put_contents(__DIR__.'/PHASE1-SURFACE-COUNTS.json', json_encode($summary, JSON_PRETTY_PRINT)."\n");
echo json_encode($summary, JSON_PRETTY_PRINT), PHP_EOL;
