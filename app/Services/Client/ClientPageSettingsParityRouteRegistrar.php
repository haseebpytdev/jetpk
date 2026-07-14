<?php

namespace App\Services\Client;

use App\Http\Controllers\Admin\ClientPageSettingsController;
use App\Support\Client\ReservedClientPreviewSlugs;
use Illuminate\Support\Facades\Route;

/**
 * Registers client-prefixed mutating admin page-settings routes for JetPK preview context.
 *
 * GET/HEAD page-settings parity is handled by ClientPrefixedRouteRegistrar; forms need
 * PATCH/POST/DELETE under /{clientSlug}/admin/page-settings/* so client_route() stays prefixed.
 */
final class ClientPageSettingsParityRouteRegistrar
{
    /**
     * @return array{registered: int, skipped: int}
     */
    public function register(): array
    {
        if (! config('client_route_parity.enabled', true)) {
            return ['registered' => 0, 'skipped' => 0];
        }

        $registered = 0;
        $skipped = 0;

        $group = Route::middleware([
            'web',
            'preview.client',
            'preview.client.persist',
            'auth',
            'agency.context',
            'account.type:platform_admin,staff',
        ])
            ->prefix('{clientSlug}/admin/page-settings')
            ->where(['clientSlug' => ReservedClientPreviewSlugs::routeParameterConstraint()])
            ->name('client.parity.admin.page-settings.');

        $routes = [
            ['patch', '{pageKey}', 'update', 'update'],
            ['post', '{pageKey}/publish', 'publish', 'publish'],
            ['post', '{pageKey}/preview', 'preview.begin', 'beginPreview'],
            ['post', '{pageKey}/assets', 'assets.store', 'storeAsset'],
            ['delete', '{pageKey}/assets/{asset}', 'assets.destroy', 'destroyAsset'],
            ['post', 'palette/generate', 'palette.generate', 'generatePalette'],
            ['post', 'palette/apply', 'palette.apply', 'applyPalette'],
        ];

        foreach ($routes as [$method, $uri, $name, $action]) {
            $parityName = 'client.parity.admin.page-settings.'.$name;
            if (Route::has($parityName)) {
                $skipped++;

                continue;
            }

            $route = match ($method) {
                'patch' => $group->patch($uri, [ClientPageSettingsController::class, $action]),
                'post' => $group->post($uri, [ClientPageSettingsController::class, $action]),
                'delete' => $group->delete($uri, [ClientPageSettingsController::class, $action]),
                default => null,
            };

            if ($route === null) {
                $skipped++;

                continue;
            }

            $route->name($name);
            $route->setAction(array_merge($route->getAction(), [
                'client_parity_classification' => 'admin_dashboard',
                'client_parity_portal' => 'Admin',
                'client_parity_mutating_page_settings' => true,
            ]));
            $registered++;
        }

        return ['registered' => $registered, 'skipped' => $skipped];
    }
}
