<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External operational alert delivery (OPS-DEF-0022)
    |--------------------------------------------------------------------------
    |
    | Local disk-health logging remains the default. External mail delivery is
    | opt-in and never targets customers. Failures are non-fatal.
    |
    */

    'external_alerts_enabled' => (bool) env('OPS_EXTERNAL_ALERTS_ENABLED', false),

    'alert_email' => env('OPS_ALERT_EMAIL'),

    'alert_dry_run' => (bool) env('OPS_ALERT_DRY_RUN', false),

];
