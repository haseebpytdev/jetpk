<?php

namespace App\Support\Ui;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Resolves UI channel + version for the current request (site / admin / staff).
 *
 * v1 uses canonical Blade paths; v2+ overlays under ui/{channel}/{version}/...
 * with safe fallback to v1 when overlays are missing.
 */
class UiVersionResolver
{
    public const CHANNEL_SITE = 'site';

    public const CHANNEL_ADMIN = 'admin';

    public const CHANNEL_STAFF = 'staff';

    protected ?string $channel = null;

    protected bool $channelResolved = false;

    protected ?string $pathPrefixPreview = null;

    protected ?string $queryPreview = null;

    protected bool $previewNamespace = false;

    protected bool $resolved = false;

    protected string $effectiveVersion = 'v1';

    public function __construct(
        protected Request $request,
    ) {}

    public function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $channel = $this->channel();
        if ($channel === null) {
            $this->effectiveVersion = 'v1';
            $this->resolved = true;

            return;
        }

        $preview = $this->previewVersion();
        if ($preview !== null && $this->isPreviewAllowed($preview, $channel)) {
            $this->effectiveVersion = $preview;
        } else {
            $this->effectiveVersion = $this->defaultVersion($channel);
        }

        $this->resolved = true;
    }

    public function channel(): ?string
    {
        if ($this->channelResolved) {
            return $this->channel;
        }

        $this->channel = $this->detectChannelFromRequest($this->request);
        $this->channelResolved = true;

        return $this->channel;
    }

    public function defaultVersion(?string $channel = null): string
    {
        $channel = $channel ?? $this->channel();
        if ($channel === null) {
            return 'v1';
        }

        if ((bool) config('client_ui.enabled', true)
            && (bool) config('client_ui.force_v1_default_until_verified', true)) {
            $clientDefault = (string) config('client_ui.default_version', 'v1');

            return $this->normalizeVersion($clientDefault) ?? 'v1';
        }

        $configured = (string) config("ota-ui.channels.{$channel}.default", 'v1');

        return $this->normalizeVersion($configured) ?? 'v1';
    }

    public function isPreviewNamespace(): bool
    {
        return $this->previewNamespace;
    }

    public function setPreviewNamespace(bool $active): void
    {
        $this->previewNamespace = $active;
    }

    public function effectiveVersion(?string $channel = null): string
    {
        $channel = $channel ?? $this->channel();
        if ($channel === null) {
            return 'v1';
        }

        if (! $this->resolved || $channel !== $this->channel) {
            $preview = $this->previewVersionForChannel($channel);
            if ($preview !== null && $this->isPreviewAllowed($preview, $channel)) {
                return $preview;
            }

            return $this->defaultVersion($channel);
        }

        return $this->effectiveVersion;
    }

    public function previewVersion(): ?string
    {
        if ($this->channel() === null) {
            return null;
        }

        return $this->previewVersionForChannel($this->channel());
    }

    public function isPreviewActive(): bool
    {
        $channel = $this->channel();
        if ($channel === null) {
            return false;
        }

        $preview = $this->previewVersion();
        if ($preview === null) {
            return false;
        }

        return $preview !== $this->defaultVersion($channel);
    }

    public function isPreviewAllowed(string $version, ?string $channel = null): bool
    {
        $channel = $channel ?? $this->channel();
        if ($channel === null) {
            return false;
        }

        $version = $this->normalizeVersion($version);
        if ($version === null) {
            return false;
        }

        if ((bool) config('client_ui.enabled', true)) {
            if (! (bool) config('client_ui.preview_enabled', true)) {
                return false;
            }

            $clientAllowed = config('client_ui.allowed_versions', ['v1', 'v2']);
            if (! is_array($clientAllowed) || ! in_array($version, $clientAllowed, true)) {
                return false;
            }
        }

        if (! (bool) config("ota-ui.channels.{$channel}.preview_enabled", false)) {
            return false;
        }

        $active = config("ota-ui.channels.{$channel}.active_versions", []);

        return is_array($active) && in_array($version, $active, true);
    }

    public function fallbackVersion(?string $channel = null): string
    {
        $channel = $channel ?? $this->channel();
        if ($channel === null) {
            return 'v1';
        }

        $configured = (string) config("ota-ui.channels.{$channel}.fallback", 'v1');

        return $this->normalizeVersion($configured) ?? 'v1';
    }

    public function setPathPrefixPreview(string $version): void
    {
        $normalized = $this->normalizeVersion($version);
        if ($normalized !== null) {
            $this->pathPrefixPreview = $normalized;
        }
    }

    public function detectChannelFromRequest(Request $request): ?string
    {
        $first = strtolower((string) $request->segment(1));

        if ($first === 'dev' && strtolower((string) $request->segment(2)) === 'cp') {
            return null;
        }

        if ($first === 'admin') {
            return self::CHANNEL_ADMIN;
        }

        if ($first === 'staff') {
            return self::CHANNEL_STAFF;
        }

        if (in_array($first, ['agent', 'customer'], true)) {
            return self::CHANNEL_SITE;
        }

        if ($first === '' || $request->path() === '/') {
            return self::CHANNEL_SITE;
        }

        return self::CHANNEL_SITE;
    }

    public function resolveViewName(string $logicalPath): string
    {
        $logicalPath = trim($logicalPath);
        if ($logicalPath === '') {
            return $logicalPath;
        }

        $channel = $this->channel();
        if ($channel === null) {
            return $logicalPath;
        }

        $version = $this->effectiveVersion($channel);
        $fallback = $this->fallbackVersion($channel);

        if ($version === $fallback) {
            return $logicalPath;
        }

        $overlay = $this->buildOverlayViewName($channel, $version, $logicalPath);
        if (View::exists($overlay)) {
            return $overlay;
        }

        return $logicalPath;
    }

    public function resolveAssetPath(string $path): string
    {
        $path = ltrim(trim($path), '/');
        if ($path === '') {
            return '';
        }

        $channel = $this->channel();
        if ($channel === null) {
            return $path;
        }

        $version = $this->effectiveVersion($channel);
        $fallback = $this->fallbackVersion($channel);

        if ($version === $fallback) {
            return $path;
        }

        $themeMap = config('client_ui.theme_assets', []);
        if (is_array($themeMap)) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $mapKey = $extension === 'css' ? 'css' : ($extension === 'js' ? 'js' : null);
            if ($mapKey !== null && isset($themeMap[$mapKey]) && is_array($themeMap[$mapKey])) {
                $clone = $themeMap[$mapKey][$path] ?? null;
                if (is_string($clone) && $clone !== '' && is_file(public_path($clone))) {
                    return $clone;
                }
            }
        }

        $overlay = "ui/{$channel}/{$version}/{$path}";
        if (is_file(public_path($overlay))) {
            return $overlay;
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    public function activeVersions(?string $channel = null): array
    {
        $channel = $channel ?? $this->channel();
        if ($channel === null) {
            return ['v1'];
        }

        $active = config("ota-ui.channels.{$channel}.active_versions", ['v1']);

        return is_array($active) ? array_values($active) : ['v1'];
    }

    protected function previewVersionForChannel(string $channel): ?string
    {
        if ($this->pathPrefixPreview !== null && $this->previewNamespace) {
            return $this->pathPrefixPreview;
        }

        $param = (string) config('client_ui.preview_query_key', config('ota-ui.preview_query_param', 'ui'));
        $queryValue = $this->request->query($param);
        if (is_string($queryValue) && $queryValue !== '') {
            $normalized = $this->normalizeVersion($queryValue);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        if ((bool) config('client_ui.preview_enabled', true) && $this->request->hasSession()) {
            $sessionKey = (string) config('client_ui.preview_session_key', 'client_ui_preview_version');
            $sessionVersion = $this->request->session()->get($sessionKey);
            if (is_string($sessionVersion) && $sessionVersion !== '') {
                $normalized = $this->normalizeVersion($sessionVersion);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        if (in_array($channel, [self::CHANNEL_ADMIN, self::CHANNEL_STAFF], true)) {
            $otaParam = (string) config('ota-ui.preview_query_param', 'ui');
            if ($otaParam !== $param) {
                $otaQuery = $this->request->query($otaParam);
                if (is_string($otaQuery) && $otaQuery !== '') {
                    return $this->normalizeVersion($otaQuery);
                }
            }
        }

        return null;
    }

    protected function buildOverlayViewName(string $channel, string $version, string $logicalPath): string
    {
        $segments = explode('.', $logicalPath);

        return 'ui/'.$channel.'/'.$version.'/'.implode('/', $segments);
    }

    protected function normalizeVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $version = strtolower(trim($version));
        if ($version === '') {
            return null;
        }

        if (! preg_match('/^v\d+$/', $version)) {
            return null;
        }

        return $version;
    }
}
