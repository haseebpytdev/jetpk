<?php

return [

    'pipeline' => [
        'enabled' => (bool) env('NOTIFICATION_PIPELINE_ENABLED', true),
        // false = process outbox/delivery inline (no dedicated worker required).
        'async' => (bool) env('NOTIFICATION_PIPELINE_ASYNC', false),
        'compat_queue' => (string) env('NOTIFICATION_COMPAT_QUEUE', 'default'),
        'lock_timeout_seconds' => (int) env('NOTIFICATION_OUTBOX_LOCK_TIMEOUT', 300),
        'max_attempts' => (int) env('NOTIFICATION_OUTBOX_MAX_ATTEMPTS', 8),
    ],

    'queues' => [
        'critical' => 'notifications-critical',
        'transactional' => 'notifications-transactional',
        'ops' => 'notifications-ops',
        'bulk' => 'notifications-bulk',
    ],

];
