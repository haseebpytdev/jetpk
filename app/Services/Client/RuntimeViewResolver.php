<?php

namespace App\Services\Client;

use App\Models\ClientProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\View\ViewException;

/**
 * Resolves theme-specific Blade view and layout names with legacy fallback (MC-8B/8D).
 *
 * Does not register a custom view finder — callers opt in via client_view() /
 * client_layout() or direct service use. Theme folders may be absent on disk;
 * resolution always falls back to production view/layout names safely.
 */
final class RuntimeViewResolver
{
    /**
     * @var list<string>
     */
    private const AREAS = ['frontend', 'admin', 'staff', 'customer', 'agent'];

    public function __construct(
        private readonly RuntimeThemeManager $themeManager,
        private readonly CurrentClientContext $clientContext,
    ) {}

    public function view(string $name, string $area = 'frontend', ?ClientProfile $profile = null): string
    {
        $themeName = $this->themeViewName($name, $area, $profile);

        if (View::exists($themeName)) {
            return $themeName;
        }

        if ($this->requiresStrictThemedView($area)) {
            $this->handleMissingThemedView($name, $area, $themeName);
        }

        return $this->legacyViewName($name, $area);
    }

    public function exists(string $name, string $area = 'frontend', ?ClientProfile $profile = null): bool
    {
        return View::exists($this->view($name, $area, $profile));
    }

    /**
     * @param  list<string>  $names
     */
    public function first(array $names, string $area = 'frontend', ?ClientProfile $profile = null): string
    {
        foreach ($names as $name) {
            if ($this->exists($name, $area, $profile)) {
                return $this->view($name, $area, $profile);
            }
        }

        $first = $names[0] ?? '';

        return $first !== '' ? $this->view($first, $area, $profile) : '';
    }

    public function layout(string $name = 'app', string $area = 'frontend', ?ClientProfile $profile = null): string
    {
        $normalizedArea = $this->normalizeArea($area);
        $themeView = $this->themeLayoutName($name, $normalizedArea, $profile);

        if (View::exists($themeView)) {
            return $themeView;
        }

        if ($this->requiresStrictThemedView($normalizedArea)) {
            $this->handleMissingThemedLayout($name, $normalizedArea, $themeView);
        }

        return $this->legacyLayoutName($name, $normalizedArea);
    }

    public function layoutExists(string $name = 'app', string $area = 'frontend', ?ClientProfile $profile = null): bool
    {
        return View::exists($this->layout($name, $area, $profile));
    }

    public function themeLayoutName(string $name, string $area = 'frontend', ?ClientProfile $profile = null): string
    {
        $normalizedArea = $this->normalizeArea($area);
        $theme = $this->resolvedTheme($normalizedArea, $profile);
        $relative = $this->layoutRelativeName($name, $normalizedArea);

        return 'themes.'.$normalizedArea.'.'.$theme.'.'.$relative;
    }

    public function themeViewName(string $name, string $area = 'frontend', ?ClientProfile $profile = null): string
    {
        $normalizedArea = $this->normalizeArea($area);
        $theme = $this->resolvedTheme($normalizedArea, $profile);

        return 'themes.'.$normalizedArea.'.'.$theme.'.'.$name;
    }

    public function legacyViewName(string $name, string $area = 'frontend'): string
    {
        $normalizedArea = $this->normalizeArea($area);
        $prefix = trim((string) (config('client_view_paths.areas.'.$normalizedArea.'.legacy_prefix') ?? ''));

        if ($prefix !== '' && str_starts_with($name, $prefix.'.')) {
            return $name;
        }

        if ($prefix !== '' && in_array($normalizedArea, ['admin', 'staff', 'customer', 'agent'], true)) {
            $prefixed = $prefix.'.'.$name;
            if (View::exists($prefixed)) {
                return $prefixed;
            }
        }

        $sharedDashboard = 'dashboard.'.$name;
        if (View::exists($sharedDashboard)) {
            return $sharedDashboard;
        }

        if (str_contains($name, '.')) {
            return $name;
        }

        return $prefix !== '' ? $prefix.'.'.$name : $name;
    }

    /**
     * @return array<string, array{
     *     area: string,
     *     resolved_theme: string,
     *     theme_view_root: string,
     *     fallback_root: string,
     *     theme_root_exists: bool,
     *     note: string
     * }>|array{
     *     area: string,
     *     resolved_theme: string,
     *     theme_view_root: string,
     *     fallback_root: string,
     *     theme_root_exists: bool,
     *     note: string
     * }
     */
    public function summary(?string $area = null, ?ClientProfile $profile = null): array
    {
        if ($area !== null) {
            return $this->areaSummary($this->normalizeArea($area), $profile);
        }

        $summaries = [];
        foreach (self::AREAS as $areaKey) {
            $summaries[$areaKey] = $this->areaSummary($areaKey, $profile);
        }

        return $summaries;
    }

