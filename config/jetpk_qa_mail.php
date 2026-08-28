<?php

return [
    'enabled' => (bool) env('JETPK_QA_MAIL_SINK_ENABLED', false),
    'recipient' => env('JETPK_QA_MAIL_SINK_RECIPIENT'),
];
