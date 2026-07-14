<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\JetpkUrlPrefixAudit;
use App\Support\Client\ClientPublicWebrootPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Read-only JetPK flight result/search flow leak audit.
 */
class OtaJetpkResultFlowLeakAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-result-flow-leak-audit {--client=jetpk : Client slug for theme resolution}';

    protected $description = 'Read-only JetPK result/search flow audit — component parity and Master fallback detection';

    public function handle(
        RuntimeViewResolver $resolver,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY JetPK result flow leak audit.');
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

        $homeSearchPartial = resource_path('views/themes/frontend/jetpakistan/sections/hero.blade.php');
        $homeUsesJetpkSearch = File::exists($homeSearchPartial)
            && str_contains((string) File::get($homeSearchPartial), 'components.search.search-shell');
        $resultsPartial = resource_path('views/frontend/flights/partials/results-page.blade.php');
        $resultsContent = File::exists($resultsPartial) ? (string) File::get($resultsPartial) : '';
        $resultsUsesJetpkSearch = str_contains($resultsContent, 'components.search.search-shell')
            && str_contains($resultsContent, "current_client_slug() === 'jetpk'");
        $resultsUsesMasterSearchOnly = str_contains($resultsContent, 'ota-hero-flight-search')
            && ! $resultsUsesJetpkSearch;

        $this->info('Search form component matrix');
        $this->table(['page', 'component', 'status'], [
            ['home', 'themes/.../components/search/search-shell', $homeUsesJetpkSearch ? 'jetpk-owned' : 'fallback-risk'],
            ['results modify-search', 'search-shell (jetpk branch)', $resultsUsesJetpkSearch ? 'jetpk-owned' : 'fallback-risk'],
        ]);
        if (! $homeUsesJetpkSearch || ! $resultsUsesJetpkSearch) {
            $failCount++;
            $leakCount++;
        }
        $this->newLine();

        $views = [
            ['label' => 'one-way results', 'logical' => 'frontend.flights.results'],
            ['label' => 'return options', 'logical' => 'frontend.flights.return-options'],
            ['label' => 'flight details', 'logical' => 'frontend.flights.details'],
        ];
        $this->info('Result view resolution');
        $rows = [];
        foreach ($views as $view) {
            $theme = $resolver->themeViewName($view['logical'], 'frontend', $profile);
            $legacy = $resolver->legacyViewName($view['logical'], 'frontend');
            $resolved = $resolver->view($view['logical'], 'frontend', $profile);
            $hasTheme = View::exists($theme);
            $status = $hasTheme ? 'jetpk-owned' : 'fallback-risk';
            if (! $hasTheme) {
                $failCount++;
                $leakCount++;
            }
            $rows[] = [$view['label'], $status, $resolved, $hasTheme ? 'yes' : 'no', $resolved === $legacy ? 'legacy' : 'theme'];
        }
        $this->table(['page', 'status', 'resolved_view', 'theme_on_disk', 'resolver'], $rows);
        $this->newLine();

        $flightController = (string) File::get(app_path('Http/Controllers/Frontend/FlightController.php'));
        $usesClientViewReturn = str_contains($flightController, "client_view('frontend.flights.return-options'");
        $usesClientRouteResults = str_contains($flightController, "client_route('flights.results");
        $this->info('Controller wiring');
        $this->line(sprintf('return_options_client_view=%s', $usesClientViewReturn ? 'yes' : 'no'));
        $this->line(sprintf('results_client_route=%s', $usesClientRouteResults ? 'yes' : 'no'));
        if (! $usesClientViewReturn) {
            $failCount++;
            $leakCount++;
        }
        $this->newLine();

        $this->info('Result card render path');
        $resultsJsRelative = 'public/themes/frontend/jetpakistan/js/results.js';
        $flightCardsJsRelative = 'public/themes/frontend/jetpakistan/js/flight-cards.js';
        $resultsJsContents = ClientPublicWebrootPath::readPublicRelative($resultsJsRelative) ?? '';
        $flightCardsJsContents = ClientPublicWebrootPath::readPublicRelative($flightCardsJsRelative) ?? '';
        $hasJetpkCardBuilder = $resultsJsContents !== ''
            && str_contains($resultsJsContents, 'JetPkResultCards')
            && str_contains($resultsJsContents, 'buildCard: buildCard');
        $hasReturnSplitCard = $resultsJsContents !== ''
            && str_contains($resultsJsContents, 'buildReturnSplitCard')
            && str_contains($resultsJsContents, 'buildOutboundSplitCard');
        $hasBrandedCarousel = $flightCardsJsContents !== ''
            && str_contains($flightCardsJsContents, 'ota-branded-fares-carousel');
        $this->line(sprintf('jetpk_card_builder=%s', $hasJetpkCardBuilder ? 'yes' : 'no'));
        $this->line(sprintf('jetpk_return_split_card=%s', $hasReturnSplitCard ? 'yes' : 'no'));
        $this->line(sprintf('branded_fare_carousel=%s', $hasBrandedCarousel ? 'yes' : 'no'));
        $this->line('results_js='.ClientPublicWebrootPath::publicRelativePath($resultsJsRelative));
        $this->line('flight_cards_js='.ClientPublicWebrootPath::publicRelativePath($flightCardsJsRelative));
        if ($ctx['using_configured'] && ! ClientPublicWebrootPath::laravelPublicRelativeExists($resultsJsRelative)) {
            $this->warn('Laravel public path missing results.js, but configured live webroot is used for runtime checks.');
        }
        if (! $hasJetpkCardBuilder || ! $hasReturnSplitCard) {
            $failCount++;
        }
        $this->newLine();

        $this->info('Master fallback references in JetPK result partial');
        $masterRefs = [];
        if (str_contains($resultsContent, 'ota-hero-flight-search') && $resultsUsesJetpkSearch) {
            $masterRefs[] = ['results-page.blade.php', 'ota-hero-flight-search (master branch for non-jetpk only)'];
        }
        if ($resultsUsesMasterSearchOnly) {
            $masterRefs[] = ['results-page.blade.php', 'ota-hero-flight-search forced for jetpk'];
            $failCount++;
            $leakCount++;
        }
        if ($masterRefs === []) {
            $this->line('no jetpk-forced master search partial');
        } else {
            $this->table(['file', 'note'], $masterRefs);
        }
        $this->newLine();

        $this->info('Results runtime URL prefix');
        $prefixAudit = app(JetpkUrlPrefixAudit::class)->run();
        $this->line('results_runtime_urls='.$prefixAudit['results_runtime_urls']);
        if ($prefixAudit['results_runtime_urls'] !== 'client-prefixed') {
            $failCount++;
            $leakCount++;
        }
        $this->newLine();

        $this->line(sprintf('leak_count=%d fail_count=%d warning_count=%d', $leakCount, $failCount, $warnCount));

        if ($failCount > 0) {
            $this->error('JetPK result flow leak audit failed.');

            return self::FAILURE;
        }

        $this->info('JetPK result flow leak audit passed.');

        return self::SUCCESS;
    }
}
