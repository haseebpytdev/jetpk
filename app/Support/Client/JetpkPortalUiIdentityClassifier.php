<?php

namespace App\Support\Client;

use Illuminate\Support\Facades\View;

/**
 * Classifies JetPK portal views by shell family (operational vs public-portal).
 */
class JetpkPortalUiIdentityClassifier
{
  /**
   * @return 'operational'|'public-portal'|'summary-module'|'shell-wrapped'|'unknown'
   */
    public static function classify(string $area, string $resolvedView, string $pageStatus): string
    {
        if ($pageStatus === 'shell-wrapped') {
            return 'shell-wrapped';
        }

        if ($pageStatus === 'summary-module') {
            return 'summary-module';
        }

        if (! View::exists($resolvedView)) {
            return 'unknown';
        }

        $shell = self::traceShellFamily($resolvedView);

        return $shell !== 'unknown' ? $shell : 'unknown';
    }

    /**
     * @return 'operational'|'public-portal'|'unknown'
     */
    public static function traceShellFamily(string $viewName, int $depth = 0): string
    {
        if ($depth > 8) {
            return 'unknown';
        }

        if (str_contains($viewName, 'themes.admin.jetpakistan.layouts.dashboard')
            || str_contains($viewName, 'themes.staff.jetpakistan.layouts.dashboard')) {
            return 'operational';
        }

        if (str_contains($viewName, 'themes.frontend.jetpakistan.layouts.portal')
            || str_contains($viewName, 'themes.agent.jetpakistan.layouts.agent-portal')
            || str_contains($viewName, 'themes.customer.jetpakistan.layouts.customer-account')) {
            return 'public-portal';
        }

        try {
            $path = View::getFinder()->find($viewName);
        } catch (\InvalidArgumentException) {
            return 'unknown';
        }

        $content = (string) file_get_contents($path);

        if (str_contains($content, 'themes.admin.jetpakistan.layouts.dashboard')) {
            return 'operational';
        }

        if (str_contains($content, 'themes.frontend.jetpakistan.layouts.portal')) {
            return 'public-portal';
        }

        if (preg_match("/@extends\(\s*'([^']+)'/", $content, $match)) {
            return self::traceShellFamily($match[1], $depth + 1);
        }

        if (preg_match('/@extends\(\s*"([^"]+)"/', $content, $match)) {
            return self::traceShellFamily($match[1], $depth + 1);
        }

        if (preg_match("/@extends\(client_layout\('([^']+)',\s*'([^']+)'\)/", $content, $match)) {
            $layoutView = client_layout($match[1], $match[2]);

            return self::traceShellFamily($layoutView, $depth + 1);
        }

        return 'unknown';
    }

    public static function expectedShell(string $area): string
    {
        return in_array($area, ['admin', 'staff'], true) ? 'operational' : 'public-portal';
    }

    public static function identityMatchesArea(string $area, string $identity): bool
    {
        if (in_array($identity, ['shell-wrapped', 'summary-module', 'unknown'], true)) {
            return $identity !== 'shell-wrapped';
        }

        return $identity === self::expectedShell($area);
    }
}
