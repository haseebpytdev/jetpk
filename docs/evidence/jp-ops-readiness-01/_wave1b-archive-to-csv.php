<?php

$j = json_decode(file_get_contents(__DIR__.'/FAILED-JOBS-ARCHIVE-20261014.json'), true);
$out = fopen(__DIR__.'/FAILED-JOBS-ARCHIVE-20261014.csv', 'w');
fputcsv($out, ['ID', 'UUID', 'JOB_CLASS', 'QUEUE', 'FAILED_AT', 'EXCEPTION_CLASS', 'SANITIZED_ERROR_SIGNATURE', 'BUSINESS_EFFECT', 'DISPOSITION']);
foreach ($j['rows'] as $r) {
    fputcsv($out, [$r['id'], $r['uuid'], $r['job_class'], $r['queue'], $r['failed_at'], $r['exception_class'], $r['sanitized_error_signature'], $r['business_effect'], $r['disposition']]);
}
fclose($out);
echo 'ROWS='.count($j['rows']).PHP_EOL;