    /**
     * @return array{
     *     logical_name: string,
     *     area: string,
     *     resolved_theme: string,
     *     theme_view_name: string,
     *     legacy_view_name: string,
     *     resolved_view_name: string,
     *     fallback_used: bool,
     *     view_exists: bool
     * }
     */
    /**
     * @return array{
     *     requested_layout: string,
     *     area: string,
     *     selected_theme: string|null,
     *     resolved_theme: string,
     *     theme_layout_name: string,
     *     legacy_layout_name: string,
     *     resolved_layout_name: string,
     *     fallback_used: bool,
     *     theme_layout_exists: bool,
     *     legacy_layout_exists: bool,
     *     layout_exists: bool
     * }
     */
    public function resolveLayoutSample(string $name, string $area, ?ClientProfile $profile = null): array
    {
        $normalizedArea = $this->normalizeArea($area);
        $theme = $this->resolvedTheme($normalizedArea, $profile);
        $themeLayoutName = $this->themeLayoutName($name, $normalizedArea, $profile);
        $legacyLayoutName = $this->legacyLayoutName($name, $normalizedArea);
        $resolvedLayoutName = $this->layout($name, $normalizedArea, $profile);
        $fallbackUsed = $resolvedLayoutName === $legacyLayoutName;

        return [
            'requested_layout' => $name,
            'area' => $normalizedArea,
            'selected_theme' => $this->selectedTheme($normalizedArea, $profile),
            'resolved_theme' => $theme,
            'theme_layout_name' => $themeLayoutName,
            'legacy_layout_name' => $legacyLayoutName,
            'resolved_layout_name' => $resolvedLayoutName,
            'fallback_used' => $fallbackUsed,
            'theme_layout_exists' => View::exists($themeLayoutName),
            'legacy_layout_exists' => View::exists($legacyLayoutName),
            'layout_exists' => View::exists($resolvedLayoutName),
        ];
    }

    public function resolveSample(string $name, string $area, ?ClientProfile $profile = null): array
    {
        $normalizedArea = $this->normalizeArea($area);
        $theme = $this->resolvedTheme($normalizedArea, $profile);
        $themeViewName = $this->themeViewName($name, $normalizedArea, $profile);
        $legacyViewName = $this->legacyViewName($name, $normalizedArea);
        $resolvedViewName = $this->view($name, $normalizedArea, $profile);
        $fallbackUsed = $resolvedViewName === $legacyViewName;

        return [
            'logical_name' => $name,
            'area' => $normalizedArea,
            'resolved_theme' => $theme,
            'theme_view_name' => $themeViewName,
            'legacy_view_name' => $legacyViewName,
            'resolved_view_name' => $resolvedViewName,
            'fallback_used' => $fallbackUsed,
            'view_exists' => View::exists($resolvedViewName),
        ];
    }

    /**
     * @return array{
     *     area: string,
     *     resolved_theme: string,
     *     theme_view_root: string,
     *     fallback_root: string,
     *     theme_root_exists: bool,
     *     note: string
     * }
     */
    private function areaSummary(string $area, ?ClientProfile $profile): array
    {
        $theme = $this->resolvedTheme($area, $profile);
        $themeRootRelative = $this->themeRootRelative($area, $theme);
        $themeViewRoot = resource_path('views/'.$themeRootRelative);
        $fallbackRoot = (string) (config('client_view_paths.areas.'.$area.'.legacy_root') ?? '');

        return [
            'area' => $area,
            'resolved_theme' => $theme,
            'theme_view_root' => 'resources/views/'.$themeRootRelative,
            'fallback_root' => $fallbackRoot,
            'theme_root_exists' => is_dir($themeViewRoot),
            'note' => (string) config('client_view_paths.mc8d_note', config('client_view_paths.mc8b_note', 'MC-8B resolver active.')),
        ];
    }

