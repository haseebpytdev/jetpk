<?php

declare(strict_types=1);

/**
 * JP-OPS-07 mutually exclusive mutation classification for the canonical 159 inventory.
 * Run after: php scripts/jp-ops-05-route-inventory.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$inventoryPath = __DIR__.'/../storage/app/jp-ops-05-mutation-inventory.json';
/** @var array<string, mixed> $inventory */
$inventory = json_decode((string) file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);

/** @var list<string> */
$jpOps05Connected = [
    'admin.bookings.payments.verify',
    'admin.bookings.payments.reject',
    'staff.bookings.payments.verify',
    'staff.bookings.payments.reject',
    'admin.agent-deposits.approve',
    'admin.agent-deposits.reject',
];

/** @var list<string> */
$jpOps06Connected = [
    'admin.bookings.cancellations.process',
    'staff.bookings.cancellations.process',
    'admin.bookings.refunds.mark-paid',
    'staff.bookings.refunds.mark-paid',
    'admin.bookings.issue-ticket',
    'staff.bookings.issue-ticket',
];

/** @var list<string> */
$jpOps07ReviewConnected = [
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
$jpOps07CoreConnected = [
    'admin.bookings.notes',
    'staff.bookings.notes',
    'admin.bookings.assign-staff',
    'admin.bookings.cancellations.store',
    'staff.bookings.cancellations.store',
    'admin.bookings.refunds.store',
    'staff.bookings.refunds.store',
    'admin.bookings.payments.store',
    'staff.bookings.payments.store',
    'admin.users.activate',
    'admin.users.suspend',
    'admin.agencies.users.agency-role.update',
    'admin.agencies.users.agent-permissions.update',
    'admin.agencies.users.agent-permissions.apply-template',
    'admin.agencies.prefix.update',
    'admin.agent-applications.approve',
    'admin.agent-applications.reject',
    'admin.agent-applications.needs-more-info',
    'admin.support.tickets.assign',
    'admin.support.tickets.forward',
    'admin.support.tickets.reply',
    'admin.support.tickets.status',
    'staff.support.tickets.reply',
    'staff.support.tickets.status',
    'admin.commissions.entries.approve',
    'admin.commissions.entries.reject',
    'admin.group-bookings.verify-payment',
    'admin.group-bookings.reject-payment',
    'admin.finance.adjustments.store',
    'admin.finance.adjustments.reverse',
];

/** @var list<string> */
$runtimeDeferred = [
    'admin.bookings.communication.send',
    'admin.bookings.communication.resend',
    'admin.users.send-invite',
    'admin.users.reset-password-link',
    'admin.bookings.supplier-booking',
    'staff.bookings.supplier-booking',
    'admin.bookings.prepare-supplier-pnr-context',
    'staff.bookings.prepare-supplier-pnr-context',
    'admin.bookings.sync-pnr-itinerary',
    'staff.bookings.sync-pnr-itinerary',
    'admin.bookings.sync-airblue-booking',
    'admin.bookings.sync-iati-booking',
    'admin.bookings.sync-pia-ndc-booking',
    'admin.bookings.create-pia-ndc-option-pnr',
    'admin.bookings.release-pia-ndc-option-pnr',
    'admin.bookings.preview-pia-ndc-ticket',
    'admin.bookings.refresh-pia-ndc-status',
    'admin.bookings.resend-pia-ndc-eticket',
    'admin.bookings.void-pia-ndc-ticket',
];

/** @var list<string> */
$intentionalBlade = [
    'admin.',
    'staff.',
    'admin.bookings.status',
    'staff.bookings.status',
    'admin.bookings.manual-pnr',
    'staff.bookings.manual-pnr',
    'admin.bookings.payments.documents.receipt',
    'staff.bookings.payments.documents.receipt',
    'admin.bookings.documents.confirmation',
    'admin.bookings.documents.invoice',
    'admin.bookings.documents.ticket-itinerary',
    'admin.bookings.documents.cancellation-confirmation',
    'admin.bookings.documents.refund-note',
    'staff.bookings.documents.confirmation',
    'staff.bookings.documents.invoice',
    'staff.bookings.documents.ticket-itinerary',
    'staff.bookings.documents.cancellation-confirmation',
    'staff.bookings.documents.refund-note',
    'admin.users.store',
    'admin.users.update',
    'admin.commissions.adjustments.store',
    'admin.commissions.payouts.store',
    'admin.commissions.statements.store',
    'admin.finance.wallet-audit.archive',
    'admin.group-bookings.restrictions.reset',
];

function classifyJpOps07(array $row): string
{
    global $jpOps05Connected, $jpOps06Connected, $jpOps07ReviewConnected, $jpOps07CoreConnected,
        $runtimeDeferred, $intentionalBlade;

    $name = (string) ($row['name'] ?? '');
    $uri = (string) ($row['uri'] ?? '');
    $portal = (string) ($row['portal'] ?? '');

    $allConnected = array_merge($jpOps05Connected, $jpOps06Connected, $jpOps07ReviewConnected, $jpOps07CoreConnected);
    if (in_array($name, $allConnected, true)) {
        return 'CONNECTED';
    }

    if (in_array($name, $runtimeDeferred, true)) {
        return 'DEFERRED_TO_JP-RUNTIME-01';
    }

    if (in_array($name, $intentionalBlade, true)) {
        return 'INTENTIONAL_BLADE_FALLBACK';
    }

    if (str_contains($uri, 'page-settings') || $portal === 'admin_page_settings') {
        return 'DEFERRED_TO_JP-UX-CMS-01';
    }

    if (
        str_contains($uri, 'cms')
        || str_contains($uri, 'settings')
        || str_contains($uri, 'markups')
        || str_contains($uri, 'promo')
        || str_contains($uri, 'api-settings')
        || str_contains($uri, 'group-ticketing')
    ) {
        return 'DEFERRED_TO_JP-UX-CMS-01';
    }

    return 'INTENTIONAL_BLADE_FALLBACK';
}

/** @var array<string, int> $totals */
$totals = [];
/** @var list<array<string, mixed>> $classified */
$classified = [];

/** @var list<array<string, mixed>> $rows */
$rows = $inventory['rows'];
foreach ($rows as $row) {
    $class = classifyJpOps07($row);
    $totals[$class] = ($totals[$class] ?? 0) + 1;
    $classified[] = array_merge($row, ['jp_ops_07_class' => $class]);
}

$outPath = __DIR__.'/../storage/app/jp-ops-07-mutation-classification.json';
file_put_contents($outPath, json_encode([
    'generated_at' => gmdate('c'),
    'denominator' => count($rows),
    'totals' => $totals,
    'mandatory_review' => $jpOps07ReviewConnected,
    'core_connect' => $jpOps07CoreConnected,
    'rows' => $classified,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo 'written='.$outPath."\n";
echo 'total='.count($rows)."\n";
foreach ($totals as $class => $count) {
    echo $class.'='.$count."\n";
}
