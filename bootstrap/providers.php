<?php

use App\Providers\AppServiceProvider;

return [
    App\Providers\EventServiceProvider::class,
    AppServiceProvider::class,
    App\Providers\OneApiServiceProvider::class,
    App\Providers\VisaServiceProvider::class,
];
