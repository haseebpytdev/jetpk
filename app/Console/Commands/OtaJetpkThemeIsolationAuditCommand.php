<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only JetPakistan theme isolation audit — detects Master/other-client asset leakage in rendered HTML.
 */
class OtaJetpkThemeIsolationAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-theme-isolation-audit
                            {--client=jetpk : Client slug to audit}';

    protected $description = 'Read-only audit — JetPK pages must not reference Master or other-client frontend/dashboard assets';

    /** @var list<string> */
    private array $masterCssPatterns = [
        'ota-public.css',
        '/css/bootstrap',
        'adminlte',
        'tournest',
        'TourNest',
        'layouts/dashboard.css',
        'tabler.min.css',
    ];

    /** @var list<string> */
    private array $masterJsPatterns = [
        'bootstrap.min.js',
        'bootstrap.bundle',
        'adminlte',
        'tournest',
        'TourNest',
        'tabler.min.js',
    ];

    /** @var list<string> */
    private array $forbiddenBrandPatterns = [
        'parwaaz',
        'yoursdomain',
        'asif travels',
        'asifkhali@yoursdomain.com',
        'aerobilet',
        'tournest',
    ];

    /** @var list<string> */
    private array $mojibakePatterns = [
        'Â',
        'â€™',
        'Ã',
    ];

    /** @var list<string> */
    private array $brokenEntityPatterns = [
        '&nbsp;',
        '&quot;',
        '&#039;',
    ];

    /** @var list<string> */
    private array $jetpkAssetPrefixHints = [
        '/themes/frontend/jetpakistan/',
        'themes/frontend/jetpakistan',
        '/themes/admin/jetpakistan/',
        'themes/admin/jetpakistan',
    ];

    /** @var list<array{path:string,label:string,auth?:bool}> */
    private array $routes = [
        ['path' => '/home', 'label' => 'home'],
        ['path' => '/about-us', 'label' => 'about'],
        ['path' => '/support', 'label' => 'support'],
        ['path' => '/lookup-booking', 'label' => 'lookup-booking'],
        ['path' => '/login', 'label' => 'login'],
        ['path' => '/register', 'label' => 'register'],
        ['path' => '/forgot-password', 'label' => 'forgot-password'],
        ['path' => '/groups/search', 'label' => 'groups-search'],
        ['path' => '/admin', 'label' => 'admin-dashboard', 'auth' => true],
        ['path' => '/admin/bookings', 'label' => 'admin-bookings', 'auth' => true],
        ['path' => '/admin/settings', 'label' => 'admin-settings', 'auth' => true],
        ['path' => '/admin/settings/branding', 'label' => 'admin-branding', 'auth' => true],
        ['path' => '/admin/settings/communications', 'label' => 'admin-comms', 'auth' => true],
        ['path' => '/admin/api-settings', 'label' => 'admin-api-settings', 'auth' => true],
        ['path' => '/admin/reports', 'label' => 'admin-reports', 'auth' => true],
        ['path' => '/admin/group-ticketing', 'label' => 'admin-group-ticketing', 'auth' => true],
        ['path' => '/staff', 'label' => 'staff-dashboard', 'auth' => true],
        ['path' => '/agent', 'label' => 'agent-dashboard', 'auth' => true],
        ['path' => '/customer', 'label' => 'customer-dashboard', 'auth' => true],
    ];

    public function handle(ClientProfileResolver $profileResolver): int
    {
        $clientSlug = trim((string) $this->option('client'));

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY JetPK theme isolation audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $profile = $profileResolver->resolveBySlug($clientSlug);
        if ($profile === null) {
            $this->error("Client profile not found for slug: {$clientSlug}");

            return self::FAILURE;
        }

        $rows = [];
        $failCount = 0;
        $warnCount = 0;

        foreach ($this->routes as $route) {
            $rows[] = $this->auditPath($clientSlug, $route['path'], $route['label'], $failCount, $warnCount, (bool) ($route['auth'] ?? false));
        }

        $this->table(['page', 'status', 'master_css', 'master_js', 'other_client', 'notes'], array_map(
            static fn (array $row): array => [
                $row['page'],
                $row['status'],
                (string) $row['master_css'],
                (string) $row['master_js'],
                (string) $row['other_client'],
                $row['notes'],
            ],
            $rows,
        ));

        $this->newLine();
        $this->line(sprintf('Summary: fail=%d warn=%d pass=%d', $failCount, $warnCount, count($rows) - $failCount - $warnCount));

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  int  $failCount
     * @param  int  $warnCount
     * @return array{page:string,status:string,master_css:int,master_js:int,other_client:int,notes:string}
     */
    private function auditPath(string $clientSlug, string $path, string $label, int &$failCount, int &$warnCount, bool $authRequired = false): array
    {
        $uri = '/'.$clientSlug.$path;
        $response = $this->dispatchGet($uri);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 500) {
            $failCount++;

            return [
                'page' => $label,
                'status' => 'FAIL',
                'master_css' => 0,
                'master_js' => 0,
                'other_client' => 0,
                'notes' => 'HTTP '.$statusCode,
            ];
        }

        if ($authRequired && in_array($statusCode, [301, 302, 303, 307, 308], true)) {
            return [
                'page' => $label,
                'status' => 'PASS',
                'master_css' => 0,
                'master_js' => 0,
                'other_client' => 0,
                'notes' => 'auth redirect (expected unauthenticated)',
            ];
        }

        $html = (string) $response->getContent();
        $masterCss = $this->countPatterns($html, $this->masterCssPatterns);
        $masterJs = $this->countPatterns($html, $this->masterJsPatterns);
        $otherClient = $this->countOtherClientAssets($html);
        $missingJetpk = $this->missingJetpkStylesheet($html, $authRequired);
        $forbiddenBrand = $this->countPatterns($html, $this->forbiddenBrandPatterns);
        $mojibake = $this->countPatterns($html, $this->mojibakePatterns);
        $brokenEntities = $this->countBrokenEntities($html);

        $notes = [];
        if ($missingJetpk) {
            $notes[] = 'no JetPK theme stylesheet';
        }
        if ($forbiddenBrand > 0) {
            $notes[] = 'forbidden brand text detected';
        }
        if ($mojibake > 0) {
            $notes[] = 'mojibake pattern detected';
        }
        if ($brokenEntities > 0) {
            $notes[] = 'broken HTML entity in output';
        }

        $status = 'PASS';
        if ($masterCss > 0 || $masterJs > 0 || $forbiddenBrand > 0 || $mojibake > 0) {
            $status = 'FAIL';
            $failCount++;
            if ($masterCss > 0 || $masterJs > 0) {
                $notes[] = 'Master asset reference detected';
            }
        } elseif ($otherClient > 0 || $missingJetpk || $brokenEntities > 0) {
            $status = 'WARN';
            $warnCount++;
            if ($otherClient > 0) {
                $notes[] = 'possible other-client asset';
            }
        }

        return [
            'page' => $label,
            'status' => $status,
            'master_css' => $masterCss,
            'master_js' => $masterJs,
            'other_client' => $otherClient,
            'notes' => $notes !== [] ? implode('; ', $notes) : 'ok',
        ];
    }

    private function dispatchGet(string $uri): Response
    {
        $kernel = app(Kernel::class);
        $request = Request::create($uri, 'GET');

        return $kernel->handle($request);
    }

    /**
     * @param  list<string>  $patterns
     */
    private function countPatterns(string $html, array $patterns): int
    {
        $count = 0;
        foreach ($patterns as $pattern) {
            $count += substr_count(strtolower($html), strtolower($pattern));
        }

        return $count;
    }

    private function countOtherClientAssets(string $html): int
    {
        $count = 0;
        if (preg_match_all('#/themes/(?:frontend|admin)/(?!jetpakistan)[a-z0-9\-]+/#i', $html, $matches)) {
            $count += count($matches[0]);
        }

        return $count;
    }

    private function missingJetpkStylesheet(string $html, bool $authRequired): bool
    {
        if ($authRequired && strlen($html) < 200) {
            return false;
        }

        foreach ($this->jetpkAssetPrefixHints as $hint) {
            if (stripos($html, $hint) !== false) {
                return false;
            }
        }

        return true;
    }

    private function countBrokenEntities(string $html): int
    {
        $count = 0;
        foreach ($this->brokenEntityPatterns as $pattern) {
            $count += substr_count($html, $pattern);
        }

        return $count;
    }
}
