<?php

$base = dirname(__FILE__);
$data = json_decode((string) file_get_contents($base.'/w1c-classify.json'), true);
$closures = $data['closures'] ?? [];

$missed = 0;
$missedCritical = 0;
$dupRisk = 0;
$byDomain = [];
$byDisp = [];

foreach ($closures as $row) {
    $domain = (string) ($row['related_domain'] ?? 'OTHER');
    $byDomain[$domain] = ($byDomain[$domain] ?? 0) + 1;
    $disp = (string) ($row['disposition'] ?? 'UNKNOWN');
    $byDisp[$disp] = ($byDisp[$disp] ?? 0) + 1;

    $already = ! empty($row['delivery_already_recorded']);
    $status = (string) ($row['communication_log_status'] ?? '');
    if (! $already && in_array($status, ['queued', 'pending', ''], true)) {
        $missed++;
        // Critical = booking/payment domains or CURRENT_REPLAY under 24h
        if (in_array($domain, ['BOOKING', 'PAYMENT'], true) || $disp === 'CURRENT_REPLAY_REQUIRED') {
            $missedCritical++;
        }
    }
    if (! empty($row['duplicate_send_risk'])) {
        $dupRisk++;
    }
}

$out = [
    'MISSED_NOTIFICATIONS_TOTAL' => $missed,
    'MISSED_CRITICAL_NOTIFICATIONS' => $missedCritical,
    'MISSED_NONCRITICAL_NOTIFICATIONS' => max(0, $missed - $missedCritical),
    'ALREADY_DELIVERED_DUPLICATE_RISK' => $dupRisk,
    'DOMAIN_COUNTS' => $byDomain,
    'DISPOSITION_COUNTS' => $byDisp,
    'BOOKING_UNIVERSAL_COUNT' => count($data['booking_universal'] ?? []),
    'SAFE_TO_SEND_NOW_BOOKING_UNIVERSAL' => 0,
];

file_put_contents($base.'/COMMUNICATION-IMPACT-WAVE-1C.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo json_encode($out, JSON_PRETTY_PRINT)."\n";
