<?php

declare(strict_types=1);

/**
 * Temporary production bootstrap for JP-DASH-PROD-01 QA reconcile.
 * Upload to /tmp, run via php, delete after use. Never echoes passwords.
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = $argv[1] ?? 'status';
$role = $argv[2] ?? null;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

if ($action === 'sync-password' && $role !== null) {
    $password = trim((string) stream_get_contents(STDIN));
    if ($password !== '') {
        putenv('JP_DASH_03_QA_'.strtoupper(str_replace('agent_staff', 'AGENT_STAFF', $role)).'_PASSWORD='.$password);
        $_ENV['JP_DASH_03_QA_'.strtoupper(str_replace('agent_staff', 'AGENT_STAFF', $role)).'_PASSWORD'] = $password;
    }
    $exit = $kernel->call('jetpk:dashboard-prod-cert-qa', [
        'action' => 'sync-password',
        '--role' => $role,
    ]);
    echo $kernel->output();
    exit($exit);
}

$exit = $kernel->call('jetpk:dashboard-prod-cert-qa', ['action' => $action]);
echo $kernel->output();
exit($exit);
