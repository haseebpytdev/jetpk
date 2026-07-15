<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\JetpkCheckoutBodyBrandAudit;
use App\Support\Audits\JetpkUrlPrefixAudit;
use App\Support\Client\ClientPublicWebrootPath;
use App\Support\FlightSearch\PublicMulticityInquiryPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/**
 * Read-only deep JetPK flow isolation audit — flight modes, UI ownership, leak scan.
 */
class OtaJetpkDeepFlowIsolationAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-deep-flow-isolation-audit {--client=jetpk : Client slug}';

    protected $description = 'Read-only deep JetPK public flight/search/booking UI isolation audit';

    public function handle(
        RuntimeViewResolver $resolver,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY deep JetPK flow isolation audit.');
        $this->line('db_write_attempted=false');
        $ctx = ClientPublicWebrootPath::auditContext();
        $this->line('configured_public_webroot='.($ctx['configured_public_webroot'] !== '' ? $ctx['configured_public_webroot'] : '(not set)'));
        $this->line('laravel_public_path='.$ctx['laravel_public_path']);
        $this->line('resolved_asset_root='.$ctx['resolved_asset_root']);
        $this->newLine();

        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);
        if ($profile !== null) {
            $clientContext->set($profile);
        }

        $failCount = 0;
        $warnCount = 0;
        $leakCount = 0;
        $blocking = [];

        $resultsPartial = (string) File::get(resource_path('views/frontend/flights/partials/results-page.blade.php'));
        $returnOptionsScript = File::exists(resource_path('views/frontend/flights/partials/return-options-script.blade.php'))
            ? (string) File::get(resource_path('views/frontend/flights/partials/return-options-script.blade.php'))
            : '';
        $flightController = (string) File::get(app_path('Http/Controllers/Frontend/FlightController.php'));
        $bookingController = (string) File::get(app_path('Http/Controllers/Frontend/BookingController.php'));
        $resultsJsRelative = 'public/themes/frontend/jetpakistan/js/results.js';
        $resultsJs = ClientPublicWebrootPath::readPublicRelative($resultsJsRelative) ?? '';

        $outboundOwnership = $this->classifySplitRendererOwnership(
            $resultsJs,
            $resultsPartial,
            $returnOptionsScript,
            'buildOutboundSplitCard',
            'buildOutboundSplitCardHtml'
        );
        $inboundOwnership = $this->classifySplitRendererOwnership(
            $resultsJs,
            $resultsPartial,
            $returnOptionsScript,
            'buildReturnSplitCard',
            'buildReturnSplitCardHtml'
        );

        if ($ctx['using_configured'] && ! ClientPublicWebrootPath::laravelPublicRelativeExists($resultsJsRelative)) {
            $this->warn('Laravel public path missing results.js, but configured live webroot is used for runtime checks.');
            $warnCount++;
        }
        $this->newLine();
        $this->info('Flight mode matrix');
        $modes = [
            ['one-way home', 'search-shell', 'cardHtml/JetPkResultCards.buildCard', 'jetpk-owned'],
            ['one-way results modify-search', 'search-shell (jetpk branch)', 'cardHtml', 'jetpk-owned'],
            ['return home', 'search-shell round_trip', 'outboundSplitCardHtml + returnSplitCardHtml', 'jetpk-owned'],
            ['return results modify-search', 'search-shell (jetpk branch)', 'outbound/return split cards', 'jetpk-owned'],
            ['return outbound selection', 'inline results-page JS', 'buildOutboundSplitCard', $outboundOwnership],
            ['return inbound selection', 'inline or return-options view', 'buildReturnSplitCard', $inboundOwnership],
            ['multi-city search', 'search-shell multi_city', 'inquiry-only cards', PublicMulticityInquiryPolicy::INQUIRY_NOTICE !== '' ? 'shared-data-only' : 'missing'],
            ['flight details', 'modal + details view', 'client_view frontend.flights.details', View::exists($resolver->view('frontend.flights.details', 'frontend', $profile)) ? 'jetpk-owned' : 'fallback-risk'],
            ['branded fare tray', 'flight-cards.js', 'buildBrandedFaresPanelHtml', 'jetpk-owned'],
            ['fare details modal', '#jpFareModal flight-cards.js', 'JetPK modal', 'jetpk-owned'],
            ['checkout passengers', 'BookingController', 'client_view passenger-details', 'jetpk-owned'],
            ['checkout review', 'BookingController', 'client_view review', 'jetpk-owned'],
            ['checkout card payment', 'BookingController', 'client_view card-payment', 'jetpk-owned'],
            ['checkout confirmation', 'BookingController', 'client_view confirmation', 'jetpk-owned'],
        ];
        $modeRows = [];
        foreach ($modes as $mode) {
            $status = $mode[3];
            if ($status === 'master-fallback-risk' || $status === 'fallback-risk') {
                $failCount++;
                $blocking[] = $mode[0].': '.$status;
            }
            $modeRows[] = [$mode[0], $mode[1], $mode[2], $status];
        }
        $this->table(['mode', 'entry', 'renderer', 'ownership'], $modeRows);
        $this->newLine();

        $this->info('Route / view matrix');
        $routes = [
            ['flights.results', 'frontend.flights.results'],
            ['flights.return-options', 'frontend.flights.return-options'],
            ['flights.details', 'frontend.flights.details'],
            ['booking.passengers', 'frontend.booking.passenger-details'],
            ['booking.review', 'frontend.booking.review'],
            ['booking.confirmation', 'frontend.booking.confirmation'],
        ];
        $routeRows = [];
        foreach ($routes as [$routeName, $logical]) {
            $resolved = $resolver->view($logical, 'frontend', $profile);
            $legacy = $resolver->legacyViewName($logical, 'frontend');
            $hasTheme = View::exists($resolver->themeViewName($logical, 'frontend', $profile));
            $status = $hasTheme ? 'jetpk-owned' : 'master-fallback-risk';
            if (! $hasTheme) {
                $failCount++;
                $leakCount++;
                $blocking[] = $routeName.' view missing theme';
            }
            $routeRows[] = [$routeName, Route::has($routeName) ? 'yes' : 'no', $resolved, $status];
        }
        $this->table(['route', 'registered', 'resolved_view', 'status'], $routeRows);
        $this->newLine();

        $this->info('Search form action scan');
        $flightsPanel = (string) File::get(resource_path('views/themes/frontend/jetpakistan/components/search/flights-panel.blade.php'));
        $homeUsesClientRoute = str_contains($flightsPanel, "client_route('flights.results')");
        $resultsUsesJetpkSearch = str_contains($resultsPartial, 'components.search.search-shell')
            && str_contains($resultsPartial, "current_client_slug() === 'jetpk'");
        $this->line('home/results search action uses client_route: '.($homeUsesClientRoute ? 'yes' : 'no'));
        $this->line('results modify-search uses JetPK search-shell: '.($resultsUsesJetpkSearch ? 'yes' : 'no'));
        if (! $homeUsesClientRoute || ! $resultsUsesJetpkSearch) {
            $failCount++;
            $blocking[] = 'search form not client-owned';
        }
        $this->newLine();

        $this->info('Controller client_route / client_view wiring');
        $checks = [
            ['FlightController results client_view', str_contains($flightController, "client_view('frontend.flights.results'")],
            ['FlightController return-options client_view', str_contains($flightController, "client_view('frontend.flights.return-options'")],
            ['FlightController return-options client_route redirect', str_contains($flightController, "client_route('flights.return-options'")],
            ['FlightController results AJAX client_route', str_contains($flightController, "client_route('flights.return-options.data'")],
            ['BookingController passengers client_view', str_contains($bookingController, "client_view('frontend.booking.passenger-details'")],
            ['BookingController review client_view', str_contains($bookingController, "client_view('frontend.booking.review'")],
            ['results-page outbound JetPK branch', str_contains($resultsPartial, 'buildOutboundSplitCard')],
            ['results-page return JetPK branch', str_contains($resultsPartial, 'buildReturnSplitCard')],
            ['results-page isJetPkResults cardHtml branch', str_contains($resultsPartial, 'JetPkResultCards.buildCard')],
        ];
        foreach ($checks as [$label, $ok]) {
            $this->line(sprintf('%s: %s', $label, $ok ? 'yes' : 'NO'));
            if (! $ok) {
                $failCount++;
                $blocking[] = $label;
            }
        }
        $this->newLine();

        $this->info('Leak scan (JetPK theme tree)');
        $forbidden = ['parwaaz', 'yoursdomain', 'tournest', 'ota-public.css', 'layouts/dashboard.css'];
        $scanRoots = [
            resource_path('views/themes/frontend/jetpakistan'),
            ClientPublicWebrootPath::path('themes/frontend/jetpakistan'),
        ];
        $hits = [];
        foreach ($scanRoots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            foreach (File::allFiles($root) as $file) {
                if (! in_array($file->getExtension(), ['php', 'blade.php', 'css', 'js'], true)) {
                    continue;
                }
                $content = strtolower((string) File::get($file->getPathname()));
                foreach ($forbidden as $pattern) {
                    if (str_contains($content, strtolower($pattern))) {
                        $hits[] = [str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()), $pattern];
                        if (! in_array($pattern, ['parwaaz'], true) || ! str_contains($content, 'hide master')) {
                            $warnCount++;
                        }
                    }
                }
            }
        }
        if ($hits === []) {
            $this->line('no forbidden runtime asset references');
        } else {
            $this->table(['file', 'pattern'], array_slice($hits, 0, 20));
            $leakCount += count($hits);
        }
        $this->newLine();

        $this->info('Checkout body brand isolation (visible headings / included partials)');
        $bodyAudit = app(JetpkCheckoutBodyBrandAudit::class)->run();
        $this->line('checkout_passenger_body_brand='.$bodyAudit['pages']['checkout_passenger']['body_brand']);
        $this->line('checkout_review_body_brand='.$bodyAudit['pages']['checkout_review']['body_brand']);
        $this->line('checkout_confirmation_body_brand='.$bodyAudit['pages']['checkout_confirmation']['body_brand']);
        $this->line('checkout_card_payment_body_brand='.$bodyAudit['pages']['checkout_card_payment']['body_brand']);
        $bodyRows = [];
        foreach ($bodyAudit['pages'] as $pageKey => $pageResult) {
            $bodyRows[] = [
                $pageKey,
                $pageResult['body_brand'],
                $pageResult['branding_override'] ?? 'n/a',
                $pageResult['status'],
                implode(', ', $pageResult['forbidden_hits']) ?: 'none',
            ];
            if ($pageResult['status'] !== 'jetpk-owned') {
                $failCount++;
                $leakCount++;
                $blocking[] = $pageKey.': '.$pageResult['status'];
            }
        }
        $this->table(['page', 'body_brand', 'branding_override', 'status', 'forbidden_hits'], $bodyRows);
        $this->newLine();

        $this->info('URL prefix isolation (auth/results/checkout)');
        $prefixAudit = app(JetpkUrlPrefixAudit::class)->run();
        $this->line('auth_login_form_action='.$prefixAudit['auth_login_form_action']);
        $this->line('results_runtime_urls='.$prefixAudit['results_runtime_urls']);
        $this->line('checkout_return_path='.$prefixAudit['checkout_return_path']);
        if ($prefixAudit['fail_count'] > 0) {
            $failCount += $prefixAudit['fail_count'];
            $leakCount += $prefixAudit['fail_count'];
            foreach ($prefixAudit['issues'] as $issue) {
                $blocking[] = $issue;
            }
        }
        $unguardedResultsRedirect = (bool) preg_match(
            "/(?<!clientRedirect\\(\\)->)redirect\s*\(\s*\)->route\s*\(\s*['\"]flights\.results['\"]/",
            $bookingController,
        );
        $usesClientRedirect = str_contains($bookingController, 'function clientRedirect()');
        $this->line('booking_client_redirect='.($usesClientRedirect ? 'yes' : 'no'));
        $this->line('unguarded_flights_results_redirect='.($unguardedResultsRedirect ? 'yes' : 'no'));
        $this->line('checkout_fallback_url_status='.((! $unguardedResultsRedirect && $usesClientRedirect) ? 'pass' : 'fail'));
        $this->line('fare_revalidation_failure_url_status='.((! $unguardedResultsRedirect && $usesClientRedirect) ? 'pass' : 'fail'));
        if ($unguardedResultsRedirect || ! $usesClientRedirect) {
            $failCount++;
            $leakCount++;
            $blocking[] = 'BookingController still has unguarded flights.results fallback redirects';
        }
        $this->newLine();

        $features = config('client_features.clients.'.$slug.'.features', []);
        $fallbackAllowed = (bool) config('client_features.clients.'.$slug.'.fallback_ui_allowed', true);
        $this->info('Feature readiness ('.$slug.')');
        $this->line('fallback_ui_allowed='.($fallbackAllowed ? 'true' : 'false'));
        foreach ($features as $key => $enabled) {
            $this->line(sprintf('  %s=%s', $key, $enabled ? 'ready' : 'off'));
        }
        $multiCitySafe = ($features['multi_city_inquiry_only'] ?? false) && ! ($features['multi_city_checkout'] ?? true);
        $this->line('multi_city_behavior='.($multiCitySafe ? 'inquiry-only-safe' : 'review-needed'));
        if (! $multiCitySafe) {
            $warnCount++;
        }
        $this->newLine();

        $canResume7k = $failCount === 0 && $fallbackAllowed === false;
        $this->line(sprintf('leak_count=%d fail_count=%d warning_count=%d', $leakCount, $failCount, $warnCount));
        $this->line('can_resume_7K='.($canResume7k ? 'yes' : 'no'));
        if ($blocking !== []) {
            $this->warn('blocking_items:');
            foreach ($blocking as $item) {
                $this->line('  - '.$item);
            }
        }

        if ($failCount > 0) {
            $this->error('Deep JetPK flow isolation audit failed.');

            return self::FAILURE;
        }

        $this->info('Deep JetPK flow isolation audit passed.');

        return self::SUCCESS;
    }

    private function classifySplitRendererOwnership(
        string $resultsJs,
        string $resultsPartial,
        string $returnOptionsScript,
        string $jetpkBuilderMethod,
        string $masterCardMethod,
    ): string {
        $hasJsBuilder = $resultsJs !== ''
            && str_contains($resultsJs, $jetpkBuilderMethod)
            && str_contains($resultsJs, 'JetPkResultCards');
        $hasBladeBranch = str_contains($resultsPartial, 'JetPkResultCards.'.$jetpkBuilderMethod)
            || str_contains($returnOptionsScript, 'JetPkResultCards.'.$jetpkBuilderMethod);

        if ($hasJsBuilder && $hasBladeBranch) {
            return 'jetpk-owned';
        }

        $masterCardRendered = str_contains($resultsPartial, 'OtaReturnSplitCards.'.$masterCardMethod)
            || str_contains($returnOptionsScript, 'OtaReturnSplitCards.'.$masterCardMethod);
        $sharedDataOnly = str_contains($resultsPartial, 'OtaReturnSplitCards.normalizeOptionForBrandedFares')
            || str_contains($resultsPartial, 'OtaReturnSplitCards.buildOutboundSummaryHtml')
            || str_contains($returnOptionsScript, 'OtaReturnSplitCards.normalizeOptionForBrandedFares')
            || str_contains($returnOptionsScript, 'OtaReturnSplitCards.buildOutboundSummaryHtml');

        if ($hasBladeBranch && $sharedDataOnly && ! $masterCardRendered) {
            return 'shared-data-only';
        }

        return 'master-fallback-risk';
    }
}
