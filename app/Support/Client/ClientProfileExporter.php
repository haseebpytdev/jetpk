<?php

namespace App\Support\Client;

use App\Services\Client\ClientProfileResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Exports live deployment metadata into clients/{slug}/ and public/client-assets/{slug}/.
 *
 * Prefers client_profiles DB when present; falls back to config/ota_client.php.
 * Never writes secrets.
 */
final class ClientProfileExporter
{
    /**
     * @var list<string>
     */
    private const EXPORT_MARKERS = [
        'client.json',
        'branding.json',
        'modules.json',
        'deployment.json',
    ];

    /**
     * @var list<string>
     */
    private const SECRET_ENV_KEYS = [
        'APP_KEY',
        'DB_PASSWORD',
        'DB_USERNAME',
        'DB_DATABASE',
        'DB_HOST',
        'MAIL_PASSWORD',
        'MAIL_USERNAME',
        'MAIL_HOST',
        'REDIS_PASSWORD',
        'TURNSTILE_SECRET_KEY',
        'TURNSTILE_SITE_KEY',
        'DUFFEL_ACCESS_TOKEN',
        'SABRE_CLIENT_ID',
        'SABRE_CLIENT_SECRET',
        'SABRE_PASSWORD',
        'SABRE_USERNAME',
        'ALHAIDER_API_KEY',
        'ALHAIDER_API_SECRET',
    ];

    /**
     * @var list<string>
     */
    private const ASSET_SUBDIRS = [
        'logo',
        'banners',
        'favicon',
        'uploads',
    ];

    public function __construct(
        private readonly ?string $clientsRoot = null,
        private readonly ?string $publicRoot = null,
        private readonly ?ClientProfileResolver $resolver = null,
        private readonly ?ClientProfileConfigReader $configReader = null,
    ) {}

    /**
     * @return array{slug: string, client_dir: string, assets_dir: string, files: list<string>}
     */
    public function export(
        ?string $slug = null,
        bool $fromDb = false,
        bool $includeAssets = false,
        bool $force = false,
    ): array {
        $slug = $this->resolveSlug($slug);
        $payload = $this->buildPayload($slug, $fromDb);
        $assetProfile = (string) $payload['asset_profile'];
        $clientDir = $this->clientsDirectory().DIRECTORY_SEPARATOR.$slug;
        $assetsDir = $this->publicClientAssetsRoot().DIRECTORY_SEPARATOR.$assetProfile;

        if (! $force && $this->exportAlreadyExists($clientDir)) {
            throw new RuntimeException(
                "Client profile already exists at clients/{$slug}/. Pass --force to overwrite."
            );
        }

        $this->assertPayloadHasNoSecrets($payload);

        File::ensureDirectoryExists($clientDir);

        $writtenFiles = [];
        foreach ($payload['json_files'] as $filename => $data) {
            $path = $clientDir.DIRECTORY_SEPARATOR.$filename;
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (! is_string($encoded)) {
                throw new RuntimeException("Failed to encode {$filename} for export.");
            }
            File::put($path, $encoded.PHP_EOL);
            $writtenFiles[] = $path;
        }

        $envExample = $this->buildEnvProductionExample($payload);
        $this->assertContentHasNoSecrets($envExample);
        $envPath = $clientDir.DIRECTORY_SEPARATOR.'env.production.example';
        File::put($envPath, $envExample);
        $writtenFiles[] = $envPath;

        $notesPath = $clientDir.DIRECTORY_SEPARATOR.'notes.md';
        File::put($notesPath, $this->buildNotesMarkdown($slug, $payload));
        $writtenFiles[] = $notesPath;

        foreach (self::ASSET_SUBDIRS as $subdir) {
            File::ensureDirectoryExists($assetsDir.DIRECTORY_SEPARATOR.$subdir);
        }

        if ($includeAssets) {
            $this->copyBrandingAssets($payload, $assetsDir, $assetProfile);
        }

        return [
            'slug' => $slug,
            'client_dir' => $clientDir,
            'assets_dir' => $assetsDir,
            'files' => $writtenFiles,
        ];
    }

