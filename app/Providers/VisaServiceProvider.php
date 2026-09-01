<?php

namespace App\Providers;

use App\Contracts\Visa\VisaLookupProvider;
use App\Services\Visa\Providers\MockVisaLookupProvider;
use App\Services\Visa\Providers\SaudiMofaVisaProvider;
use App\Services\Visa\Support\SaudiMofaProviderSignature;
use App\Services\Visa\Support\SaudiMofaResultParser;
use App\Services\Visa\Support\VisaHostAllowlist;
use App\Services\Visa\Support\VisaRedactor;
use App\Services\Visa\Transport\FixtureVisaHttpTransport;
use App\Services\Visa\Transport\LiveVisaHttpTransport;
use App\Services\Visa\Transport\VisaHttpTransport;
use App\Services\Visa\VisaExportService;
use App\Services\Visa\VisaLookupService;
use App\Services\Visa\VisaLookupSessionStore;
use App\Services\Visa\VisaPolicyGate;
use Illuminate\Support\ServiceProvider;

final class VisaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VisaPolicyGate::class);
        $this->app->singleton(VisaLookupSessionStore::class);
        $this->app->singleton(VisaRedactor::class);
        $this->app->singleton(SaudiMofaProviderSignature::class);
        $this->app->singleton(SaudiMofaResultParser::class);

        $this->app->singleton(VisaHostAllowlist::class, function () {
            return new VisaHostAllowlist(
                allowedHosts: (array) config('visa.saudi_mofa.allowed_hosts', ['visa.mofa.gov.sa']),
                allowedPathPrefixes: (array) config('visa.saudi_mofa.allowed_path_prefixes', []),
                maxBodyBytes: (int) config('visa.max_provider_body_bytes', 2_000_000),
            );
        });

        $this->app->singleton(VisaHttpTransport::class, function ($app) {
            $mode = (string) config('visa.saudi_mofa.transport', 'fixture');
            if ($mode === 'live') {
                return $app->make(LiveVisaHttpTransport::class);
            }

            return new FixtureVisaHttpTransport(
                $app->make(VisaHostAllowlist::class),
                base_path('tests/Fixtures/Visa/mofa'),
            );
        });

        $this->app->singleton(VisaLookupProvider::class, function ($app) {
            $default = (string) config('visa.default_provider', 'mock');
            if ($default === 'saudi_mofa') {
                return $app->make(SaudiMofaVisaProvider::class);
            }

            return $app->make(MockVisaLookupProvider::class);
        });

        $this->app->singleton(VisaLookupService::class);
        $this->app->singleton(VisaExportService::class);
    }
}
