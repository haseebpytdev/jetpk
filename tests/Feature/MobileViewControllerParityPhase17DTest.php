<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\MobileViewController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase17D MobileViewController preference endpoints were retired.
 * JetPakistan uses responsive CSS rather than mobile/desktop preference cookies.
 */
class MobileViewControllerParityPhase17DTest extends TestCase
{
    public function test_get_preview_routes_redirect_with_preference_cookie(): void
    {
        $this->assertFalse(Route::has('view-preference.mobile-get'));
        $this->assertFalse(Route::has('view-preference.mobile-preview'));
        $this->assertFalse(Route::has('view-preference.desktop-preview'));
    }

    public function test_post_preference_routes_use_mobile_view_controller(): void
    {
        $this->assertFalse(Route::has('view-preference.mobile'));
        $this->assertFalse(Route::has('view-preference.desktop'));
    }

    public function test_mobile_view_controller_class_is_loadable(): void
    {
        $this->assertFalse(
            class_exists(MobileViewController::class),
            'MobileViewController was retired for JetPakistan responsive shells.'
        );
    }
}
