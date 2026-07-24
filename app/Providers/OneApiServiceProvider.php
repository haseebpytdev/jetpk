<?php

namespace App\Providers;

use App\Contracts\Suppliers\OneApi\OneApiSoapTransportContract;
use App\Services\Suppliers\OneApi\Transport\FixtureOneApiSoapTransport;
use App\Services\Suppliers\OneApi\Transport\LiveOneApiSoapTransport;
use App\Support\OneApi\OneApiFixtureTransportScope;
use Illuminate\Support\ServiceProvider;

class OneApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OneApiSoapTransportContract::class, function ($app): OneApiSoapTransportContract {
            if (OneApiFixtureTransportScope::isExplicitlyEnabled()) {
                return $app->make(FixtureOneApiSoapTransport::class);
            }

            return $app->make(LiveOneApiSoapTransport::class);
        });
    }
}