    private function resolveSlug(?string $slug): string
    {
        $slug = trim((string) ($slug ?? ''));
        if ($slug === '') {
            $slug = ClientProfile::slug();
        }

        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new RuntimeException(
                'Provide a valid client slug argument or set OTA_CLIENT_SLUG in the environment.'
            );
        }

        return $slug;
    }

    private function exportAlreadyExists(string $clientDir): bool
    {
        if (! is_dir($clientDir)) {
            return false;
        }

        foreach (self::EXPORT_MARKERS as $marker) {
            if (is_file($clientDir.DIRECTORY_SEPARATOR.$marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     slug: string,
     *     asset_profile: string,
     *     company_name: string,
     *     domain: string,
     *     active_theme: string,
     *     environment: string,
     *     default_locale: string,
     *     modules: array<string, bool>,
     *     storage_assets: array<string, string|null>,
     *     json_files: array<string, array<string, mixed>>
     * }
     */
    private function buildPayload(string $slug, bool $fromDb): array
    {
        $resolver = $this->resolver();
        $dbProfile = $resolver->resolveBySlug($slug);

        if ($dbProfile !== null) {
            return $this->buildPayloadFromRuntimeConfig($resolver->toRuntimeConfig($dbProfile));
        }

        return $this->buildPayloadFromConfig($slug, $fromDb);
    }

    /**
     * @param  array<string, mixed>  $runtime
     * @return array<string, mixed>
     */
    private function buildPayloadFromRuntimeConfig(array $runtime): array
    {
        $slug = (string) $runtime['slug'];
        $assetProfile = (string) $runtime['asset_profile'];
        $branding = is_array($runtime['branding'] ?? null) ? $runtime['branding'] : [];
        $modules = is_array($runtime['modules'] ?? null) ? $runtime['modules'] : [];
        $modulesJson = $this->reader()->normalizeModules($modules);

        $companyName = (string) ($branding['company_name'] ?? $runtime['name'] ?? 'Travel');
        $domain = (string) ($runtime['domain'] ?? $branding['domain'] ?? 'example.com');

        $clientJson = [
            'client_name' => $companyName,
            'client_slug' => $slug,
            'domain' => $domain,
            'environment' => (string) ($runtime['environment'] ?? 'production'),
            'active_theme' => (string) ($runtime['theme'] ?? 'v1-classic'),
            'active_public_asset_profile' => $assetProfile,
            'default_locale' => (string) ($runtime['default_locale'] ?? 'en'),
            'timezone' => (string) ($runtime['timezone'] ?? 'Asia/Karachi'),
            'currency' => (string) ($runtime['currency'] ?? 'PKR'),
        ];

        $brandingJson = [
            'logo_path' => (string) ($branding['logo_path'] ?? 'logo/logo.svg'),
            'favicon_path' => (string) ($branding['favicon_path'] ?? 'favicon/favicon.ico'),
            'primary_color' => (string) ($branding['primary_color'] ?? '#0c4a6e'),
            'secondary_color' => (string) ($branding['secondary_color'] ?? '#0ea5e9'),
            'accent_color' => (string) ($branding['accent_color'] ?? '#f59e0b'),
            'company_name' => $companyName,
            'phone' => (string) ($branding['phone'] ?? ''),
            'email' => (string) ($branding['email'] ?? ''),
            'address' => (string) ($branding['address'] ?? ''),
            'footer_text' => (string) ($branding['footer_text'] ?? ''),
        ];

        $deploymentJson = $this->buildDeploymentJson($slug, $domain, $assetProfile);

        return [
            'slug' => $slug,
            'asset_profile' => $assetProfile,
            'company_name' => $companyName,
            'domain' => $domain,
            'active_theme' => (string) ($runtime['theme'] ?? 'v1-classic'),
            'environment' => (string) ($runtime['environment'] ?? 'production'),
            'default_locale' => (string) ($runtime['default_locale'] ?? 'en'),
            'modules' => $modulesJson,
            'storage_assets' => [
                'logo_path' => $branding['storage_logo_path'] ?? null,
                'favicon_path' => $branding['storage_favicon_path'] ?? null,
                'hero_image_path' => $branding['storage_hero_image_path'] ?? null,
            ],
            'json_files' => [
                'client.json' => $clientJson,
                'branding.json' => $brandingJson,
                'modules.json' => $modulesJson,
                'deployment.json' => $deploymentJson,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadFromConfig(string $slug, bool $fromDb): array
    {
        $reader = $this->reader();
        $agencyBranding = $fromDb ? $reader->loadAgencyBranding() : null;
        $branding = $reader->brandingFromConfig($agencyBranding);
        $modulesJson = $reader->modulesFromConfig();
        $assetProfile = ClientProfile::assetProfile();
        if ($assetProfile === '') {
            $assetProfile = $slug;
        }

        $companyName = $branding['company_name'];
        $domain = $branding['domain'];

        $clientJson = [
            'client_name' => $companyName,
            'client_slug' => $slug,
            'domain' => $domain,
            'environment' => (string) config('app.env', 'production'),
            'active_theme' => ClientProfile::theme(),
            'active_public_asset_profile' => $assetProfile,
            'default_locale' => (string) config('app.locale', 'en'),
            'timezone' => $branding['timezone'],
            'currency' => $branding['currency'],
        ];

        $brandingJson = [
            'logo_path' => $branding['logo_path'],
            'favicon_path' => $branding['favicon_path'],
            'primary_color' => $branding['primary_color'],
            'secondary_color' => $branding['secondary_color'],
            'accent_color' => $branding['accent_color'],
            'company_name' => $companyName,
            'phone' => $branding['phone'],
            'email' => $branding['email'],
            'address' => $branding['address'],
            'footer_text' => $branding['footer_text'],
        ];

        $deploymentJson = $this->buildDeploymentJson($slug, $domain, $assetProfile);

        return [
            'slug' => $slug,
            'asset_profile' => $assetProfile,
            'company_name' => $companyName,
            'domain' => $domain,
            'active_theme' => ClientProfile::theme(),
            'environment' => (string) config('app.env', 'production'),
            'default_locale' => (string) config('app.locale', 'en'),
            'modules' => $modulesJson,
            'storage_assets' => [
                'logo_path' => $branding['storage_logo_path'] ?? null,
                'favicon_path' => $branding['storage_favicon_path'] ?? null,
                'hero_image_path' => $branding['storage_hero_image_path'] ?? null,
            ],
            'json_files' => [
                'client.json' => $clientJson,
                'branding.json' => $brandingJson,
                'modules.json' => $modulesJson,
                'deployment.json' => $deploymentJson,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeploymentJson(string $slug, string $domain, string $assetProfile): array
    {
        return [
            'hosting_panel' => 'hostinger',
            'ssh_host' => $domain,
            'ssh_port' => 22,
            'ssh_user' => 'REPLACE_SSH_USER',
            'auth_type' => 'password',
            'app_path' => '/home/REPLACE_USER/domains/'.$domain.'/laravel',
            'public_html_path' => '/home/REPLACE_USER/domains/'.$domain.'/public_html',
            'public_assets_path' => '/home/REPLACE_USER/domains/'.$domain.'/public_html/client-assets/'.$assetProfile,
            'backup_path' => '/home/REPLACE_USER/backups/'.$slug,
            'deploy_strategy' => 'sftp_single_file',
            'last_deployed_at' => null,
            'last_deployed_by' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildEnvProductionExample(array $payload): string
    {
        $templatePath = $this->clientsDirectory().DIRECTORY_SEPARATOR.'_template'.DIRECTORY_SEPARATOR.'env.production.example';
        if (! is_file($templatePath)) {
            throw new RuntimeException('Missing clients/_template/env.production.example.');
        }

        $content = (string) file_get_contents($templatePath);
        $companyName = (string) $payload['company_name'];
        $domain = (string) $payload['domain'];
        $slug = (string) $payload['slug'];
        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
        $activeTheme = (string) ($payload['active_theme'] ?? ClientProfile::theme());
        $environment = (string) ($payload['environment'] ?? config('app.env', 'production'));
        $defaultLocale = (string) ($payload['default_locale'] ?? config('app.locale', 'en'));

        $replacements = [
            'REPLACE_CLIENT_NAME' => $companyName,
            'replace.example.com' => $domain,
            'replace-client-slug' => $slug,
            'REPLACE_CLIENT_SLUG' => $slug,
            'REPLACE_DOMAIN' => $domain,
            'REPLACE_USER' => 'REPLACE_USER',
        ];

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        $content = preg_replace('/^APP_NAME=.*$/m', 'APP_NAME="'.$this->escapeEnvValue($companyName).'"', $content) ?? $content;
        $content = preg_replace('/^APP_URL=.*$/m', 'APP_URL=https://'.$domain, $content) ?? $content;
        $content = preg_replace('/^APP_ENV=.*$/m', 'APP_ENV='.$environment, $content) ?? $content;
        $content = preg_replace('/^APP_LOCALE=.*$/m', 'APP_LOCALE='.$defaultLocale, $content) ?? $content;
        $content = preg_replace('/^MAIL_FROM_NAME=.*$/m', 'MAIL_FROM_NAME="'.$this->escapeEnvValue($companyName).'"', $content) ?? $content;

        $content = preg_replace('/^OTA_CLIENT_SLUG=.*$/m', 'OTA_CLIENT_SLUG='.$slug, $content) ?? $content;
        $content = preg_replace('/^OTA_ACTIVE_THEME=.*$/m', 'OTA_ACTIVE_THEME='.$activeTheme, $content) ?? $content;
        $content = preg_replace(
            '/^OTA_PUBLIC_ASSET_PROFILE=.*$/m',
            'OTA_PUBLIC_ASSET_PROFILE='.(string) $payload['asset_profile'],
            $content,
        ) ?? $content;
        $content = preg_replace(
            '/^OTA_DEFAULT_AGENCY_SLUG=.*$/m',
            'OTA_DEFAULT_AGENCY_SLUG='.trim((string) config('ota.default_agency_slug', $slug)),
            $content,
        ) ?? $content;

        foreach ($modules as $moduleKey => $enabled) {
            $envKey = 'OTA_MODULE_'.strtoupper($moduleKey);
            $value = $enabled ? 'true' : 'false';
            $content = preg_replace('/^'.$envKey.'=.*$/m', $envKey.'='.$value, $content) ?? $content;
        }

        foreach (self::SECRET_ENV_KEYS as $secretKey) {
            $liveValue = trim((string) env($secretKey, ''));
            if ($liveValue === '') {
                continue;
            }
            $content = preg_replace(
                '/^'.preg_quote($secretKey, '/').'=.*$/m',
                $secretKey.'=',
                $content,
            ) ?? $content;
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildNotesMarkdown(string $slug, array $payload): string
    {
        $companyName = (string) $payload['company_name'];
        $domain = (string) $payload['domain'];
        $exportedAt = now()->toIso8601String();

        return <<<MD
# Client profile export — {$slug}

Auto-generated by `php artisan ota:export-client-profile` on {$exportedAt}.

## Source of truth

Dev CP DB client profile is the operational source of truth when synced.
This export captures a **local-safe snapshot** of deployment metadata (no secrets).

## Client summary

- Company: {$companyName}
- Slug: `{$slug}`
- Domain: {$domain}
- Asset profile: `{$payload['asset_profile']}`

## Deployment scope (important)

**Master testing** workspaces may store **all** client profiles under `clients/` and
`public/client-assets/` for multi-client validation.

**Client production servers** must contain **only this client's** profile folder
(`clients/{$slug}/`) and assets (`public/client-assets/{$slug}/`). Do not deploy
other clients' metadata or assets to a dedicated production host.

## Next steps

1. Review JSON files in this folder.
2. Copy `env.production.example` to the server `.env` and fill secrets manually (never commit `.env`).
3. Upload assets via SFTP if needed — see `docs/client-profile-export-sync.md`.
4. Follow `docs/new-client-deployment-checklist.md` before go-live.

## Safety

- Never commit `.env`, SSH passwords, API keys, or private keys.
- Never blind-sync entire `public_html` or `clients/` trees to production.
- Back up DB, `.env`, `storage/`, and client assets before deploy.

MD;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function copyBrandingAssets(array $payload, string $assetsDir, string $assetProfile): void
    {
        $storageAssets = is_array($payload['storage_assets'] ?? null) ? $payload['storage_assets'] : [];

        $this->copyStorageFileToAssetDir($storageAssets['logo_path'] ?? null, $assetsDir, 'logo');
        $this->copyStorageFileToAssetDir($storageAssets['favicon_path'] ?? null, $assetsDir, 'favicon');
        $this->copyStorageFileToAssetDir($storageAssets['hero_image_path'] ?? null, $assetsDir, 'banners');

        $existingAssetsDir = $this->publicClientAssetsRoot().DIRECTORY_SEPARATOR.$assetProfile;
        if ($existingAssetsDir !== $assetsDir && is_dir($existingAssetsDir)) {
            File::copyDirectory($existingAssetsDir, $assetsDir);
        }
    }

    private function copyStorageFileToAssetDir(?string $storagePath, string $assetsDir, string $subdir): void
    {
        $storagePath = trim((string) $storagePath);
        if ($storagePath === '' || ! Storage::disk('public')->exists($storagePath)) {
            return;
        }

        $basename = basename($storagePath);
        $targetDir = $assetsDir.DIRECTORY_SEPARATOR.$subdir;
        File::ensureDirectoryExists($targetDir);
        File::copy(Storage::disk('public')->path($storagePath), $targetDir.DIRECTORY_SEPARATOR.$basename);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertPayloadHasNoSecrets(array $payload): void
    {
        foreach ($payload['json_files'] as $filename => $data) {
            $encoded = json_encode($data);
            if (is_string($encoded)) {
                $this->assertContentHasNoSecrets($encoded, $filename);
            }
        }
    }

    private function assertContentHasNoSecrets(string $content, string $context = 'export'): void
    {
        $appKey = trim((string) config('app.key', ''));
        if ($appKey !== '' && str_contains($content, $appKey)) {
            throw new RuntimeException("Refusing to export: detected APP_KEY in {$context}.");
        }

        foreach (self::SECRET_ENV_KEYS as $secretKey) {
            $liveValue = trim((string) env($secretKey, ''));
            if ($liveValue !== '' && str_contains($content, $liveValue)) {
                throw new RuntimeException("Refusing to export: detected {$secretKey} value in {$context}.");
            }
        }

        if (preg_match('/-----BEGIN (?:RSA |EC )?PRIVATE KEY-----/', $content) === 1) {
            throw new RuntimeException("Refusing to export: detected private key material in {$context}.");
        }
    }

    private function escapeEnvValue(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }

    private function clientsDirectory(): string
    {
        return $this->clientsRoot ?? base_path('clients');
    }

    private function publicClientAssetsRoot(): string
    {
        $publicRoot = $this->publicRoot ?? base_path('public');

        return rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'client-assets';
    }

    private function resolver(): ClientProfileResolver
    {
        return $this->resolver ?? app(ClientProfileResolver::class);
    }

    private function reader(): ClientProfileConfigReader
    {
        return $this->configReader ?? app(ClientProfileConfigReader::class);
    }
}
