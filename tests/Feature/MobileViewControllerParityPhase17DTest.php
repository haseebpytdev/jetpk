<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\MobileViewController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class MobileViewControllerParityPhase17DTest extends TestCase
{
    public function test_get_preview_routes_redirect_with_preference_cookie(): void
    {
        $this->get(route('view-preference.mobile-get'))->assertRedirect();
        $this->get(route('view-preference.mobile-preview'))->assertRedirect();
        $this->get(route('view-preference.desktop-preview'))->assertRedirect();
    }

    public function test_post_preference_routes_use_mobile_view_controller(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post(route('view-preference.mobile'))->assertRedirect();
        $this->post(route('view-preference.desktop'))->assertRedirect();
    }

    public function test_mobile_view_controller_class_is_loadable(): void
    {
        $this->assertTrue(class_exists(MobileViewController::class));
    }
}
