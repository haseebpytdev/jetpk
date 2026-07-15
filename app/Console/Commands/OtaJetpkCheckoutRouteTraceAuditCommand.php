<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\JetpkCheckoutBodyBrandAudit;
use App\Support\Audits\JetpkCheckoutShellAudit;
use App\Support\Audits\JetpkErrorLayoutAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * Read-only JetPK checkout route/view trace audit (8F).
 */
class OtaJetpkCheckoutRouteTraceAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-checkout-route-trace-audit {--client=jetpk : Client slug}';

    protected $description = 'Read-only JetPK fare-select → passenger route/view trace audit';

    public function handle(
        RuntimeViewResolver $resolver,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY JetPK checkout route trace audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);
        if ($profile !== null) {
            $clientContext->set($profile);
        }

        $failCount = 0;
        $passengersView = $resolver->view('frontend.booking.passenger-details', 'frontend', $profile);
        $bodyAudit = app(JetpkCheckoutBodyBrandAudit::class)->run();
        $bodyBrand = $bodyAudit['pages']['checkout_passenger']['body_brand'] ?? 'unknown';

        $bookingController = (string) file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));
        $hasUnguardedPassengersRedirect = (bool) preg_match(
            "/(?<!clientRedirect\\(\\)->)redirect\s*\(\s*\)->route\s*\(\s*['\"]booking\.passengers['\"]/",
            $bookingController,
        );
        $hasUnguardedPassengersRoute = (bool) preg_match(
            "/(?<!client_safe_route\\()(?<!passengersUrl\\()(?<!clientRedirect\\(\\)->)route\s*\(\s*['\"]booking\.passengers['\"]/",
            $bookingController,
        );
        $hasUnguardedResultsRedirect = (bool) preg_match(
            "/(?<!clientRedirect\\(\\)->)redirect\s*\(\s*\)->route\s*\(\s*['\"]flights\.results['\"]/",
            $bookingController,
        );
        $usesCheckoutContextResolver = str_contains($bookingController, 'ClientCheckoutContextResolver')
            && str_contains($bookingController, 'passengersUrl');
        $usesClientRedirect = str_contains($bookingController, 'function clientRedirect()');

        $shellAudit = app(JetpkCheckoutShellAudit::class)->run();
        $errorLayoutAudit = app(JetpkErrorLayoutAudit::class)->run();

        $checks = [
            ['booking.passengers route registered', Route::has('booking.passengers')],
            ['client.parity.booking.passengers GET', Route::has('client.parity.booking.passengers')],
            ['client.parity.booking.passengers.store POST', Route::has('client.parity.booking.passengers.store')],
            ['client.parity.login.store POST', Route::has('client.parity.login.store')],
            ['passengers view is JetPK theme', str_contains($passengersView, 'themes.frontend.jetpakistan')],
            ['checkout body brand jetpk', $bodyBrand === 'jetpk'],
            ['BookingController logs jetpk checkout trace', str_contains($bookingController, 'jetpk_checkout_passengers_render')],
            ['ClientCheckoutContextResolver present', class_exists(\App\Support\Client\ClientCheckoutContextResolver::class)],
            ['redirectToBookingPassengers uses client resolver', $usesCheckoutContextResolver && ! $hasUnguardedPassengersRedirect],
            ['BookingController uses clientRedirect helper', $usesClientRedirect],
            ['no unguarded redirect(flights.results) in BookingController', ! $hasUnguardedResultsRedirect],
            ['no unguarded route(booking.passengers) in BookingController', ! $hasUnguardedPassengersRoute],
            ['checkout progress-bar partial exists', $shellAudit['progress_partial_exists']],
            ['checkout shells use progress-bar @include', $shellAudit['shells_use_include']],
            ['checkout shells avoid invalid x-component syntax', $shellAudit['shells_avoid_invalid_component']],
            ['checkout progress-bar renders', $shellAudit['progress_renders']],
            ['JetPK 500 error single layout chrome', $errorLayoutAudit['fail_count'] === 0],
        ];

        foreach ($checks as [$label, $ok]) {
            $this->line(sprintf('%s: %s', $label, $ok ? 'yes' : 'NO'));
            if (! $ok) {
                $failCount++;
            }
        }

        foreach (array_merge($shellAudit['issues'], $errorLayoutAudit['issues']) as $issue) {
            $this->warn('  - '.$issue);
        }

        $this->newLine();
        $this->line('expected_passenger_path=/'.$slug.'/booking/passengers');
        $this->line('expected_view='.$passengersView);
        $this->line('checkout_body_brand='.$bodyBrand);
        $this->line('fail_count='.$failCount);

        if ($failCount > 0) {
            $this->error('JetPK checkout route trace audit failed.');

            return self::FAILURE;
        }

        $this->info('JetPK checkout route trace audit passed.');

        return self::SUCCESS;
    }
}
