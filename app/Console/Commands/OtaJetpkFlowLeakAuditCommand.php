<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Audits\JetpkCheckoutBodyBrandAudit;
use App\Support\Audits\JetpkCheckoutShellAudit;
use App\Support\Audits\JetpkErrorLayoutAudit;
use App\Support\Audits\JetpkUrlPrefixAudit;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Client\ClientPublicWebrootPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Read-only JetPK flow leak audit — checkout views, public/dashboard parity, brand/link leaks.
 */
class OtaJetpkFlowLeakAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-flow-leak-audit {--client=jetpk : Client slug for theme resolution}';

    protected $description = 'Read-only JetPK checkout/public/dashboard flow leak audit';

    /** @var list<array{logical:string,label:string}> */
    private array $checkoutViews = [
        ['logical' => 'frontend.booking.passenger-details', 'label' => 'passengers'],
        ['logical' => 'frontend.booking.review', 'label' => 'review'],
        ['logical' => 'frontend.booking.confirmation', 'label' => 'confirmation'],
        ['logical' => 'frontend.booking.card-payment', 'label' => 'card-payment'],
        ['logical' => 'frontend.booking.lookup', 'label' => 'lookup'],
    ];

    /** @var list<array{logical:string,label:string}> */
    private array $publicViews = [
        ['logical' => 'frontend.home', 'label' => 'home'],
        ['logical' => 'frontend.about', 'label' => 'about'],
        ['logical' => 'frontend.support', 'label' => 'support'],
        ['logical' => 'auth.login', 'label' => 'login'],
        ['logical' => 'auth.register', 'label' => 'register'],
        ['logical' => 'auth.forgot-password', 'label' => 'forgot-password'],
        ['logical' => 'auth.login-otp', 'label' => 'login-otp'],
        ['logical' => 'frontend.flights.results', 'label' => 'flight-results'],
        ['logical' => 'frontend.flights.details', 'label' => 'flight-details'],
        ['logical' => 'frontend.group-ticketing.search', 'label' => 'groups-search'],
        ['logical' => 'frontend.agent-registration.landing', 'label' => 'agent-register'],
    ];

    /** @var list<array{area:string,logical:string,label:string}> */
    private array $dashboardViews = [
        ['area' => 'admin', 'logical' => 'index', 'label' => 'admin dashboard'],
        ['area' => 'staff', 'logical' => 'index', 'label' => 'staff dashboard'],
        ['area' => 'agent', 'logical' => 'index', 'label' => 'agent dashboard'],
        ['area' => 'customer', 'logical' => 'dashboard', 'label' => 'customer dashboard'],
    ];

    /** @var list<string> */
    private array $forbiddenPatterns = [
        'parwaaz',
        'yoursdomain',
        'asif travels',
        'tournest',
        '/admin"',
        'route(\'login\'',
        'href="/login"',
        'href="/admin"',
    ];

    public function handle(
        RuntimeViewResolver $resolver,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY JetPK flow leak audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);
        if ($profile !== null) {
            $clientContext->set($profile);
        }

        $failCount = 0;
        $warnCount = 0;
        $leakCount = 0;

        $this->info('Checkout flow view resolution');
        $checkoutRows = [];
        foreach ($this->checkoutViews as $page) {
            $theme = $resolver->themeViewName($page['logical'], 'frontend', $profile);
            $legacy = $resolver->legacyViewName($page['logical'], 'frontend');
            $resolved = $resolver->view($page['logical'], 'frontend', $profile);
            $hasTheme = View::exists($theme);
            $status = $hasTheme ? 'jetpk-owned' : 'fallback-risk';
            if (! $hasTheme && in_array($page['logical'], [
                'frontend.booking.passenger-details',
                'frontend.booking.review',
                'frontend.booking.confirmation',
            ], true)) {
                $failCount++;
                $leakCount++;
            } elseif (! $hasTheme) {
                $warnCount++;
            }
            $checkoutRows[] = [$page['label'], $status, $resolved, $hasTheme ? 'yes' : 'no', $resolved === $legacy ? 'legacy' : 'theme'];
        }
        $this->table(['page', 'status', 'resolved_view', 'theme_on_disk', 'resolver'], $checkoutRows);
        $this->newLine();

        $this->info('Public page view resolution');
        $publicRows = [];
        foreach ($this->publicViews as $page) {
            $theme = $resolver->themeViewName($page['logical'], 'frontend', $profile);
            $legacy = $resolver->legacyViewName($page['logical'], 'frontend');
            $resolved = $resolver->view($page['logical'], 'frontend', $profile);
            $hasTheme = View::exists($theme);
            $status = $hasTheme ? 'jetpk-owned' : ($resolved === $legacy ? 'fallback-risk' : 'jetpk-better');
            if (! $hasTheme && $resolved === $legacy) {
                $warnCount++;
            }
            $publicRows[] = [$page['label'], $status, $resolved, $hasTheme ? 'yes' : 'no'];
        }
        $this->table(['page', 'status', 'resolved_view', 'theme_on_disk'], $publicRows);
        $this->newLine();

        $this->info('Dashboard entry view resolution');
        $dashRows = [];
        foreach ($this->dashboardViews as $page) {
            $theme = $resolver->themeViewName($page['logical'], $page['area'], $profile);
            $legacy = $resolver->legacyViewName($page['logical'], $page['area']);
            $resolved = $resolver->view($page['logical'], $page['area'], $profile);
            $hasTheme = View::exists($theme);
            $status = $hasTheme ? 'jetpk-owned' : 'shell-wrapped';
            if (! $hasTheme) {
                $warnCount++;
            }
            $dashRows[] = [$page['label'], $status, $resolved, $hasTheme ? 'yes' : 'no'];
        }
        $this->table(['page', 'status', 'resolved_view', 'theme_on_disk'], $dashRows);
        $this->newLine();

        $this->info('Header authenticated state');
        $headerPath = resource_path('views/themes/frontend/jetpakistan/partials/header.blade.php');
        $headerContent = File::exists($headerPath) ? (string) File::get($headerPath) : '';
        $hasGuest = str_contains($headerContent, '@guest');
        $hasAuthDropdown = str_contains($headerContent, 'account-dropdown');
        $headerStatus = ($hasGuest && $hasAuthDropdown) ? 'ok' : 'fail';
        if ($headerStatus === 'fail') {
            $failCount++;
            $leakCount++;
        }
        $this->line(sprintf('header_guest_auth=%s account_dropdown=%s status=%s', $hasGuest ? 'yes' : 'no', $hasAuthDropdown ? 'yes' : 'no', $headerStatus));
        $this->newLine();

        $this->info('Payment option view check');
        $paymentPartial = resource_path('views/frontend/booking/partials/checkout-payment-methods.blade.php');
        $paymentContent = File::exists($paymentPartial) ? (string) File::get($paymentPartial) : '';
        $hasJetpkBranch = str_contains($paymentContent, "current_client_slug() === 'jetpk'");
        $hasManual = str_contains($paymentContent, 'Manual Payment');
        $hasCard = str_contains($paymentContent, 'Pay by Card');
        $paymentStatus = ($hasJetpkBranch && $hasManual && $hasCard) ? 'ok' : 'fail';
        if ($paymentStatus === 'fail') {
            $failCount++;
        }
        $this->line(sprintf('jetpk_branch=%s manual_payment=%s pay_by_card=%s status=%s', $hasJetpkBranch ? 'yes' : 'no', $hasManual ? 'yes' : 'no', $hasCard ? 'yes' : 'no', $paymentStatus));
        $this->newLine();

        $this->info('Hardcoded Master/Parwaaz/YD link scan (JetPK theme tree)');
        $scanRoots = [
            resource_path('views/themes/frontend/jetpakistan'),
            resource_path('views/themes/admin/jetpakistan'),
            resource_path('views/themes/staff/jetpakistan'),
            resource_path('views/themes/agent/jetpakistan'),
            resource_path('views/themes/customer/jetpakistan'),
            public_path('themes/frontend/jetpakistan'),
            ClientPublicWebrootPath::path('themes/frontend/jetpakistan'),
        ];
        $scanRoots = array_values(array_unique(array_filter($scanRoots, static fn (string $root): bool => is_dir($root))));
        $scanHits = [];
        foreach ($scanRoots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            foreach (File::allFiles($root) as $file) {
                if (! in_array($file->getExtension(), ['php', 'blade.php', 'css', 'js'], true)) {
                    continue;
                }
                $contents = strtolower((string) File::get($file->getPathname()));
                foreach ($this->forbiddenPatterns as $pattern) {
                    if (str_contains($contents, strtolower($pattern))) {
                        $scanHits[] = [str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()), $pattern];
                        $leakCount++;
                    }
                }
            }
        }
        if ($scanHits === []) {
            $this->line('no forbidden brand/link patterns found');
        } else {
            $warnCount += count($scanHits);
            $this->table(['file', 'pattern'], array_slice($scanHits, 0, 25));
            if (count($scanHits) > 25) {
                $this->warn(sprintf('... and %d more hits', count($scanHits) - 25));
            }
        }
        $this->newLine();

        $this->info('Checkout controller wiring');
        $bookingController = (string) File::get(app_path('Http/Controllers/Frontend/BookingController.php'));
        $usesClientView = str_contains($bookingController, "client_view('frontend.booking.passenger-details'");
        $usesClientRedirect = str_contains($bookingController, 'function clientRedirect()');
        $unguardedResultsRedirect = (bool) preg_match(
            "/(?<!clientRedirect\\(\\)->)redirect\s*\(\s*\)->route\s*\(\s*['\"]flights\.results['\"]/",
            $bookingController,
        );
        $flightController = (string) File::get(app_path('Http/Controllers/Frontend/FlightController.php'));
        $usesClientRoute = str_contains($flightController, "client_route('booking.passengers'");
        if (! $usesClientView) {
            $failCount++;
            $leakCount++;
        }
        if (! $usesClientRedirect || $unguardedResultsRedirect) {
            $failCount++;
            $leakCount++;
        }
        if (! $usesClientRoute) {
            $warnCount++;
        }
        $this->line(sprintf(
            'booking_client_view=%s client_redirect=%s unguarded_results_redirect=%s flight_client_route=%s',
            $usesClientView ? 'yes' : 'no',
            $usesClientRedirect ? 'yes' : 'no',
            $unguardedResultsRedirect ? 'yes' : 'no',
            $usesClientRoute ? 'yes' : 'no',
        ));
        $this->line('checkout_fallback_url_status='.((! $unguardedResultsRedirect && $usesClientRedirect) ? 'pass' : 'fail'));
        $this->line('fare_revalidation_failure_url_status='.((! $unguardedResultsRedirect && $usesClientRedirect) ? 'pass' : 'fail'));
        $this->newLine();

        $this->info('Checkout body brand isolation (visible headings / included partials)');
        $bodyAudit = app(JetpkCheckoutBodyBrandAudit::class)->run();
        foreach ($bodyAudit['pages'] as $pageKey => $pageResult) {
            $metricKey = str_replace('checkout_', 'checkout_', $pageKey).'_body_brand';
            if ($pageKey === 'checkout_passenger') {
                $this->line('checkout_passenger_body_brand='.$pageResult['body_brand']);
            } elseif ($pageKey === 'checkout_review') {
                $this->line('checkout_review_body_brand='.$pageResult['body_brand']);
            } elseif ($pageKey === 'checkout_confirmation') {
                $this->line('checkout_confirmation_body_brand='.$pageResult['body_brand']);
            } elseif ($pageKey === 'checkout_card_payment') {
                $this->line('checkout_card_payment_body_brand='.$pageResult['body_brand']);
            }
            if ($pageResult['status'] !== 'jetpk-owned') {
                $failCount++;
                $leakCount++;
            }
        }
        $bodyRows = [];
        foreach ($bodyAudit['pages'] as $pageKey => $pageResult) {
            $bodyRows[] = [
                $pageKey,
                $pageResult['body_brand'],
                $pageResult['branding_override'] ?? 'n/a',
                $pageResult['status'],
                implode(', ', $pageResult['forbidden_hits']) ?: 'none',
            ];
        }
        $this->table(['page', 'body_brand', 'branding_override', 'status', 'forbidden_hits'], $bodyRows);
        $this->newLine();

        $this->info('Checkout shell render (progress-bar include/compile)');
        $shellAudit = app(JetpkCheckoutShellAudit::class)->run();
        $this->line('progress_partial_exists='.($shellAudit['progress_partial_exists'] ? 'yes' : 'no'));
        $this->line('shells_use_include='.($shellAudit['shells_use_include'] ? 'yes' : 'no'));
        $this->line('shells_avoid_invalid_component='.($shellAudit['shells_avoid_invalid_component'] ? 'yes' : 'no'));
        $this->line('progress_renders='.($shellAudit['progress_renders'] ? 'yes' : 'no'));
        if ($shellAudit['fail_count'] > 0) {
            $failCount += $shellAudit['fail_count'];
            $leakCount += $shellAudit['fail_count'];
            foreach ($shellAudit['issues'] as $issue) {
                $this->warn('  - '.$issue);
            }
        }
        $this->newLine();

        $this->info('JetPK error layout isolation');
        $errorLayoutAudit = app(JetpkErrorLayoutAudit::class)->run();
        $this->line('error_500_renders='.($errorLayoutAudit['error_500_renders'] ? 'yes' : 'no'));
        $this->line('single_header='.($errorLayoutAudit['single_header'] ? 'yes' : 'no'));
        $this->line('single_footer='.($errorLayoutAudit['single_footer'] ? 'yes' : 'no'));
        $this->line('single_error_panel='.($errorLayoutAudit['single_error_panel'] ? 'yes' : 'no'));
        $this->line('no_duplicate_brand_block='.($errorLayoutAudit['no_duplicate_brand_block'] ? 'yes' : 'no'));
        if ($errorLayoutAudit['fail_count'] > 0) {
            $failCount += $errorLayoutAudit['fail_count'];
            $leakCount += $errorLayoutAudit['fail_count'];
            foreach ($errorLayoutAudit['issues'] as $issue) {
                $this->warn('  - '.$issue);
            }
        }
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
                $this->warn('  - '.$issue);
            }
        }
        $this->newLine();

        $this->line(sprintf('leak_count=%d fail_count=%d warning_count=%d', $leakCount, $failCount, $warnCount));

        if ($failCount > 0) {
            $this->error('JetPK flow leak audit failed.');

            return self::FAILURE;
        }

        $this->info('JetPK flow leak audit passed.');

        return self::SUCCESS;
    }
}
