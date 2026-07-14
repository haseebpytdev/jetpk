<?php

namespace App\Console\Commands;

use App\Models\ClientProfile;
use App\Services\Client\ClientBrandingResolver;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeThemeManager;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Client\ClientPublicWebrootPath;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class OtaClientPreviewRuntimeStatusCommand extends Command
{
    protected $signature = 'ota:client-preview-runtime-status
                            {--client=jetpk : Client slug to inspect}';

    protected $description = 'JETPK-CLIENT-PREVIEW-RUNTIME-1 — read-only JetPakistan client preview runtime diagnostics';

    public function handle(
        ClientProfileResolver $profileResolver,
        RuntimeThemeManager $themeManager,
    ): int {
        $clientSlug = trim((string) $this->option('client'));

        if ($clientSlug === '') {
            $this->error('Option --client must not be empty.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY client preview runtime status.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $webrootPath = ClientPublicWebrootPath::resolve();
        $this->line('public webroot path: '.$webrootPath);
        $this->line('public webroot exists: '.(is_dir($webrootPath) ? 'yes' : 'no'));
        if (ClientPublicWebrootPath::configuredPath() !== '') {
            $this->line('public webroot configured: '.ClientPublicWebrootPath::configuredPath());
            $this->line('public webroot using configured path: '.(ClientPublicWebrootPath::usingConfiguredPath() ? 'yes' : 'no (fallback to public_path())'));
        }
        $this->newLine();

        $profile = ClientProfile::query()
            ->where('slug', $clientSlug)
            ->with('branding')
            ->first();

        $activeProfile = $profileResolver->resolveBySlug($clientSlug);
        $themeSummary = $profile !== null ? $themeManager->summary($profile) : null;

        $rows = [
            ['client profile found', $profile !== null ? 'yes' : 'no', $profile !== null ? 'OK' : 'FAIL'],
            ['branding row found', $profile?->branding !== null ? 'yes' : 'no', $profile?->branding !== null ? 'OK' : 'WARN'],
            ['asset profile', $profile?->asset_profile ?? '(missing)', $profile?->asset_profile ? 'OK' : 'FAIL'],
            ['active frontend theme', $profile?->active_frontend_theme ?? '(missing)', $profile?->active_frontend_theme ? 'OK' : 'FAIL'],
            ['active admin theme', $profile?->active_admin_theme ?? '(missing)', $profile?->active_admin_theme ? 'OK' : 'WARN'],
            ['preview path', $profile?->preview_path ?? '(missing)', ($profile?->preview_path ?? '') === '/'.$clientSlug ? 'OK' : 'WARN'],
            ['preview route client.preview.root', Route::has('client.preview.root') ? 'registered' : 'missing', Route::has('client.preview.root') ? 'OK' : 'FAIL'],
            ['parity route client.parity.home.alias', Route::has('client.parity.home.alias') ? 'registered' : 'missing', Route::has('client.parity.home.alias') ? 'OK' : 'FAIL'],
            ['is preview resolvable', $activeProfile !== null ? 'yes' : 'no', $activeProfile !== null ? 'OK' : 'FAIL'],
            ['root route home unaffected', route('home', [], false), route('home', [], false) === '/' ? 'OK' : 'FAIL'],
        ];

        $this->table(['check', 'value', 'status'], $rows);

        if ($profile !== null) {
            $this->newLine();
            $this->info('Branding resolver (simulated preview context)');
            app(CurrentClientContext::class)->set($profile->loadMissing(['modules', 'suppliers', 'branding']));
            $branding = app(ClientBrandingResolver::class);
            $this->line('company_name: '.$branding->companyName());
            $this->line('primary_color: '.$branding->primaryColor());
            $this->line('logo_url: '.($branding->logoUrl() ?? '(null)'));
            $this->line('favicon_url: '.($branding->faviconUrl() ?? '(null)'));
            app(CurrentClientContext::class)->clear();
        }

        if ($themeSummary !== null) {
            $this->newLine();
            $this->info('Theme resolution');
            foreach (['frontend', 'admin'] as $area) {
                $areaSummary = $themeSummary['areas'][$area];
                $this->line(sprintf(
                    '%s: selected=%s resolved=%s on_disk=%s',
                    $area,
                    $areaSummary['selected'] ?? '(empty)',
                    $areaSummary['resolved'],
                    $areaSummary['on_disk'] ? 'yes' : 'no',
                ));
            }
            foreach ($themeSummary['warnings'] as $warning) {
                $this->warn('Theme warning: '.$warning);
            }
        }

        $this->newLine();
        $this->info('HTTP preview smoke');
        $previewRoot = $this->httpGet('/'.$clientSlug);
        $previewHome = $this->httpGet('/'.$clientSlug.'/home');
        $rootHome = $this->httpGet('/');

        $this->line('/'.$clientSlug.' → '.$previewRoot->getStatusCode());
        $this->line('/'.$clientSlug.'/home → '.$previewHome->getStatusCode());
        $this->line('/ → '.$rootHome->getStatusCode());

        $missingAssets = $this->missingAssetWarnings($profile);
        if ($missingAssets !== []) {
            $this->newLine();
            $this->warn('Missing assets:');
            foreach ($missingAssets as $warning) {
                $this->warn('  '.$warning);
            }
        }

        $failed = array_filter($rows, static fn (array $row): bool => $row[2] === 'FAIL');
        $httpOk = $previewHome->isOk() && $rootHome->isOk();

        if ($failed !== [] || ! $httpOk) {
            $this->error('Client preview runtime status: issues detected.');

            return self::FAILURE;
        }

        $this->info('Client preview runtime status: OK.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function missingAssetWarnings(?ClientProfile $profile): array
    {
        if ($profile === null) {
            return ['Client profile missing — cannot check assets.'];
        }

        $warnings = [];
        $assetProfile = trim((string) ($profile->asset_profile ?? ''));
        $branding = $profile->branding;

        if ($assetProfile !== '') {
            $relative = 'client-assets/'.$assetProfile;
            if (! ClientPublicWebrootPath::isDirectory($relative)) {
                $warnings[] = $relative.' directory missing on disk (checked: '.ClientPublicWebrootPath::path($relative).').';
            }
        }

        foreach (['logo_path' => 'logo', 'favicon_path' => 'favicon'] as $field => $label) {
            $relative = trim((string) ($branding?->{$field} ?? ''));
            if ($relative === '' || $assetProfile === '') {
                continue;
            }

            $assetRelative = 'client-assets/'.$assetProfile.'/'.$relative;
            if (! ClientPublicWebrootPath::isFile($assetRelative)) {
                $warnings[] = $label.' file missing: '.$assetRelative.' (checked: '.ClientPublicWebrootPath::path($assetRelative).').';
            }
        }

        return $warnings;
    }

    private function httpGet(string $path): \Symfony\Component\HttpFoundation\Response
    {
        $kernel = app(Kernel::class);
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
