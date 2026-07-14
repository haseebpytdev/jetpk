<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\JetpkCheckoutShellAudit;
use App\Support\Audits\JetpkUrlPrefixAudit;
use App\Support\Client\ClientNoFallbackGuard;
use App\Support\Client\ClientPublicWebrootPath;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Read-only client no-fallback firewall audit (8I).
 */
class OtaClientNoFallbackAuditCommand extends Command
{
    protected $signature = 'ota:client-no-fallback-audit {--client=jetpk : Client slug}';

    protected $description = 'Read-only audit — client public flows must not fall back to Master/root URLs';

    public function handle(
        ClientNoFallbackGuard $guard,
        RuntimeViewResolver $resolver,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY client no-fallback audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);
        if ($profile !== null) {
            $clientContext->set($profile);
        }

        $failCount = 0;
        $forbiddenRootFallbackCount = 0;

        $bookingController = (string) File::get(app_path('Http/Controllers/Frontend/BookingController.php'));
        $flightController = (string) File::get(app_path('Http/Controllers/Frontend/FlightController.php'));
        $resultsPartial = (string) File::get(resource_path('views/frontend/flights/partials/results-page.blade.php'));
        $passengerBody = (string) File::get(resource_path('views/frontend/booking/partials/passenger-details-body.blade.php'));
        $reviewBody = (string) File::get(resource_path('views/frontend/booking/partials/review-body.blade.php'));
        $resultsJs = ClientPublicWebrootPath::readPublicRelative('public/themes/frontend/jetpakistan/js/results.js') ?? '';

        $unguardedResultsRedirect = (bool) preg_match(
            "/(?<!clientRedirect\\(\\)->)(?<!client_safe_route\\()redirect\\s*\\(\\s*\\)->route\\s*\\(\\s*['\"]flights\\.results['\"]/",
            $bookingController,
        );
        $unguardedPassengersRoute = (bool) preg_match(
            "/(?<!clientRedirect\\(\\)->)(?<!client_safe_route\\()(?<!passengersUrl\\()route\\s*\\(\\s*['\"]booking\\.passengers['\"]/",
            $bookingController,
        );
        $unguardedResultsRouteString = str_contains($bookingController, "redirect()->route('flights.results")
            || str_contains($bookingController, 'redirect()->route("flights.results"');
        $hasClientRedirectHelper = str_contains($bookingController, 'function clientRedirect()');
        $hasNoFallbackGuard = class_exists(ClientNoFallbackGuard::class);
        $flightUsesClientRoute = str_contains($flightController, "client_route('flights.results");
        $resultsUsesSearchShell = str_contains($resultsPartial, 'components.search.search-shell')
            && str_contains($resultsPartial, "current_client_slug() === 'jetpk'");
        $passengerUsesClientUrl = str_contains($passengerBody, "client_url('/booking/passengers')");
        $reviewUsesClientUrl = str_contains($reviewBody, 'client_url(') || str_contains($reviewBody, 'client_route(');
        $resultsJsClientPrefixed = $resultsJs !== ''
            && (str_contains($resultsJs, 'clientSlug') || str_contains($resultsJs, '/jetpk/'));

        $shellAudit = app(JetpkCheckoutShellAudit::class)->run();
        $urlAudit = app(JetpkUrlPrefixAudit::class)->run();

        $renderedPages = [
            'checkout_passenger' => $resolver->view('frontend.booking.passenger-details', 'frontend', $profile),
            'checkout_review' => $resolver->view('frontend.booking.review', 'frontend', $profile),
            'flight_results' => $resolver->view('frontend.flights.results', 'frontend', $profile),
        ];
        $errorPageForbidden = [];
        foreach (['404', '500'] as $code) {
            $errorView = 'themes.frontend.jetpakistan.errors.'.$code;
            if (View::exists($errorView)) {
                $errorPageForbidden = array_merge(
                    $errorPageForbidden,
                    $guard->scanForbiddenRootUrls((string) File::get(resource_path('views/'.str_replace('.', '/', $errorView).'.blade.php')), $slug),
                );
            }
        }

