<?php

namespace App\Services\Client;

use App\Http\Controllers\Frontend\ClientManagedPageController;
use App\Support\Client\ClientManagedPageReservedSlugs;
use Illuminate\Support\Facades\Route;

/**
 * Registers the JetPK public CMS custom-page catch-all with reserved-slug constraints.
 */
final class ClientCustomPageRouteRegistrar
{
    public function register(): void
    {
        if (Route::has('client.custom-page.show')) {
            $existing = Route::getRoutes()->getByName('client.custom-page.show');
            if ($existing !== null) {
                $existing->where('slug', ClientManagedPageReservedSlugs::routeSlugConstraint());
            }

            return;
        }

        Route::get('/{slug}', [ClientManagedPageController::class, 'customShow'])
            ->where('slug', ClientManagedPageReservedSlugs::routeSlugConstraint())
            ->name('client.custom-page.show');
    }
}