    private function selectedTheme(string $area, ?ClientProfile $profile): ?string
    {
        $profile = $this->resolveProfile($profile);

        $column = match ($area) {
            'frontend' => 'active_frontend_theme',
            'admin' => 'active_admin_theme',
            'staff' => 'active_staff_theme',
            default => null,
        };

        if ($profile !== null && $column !== null) {
            $value = trim((string) ($profile->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        if (in_array($area, ['frontend', 'admin', 'staff'], true)) {
            $configKey = match ($area) {
                'frontend' => 'theme',
                'admin' => 'admin_theme',
                'staff' => 'staff_theme',
            };
            $value = trim((string) config('ota_client.'.$configKey, ''));
            if ($value !== '') {
                return $value;
            }
        }

        $fallback = trim((string) (config('client_view_paths.areas.'.$area.'.theme_fallback') ?? ''));

        return $fallback !== '' ? $fallback : null;
    }

    private function resolvedTheme(string $area, ?ClientProfile $profile = null): string
    {
        $profile = $this->resolveProfile($profile);

        return match ($area) {
            'frontend' => $this->themeManager->frontend($profile),
            'admin' => $this->themeManager->admin($profile),
            'staff' => $this->themeManager->staff($profile),
            'agent', 'customer' => $this->resolvedAgentCustomerTheme($area, $profile),
            default => trim((string) (config('client_view_paths.areas.'.$area.'.theme_fallback') ?? '')),
        };
    }

    /**
     * Agent/customer portals share the JetPK ops shell when the client profile uses jetpakistan themes.
     */
    private function resolvedAgentCustomerTheme(string $area, ?ClientProfile $profile): string
    {
        $fallback = trim((string) (config('client_view_paths.areas.'.$area.'.theme_fallback') ?? ''));

        if ($profile !== null) {
            foreach (['active_admin_theme', 'active_staff_theme', 'active_frontend_theme'] as $column) {
                $value = trim((string) ($profile->{$column} ?? ''));
                if ($value === 'jetpakistan') {
                    return 'jetpakistan';
                }
            }
        }

        if (config('client.standalone', false)) {
            $canonical = trim((string) config('client.canonical_client.theme', ''));
            if ($canonical !== '') {
                return $canonical;
            }
        }

        return $fallback !== '' ? $fallback : 'default-'.$area;
    }

    private function themeRootRelative(string $area, string $theme): string
    {
        $template = trim((string) (config('client_view_paths.areas.'.$area.'.theme_root') ?? ''));

        return str_replace(
            ['{area}', '{theme}'],
            [$area, $theme],
            $template,
        );
    }

    private function legacyLayoutName(string $name, string $area): string
    {
        if (str_contains($name, '.')) {
            return $name;
        }

        if ($name !== 'app') {
            return 'layouts.'.$name;
        }

        return match ($area) {
            'frontend' => 'layouts.frontend',
            'admin', 'staff' => 'layouts.dashboard',
            'agent' => 'layouts.agent-portal',
            'customer' => 'layouts.customer-account',
            default => 'layouts.app',
        };
    }

    private function layoutRelativeName(string $name, string $area): string
    {
        if (str_contains($name, '.')) {
            return $name;
        }

        if ($name === 'app') {
            return match ($area) {
                'frontend' => 'layouts.frontend',
                'admin', 'staff' => 'layouts.dashboard',
                'agent' => 'layouts.agent-portal',
                'customer' => 'layouts.customer-account',
                default => 'layouts.app',
            };
        }

        return 'layouts.'.$name;
    }

    private function resolveProfile(?ClientProfile $profile): ?ClientProfile
    {
        if ($profile instanceof ClientProfile) {
            return $profile;
        }

        return $this->clientContext->get();
    }

    private function normalizeArea(string $area): string
    {
        $area = strtolower(trim($area));

        return in_array($area, self::AREAS, true) ? $area : 'frontend';
    }

    private function requiresStrictThemedView(string $area): bool
    {
        if (! config('client.standalone', false)) {
            return false;
        }

        if (config('client.fallback_policy.allow_cross_client_views', false)) {
            return false;
        }

        return in_array($this->normalizeArea($area), ['frontend', 'customer', 'agent'], true);
    }

    private function handleMissingThemedView(string $logicalName, string $area, string $themeViewName): void
    {
        Log::warning('jetpk.standalone.missing_themed_view', [
            'logical_name' => $logicalName,
            'area' => $area,
            'theme_view' => $themeViewName,
        ]);

        if (app()->environment('production') && ! app()->runningUnitTests()) {
            throw new ViewException('The requested page is temporarily unavailable.');
        }

        throw new ViewException(sprintf(
            'Missing JetPK themed view [%s] for logical key [%s] in area [%s].',
            $themeViewName,
            $logicalName,
            $area,
        ));
    }

    private function handleMissingThemedLayout(string $layoutName, string $area, string $themeLayoutName): void
    {
        Log::warning('jetpk.standalone.missing_themed_layout', [
            'layout_name' => $layoutName,
            'area' => $area,
            'theme_layout' => $themeLayoutName,
        ]);

        if (app()->environment('production') && ! app()->runningUnitTests()) {
            throw new ViewException('The requested page is temporarily unavailable.');
        }

        throw new ViewException(sprintf(
            'Missing JetPK themed layout [%s] for layout [%s] in area [%s].',
            $themeLayoutName,
            $layoutName,
            $area,
        ));
    }
}
