<?php

/**
 * Convert w1c-classify.json → triage CSVs + replay plan (local, no DB).
 */

$base = dirname(__FILE__);
$jsonPath = $base.'/w1c-classify.json';
$data = json_decode((string) file_get_contents($jsonPath), true);
if (! is_array($data)) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

$closures = $data['closures'] ?? [];
$booking = $data['booking_universal'] ?? [];

$closureHeaders = [
    'ID', 'CREATED_AT', 'AVAILABLE_AT', 'ATTEMPTS', 'ORIGINATING_EVENT_TYPE',
    'RELATED_DOMAIN', 'RELATED_RECORD_REFERENCE_SAFE', 'RECIPIENT_CLASS', 'AGE',
    'DELIVERY_ALREADY_RECORDED', 'BUSINESS_EVENT_STILL_CURRENT', 'DUPLICATE_SEND_RISK',
    'STALE', 'SAFE_TO_REPLAY', 'DISPOSITION',
];

$fh = fopen($base.'/DEFAULT-CLOSURE-JOB-TRIAGE.csv', 'w');
fputcsv($fh, $closureHeaders);
foreach ($closures as $row) {
    fputcsv($fh, [
        $row['id'] ?? '',
        $row['created_at'] ?? '',
        $row['available_at'] ?? '',
        $row['attempts'] ?? '',
        $row['originating_event_type'] ?? '',
        $row['related_domain'] ?? '',
        $row['related_record_reference_safe'] ?? '',
        $row['recipient_class'] ?? 'masked',
        ($row['age_hours'] ?? '').'h',
        ! empty($row['delivery_already_recorded']) ? 'YES' : 'NO',
        ! empty($row['business_event_still_current']) ? 'YES' : 'NO',
        ! empty($row['duplicate_send_risk']) ? 'YES' : 'NO',
        ! empty($row['stale']) ? 'YES' : 'NO',
        ! empty($row['safe_to_replay']) ? 'YES' : 'NO',
        $row['disposition'] ?? 'UNKNOWN',
    ]);
}
fclose($fh);

$buHeaders = [
    'ID', 'CREATED_AT', 'AVAILABLE_AT', 'ATTEMPTS', 'JOB_CLASS', 'ORIGINATING_EVENT_TYPE',
    'RELATED_DOMAIN', 'AGE', 'STALE', 'SAFE_TO_SEND_NOW', 'DISPOSITION',
];
$fh = fopen($base.'/DEFAULT-BOOKING-UNIVERSAL-TRIAGE.csv', 'w');
fputcsv($fh, $buHeaders);
foreach ($booking as $row) {
    fputcsv($fh, [
        $row['id'] ?? '',
        $row['created_at'] ?? '',
        $row['available_at'] ?? '',
        $row['attempts'] ?? '',
        $row['job_class'] ?? 'BookingUniversalNotification',
        $row['originating_event_type'] ?? '',
        $row['related_domain'] ?? 'BOOKING_PAYMENT_COMMS',
        ($row['age_hours'] ?? '').'h',
        ! empty($row['stale']) ? 'YES' : 'NO',
        ! empty($row['safe_to_send_now']) ? 'YES' : 'NO',
        $row['disposition'] ?? 'OBSOLETE_DELETE',
    ]);
}
fclose($fh);

$replayHeaders = [
    'JOB_ID', 'COMMUNICATION_LOG_REF', 'ORIGINATING_EVENT_TYPE', 'RELATED_DOMAIN',
    'AGE_HOURS', 'REPLAY_CLASS', 'NOTES',
];
$fh = fopen($base.'/NOTIFICATION-REPLAY-PLAN.csv', 'w');
fputcsv($fh, $replayHeaders);
foreach ($closures as $row) {
    $disp = (string) ($row['disposition'] ?? '');
    if (! in_array($disp, ['CURRENT_REPLAY_REQUIRED', 'MANUAL_REVIEW'], true)) {
        continue;
    }
    $class = $disp === 'CURRENT_REPLAY_REQUIRED' ? 'REPLAY_REQUIRED_CURRENT' : 'MANUAL_REVIEW';
    fputcsv($fh, [
        $row['id'] ?? '',
        $row['related_record_reference_safe'] ?? 'none',
        $row['originating_event_type'] ?? '',
        $row['related_domain'] ?? '',
        $row['age_hours'] ?? '',
        $class,
        'Do not execute serialized Closure; re-emit via DeliverQueuedOperationalMail only after deploy + explicit safety proof',
    ]);
}
fclose($fh);

$unknownClosure = 0;
foreach ($closures as $row) {
    if (($row['disposition'] ?? 'UNKNOWN') === 'UNKNOWN') {
        $unknownClosure++;
    }
}

echo 'CLOSURE_ROWS='.count($closures).PHP_EOL;
echo 'CLOSURE_UNKNOWN='.$unknownClosure.PHP_EOL;
echo 'BOOKING_UNIVERSAL_ROWS='.count($booking).PHP_EOL;
echo 'REPLAY_PLAN_ROWS='.(count(file($base.'/NOTIFICATION-REPLAY-PLAN.csv')) - 1).PHP_EOL;
echo 'OK'.PHP_EOL;