        $themeScanRoots = [
            resource_path('views/themes/frontend/jetpakistan/frontend/booking'),
            resource_path('views/themes/frontend/jetpakistan/components/checkout'),
            resource_path('views/themes/frontend/jetpakistan/components/search'),
        ];
        $themeForbiddenHits = [];
        foreach ($themeScanRoots as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }
            foreach (File::allFiles($root) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $hits = $guard->scanForbiddenRootUrls((string) File::get($file->getPathname()), $slug);
                if ($hits !== []) {
                    $themeForbiddenHits[$file->getRelativePathname()] = $hits;
                    $forbiddenRootFallbackCount += count($hits);
                }
            }
        }

        $checks = [
            ['ClientNoFallbackGuard exists', $hasNoFallbackGuard],
            ['client_safe_route helper exists', function_exists('client_safe_route')],
            ['BookingController uses clientRedirect()', $hasClientRedirectHelper],
            ['BookingController has missing-session redirect', str_contains($bookingController, 'redirectMissingCheckoutSession')],
            ['no unguarded redirect(flights.results) in BookingController', ! $unguardedResultsRedirect],
            ['no unguarded route(flights.results) string in BookingController', ! $unguardedResultsRouteString],
            ['no unguarded route(booking.passengers) in BookingController', ! $unguardedPassengersRoute],
            ['FlightController select/results URLs client-safe', $flightUsesClientRoute],
            ['results search shell jetpk branch', $resultsUsesSearchShell],
            ['passenger form action client-prefixed', $passengerUsesClientUrl],
            ['review body client URLs', $reviewUsesClientUrl],
            ['results.js runtime client-prefixed', $resultsJsClientPrefixed],
            ['checkout progress shell compiles', $shellAudit['progress_renders']],
            ['checkout URL prefix audit', ($urlAudit['fail_count'] ?? 1) === 0],
            ['error pages no forbidden root URLs', $errorPageForbidden === []],
            ['jetpk theme blades no forbidden root URLs', $themeForbiddenHits === []],
        ];

        $typeSafetyOk = $this->runGuardTypeSafetyCases($guard, $slug);
        $checks[] = ['ClientNoFallbackGuard safeUrl type safety cases', $typeSafetyOk];

        $missingSessionOk = $this->runMissingSessionDispatchChecks($slug);
        $checks[] = ['root /booking/passengers missing-session no 500', $missingSessionOk['root_no_500']];
        $checks[] = ['jetpk /jetpk/booking/passengers missing-session no 500', $missingSessionOk['jetpk_no_500']];
        $checks[] = ['jetpk missing-session no root /flights/results fallback', $missingSessionOk['jetpk_no_root_fallback']];

        foreach ($checks as [$label, $ok]) {
            $this->line(sprintf('%s: %s', $label, $ok ? 'yes' : 'NO'));
            if (! $ok) {
                $failCount++;
            }
        }

        foreach ($themeForbiddenHits as $file => $hits) {
            $this->warn('  forbidden in '.$file.': '.implode(', ', $hits));
        }

        $checkoutFallbackUrlStatus = (! $unguardedResultsRedirect && ! $unguardedPassengersRoute) ? 'pass' : 'fail';
        $fareRevalidationFailureUrlStatus = $hasClientRedirectHelper && ! $unguardedResultsRedirect ? 'pass' : 'fail';
        $resultsSearchUrlStatus = $resultsUsesSearchShell ? 'pass' : 'fail';
        $authUrlStatus = ($urlAudit['auth_login_form_action'] ?? '') === 'client-prefixed' ? 'pass' : 'fail';
        $errorPageUrlStatus = $errorPageForbidden === [] ? 'pass' : 'fail';
        $canResume7K = $failCount === 0 && $forbiddenRootFallbackCount === 0 ? 'yes' : 'no';

        $this->newLine();
        $this->line('forbidden_root_fallback_count='.$forbiddenRootFallbackCount);
        $this->line('checkout_fallback_url_status='.$checkoutFallbackUrlStatus);
        $this->line('fare_revalidation_failure_url_status='.$fareRevalidationFailureUrlStatus);
        $this->line('results_search_url_status='.$resultsSearchUrlStatus);
        $this->line('auth_url_status='.$authUrlStatus);
        $this->line('error_page_url_status='.$errorPageUrlStatus);
        $this->line('can_resume_7K='.$canResume7K);
        $this->line('fail_count='.$failCount);

        if ($failCount > 0) {
            $this->error('Client no-fallback audit failed.');

            return self::FAILURE;
        }

        $this->info('Client no-fallback audit passed.');

        return self::SUCCESS;
    }

    private function runGuardTypeSafetyCases(ClientNoFallbackGuard $guard, string $slug): bool
    {
        $cases = [
            ['/flights/results', null],
            ['/flights/results', '?origin=LHE&destination=DXB'],
            ['/flights/results', 'origin=LHE&destination=DXB'],
            ['/booking/passengers', []],
        ];

        $this->info('ClientNoFallbackGuard safeUrl type safety');
        $allOk = true;
        foreach ($cases as [$path, $query]) {
            try {
                $url = $guard->safeUrl($path, $query, $slug);
                $pathOnly = parse_url($url, PHP_URL_PATH) ?? $url;
                $ok = is_string($pathOnly) && str_starts_with($pathOnly, '/'.$slug.'/');
                $this->line(sprintf('  safeUrl(%s, %s) => %s [%s]', $path, $this->queryLabel($query), $url, $ok ? 'ok' : 'FAIL'));
                if (! $ok) {
                    $allOk = false;
                }
            } catch (\Throwable $e) {
                $this->line(sprintf('  safeUrl(%s, %s) => TypeError/Exception: %s', $path, $this->queryLabel($query), $e->getMessage()));
                $allOk = false;
            }
        }
        $this->newLine();

        return $allOk;
    }

    /**
     * @return array{root_no_500: bool, jetpk_no_500: bool, jetpk_no_root_fallback: bool}
     */
    private function runMissingSessionDispatchChecks(string $clientSlug): array
    {
        $kernel = app(Kernel::class);
        $result = [
            'root_no_500' => false,
            'jetpk_no_500' => false,
            'jetpk_no_root_fallback' => false,
        ];

        $this->info('Missing-session dispatch checks');

        try {
            $rootRequest = Request::create('/booking/passengers', 'GET');
            $rootResponse = $kernel->handle($rootRequest);
            $rootStatus = $rootResponse->getStatusCode();
            $result['root_no_500'] = $rootStatus < 500;
            $this->line(sprintf('  GET /booking/passengers status=%d [%s]', $rootStatus, $result['root_no_500'] ? 'ok' : 'FAIL'));
            $kernel->terminate($rootRequest, $rootResponse);
        } catch (\Throwable $e) {
            $this->line('  GET /booking/passengers exception: '.$e->getMessage());
        }

        try {
            $jetpkRequest = Request::create('/jetpk/booking/passengers', 'GET');
            $jetpkResponse = $kernel->handle($jetpkRequest);
            $jetpkStatus = $jetpkResponse->getStatusCode();
            $location = (string) ($jetpkResponse->headers->get('Location') ?? '');
            $locationPath = parse_url($location, PHP_URL_PATH) ?? '';
            $result['jetpk_no_500'] = $jetpkStatus < 500;
            $result['jetpk_no_root_fallback'] = $locationPath === ''
                || (! str_starts_with($locationPath, '/flights/results') && ! str_starts_with($locationPath, '/booking/passengers'));
            $noDoublePrefix = ! str_contains($locationPath, '/'.$clientSlug.'/'.$clientSlug.'/')
                && ! str_contains($locationPath, '/'.$clientSlug.'/'.$clientSlug);
            $this->line(sprintf(
                '  GET /jetpk/booking/passengers status=%d location=%s [%s]',
                $jetpkStatus,
                $location !== '' ? $location : '(none)',
                ($result['jetpk_no_500'] && $result['jetpk_no_root_fallback'] && $noDoublePrefix) ? 'ok' : 'FAIL',
            ));
            if (! $noDoublePrefix) {
                $result['jetpk_no_root_fallback'] = false;
            }
            $kernel->terminate($jetpkRequest, $jetpkResponse);
        } catch (\Throwable $e) {
            $this->line('  GET /jetpk/booking/passengers exception: '.$e->getMessage());
        }

        $this->newLine();

        return $result;
    }

    private function queryLabel(mixed $query): string
    {
        if ($query === null) {
            return 'null';
        }
        if (is_array($query)) {
            return '[]';
        }
        if (is_string($query)) {
            return $query;
        }

        return get_debug_type($query);
    }
}
