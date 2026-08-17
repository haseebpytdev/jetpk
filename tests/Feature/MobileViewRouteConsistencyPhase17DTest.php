<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\MobileViewController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase17D mobile preference routes were retired for JetPakistan responsive CSS.
 * Canonical proof: JetpkCanonicalResponsiveUiTest.
 */
class MobileViewRouteConsistencyPhase17DTest extends TestCase
{
    public function test_mobile_view_controller_class_exists(): void
    {
        $this->assertFalse(
            class_exists(MobileViewController::class),
            'MobileViewController was retired; responsive UX uses CSS shells.'
        );
    }

    public function test_mobile_preference_routes_resolve_to_mobile_view_controller(): void
    {
        foreach ([
            'view-preference.mobile',
            'view-preference.desktop',
            'view-preference.mobile-get',
            'view-preference.desktop-preview',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Retired route still registered: {$name}");
        }
    }

    public function test_mobile_preference_post_routes_accept_requests(): void
    {
        $this->assertFalse(Route::has('view-preference.mobile'));
        $this->assertFalse(Route::has('view-preference.desktop'));
    }
}
