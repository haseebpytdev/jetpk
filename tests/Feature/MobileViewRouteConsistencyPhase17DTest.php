<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\MobileViewController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MobileViewRouteConsistencyPhase17DTest extends TestCase
{
    public function test_mobile_view_controller_class_exists(): void
    {
        $this->assertTrue(class_exists(MobileViewController::class));
    }

    public function test_mobile_preference_routes_resolve_to_mobile_view_controller(): void
    {
        $names = [
            'view-preference.mobile',
            'view-preference.desktop',
            'view-preference.mobile-get',
            'view-preference.desktop-preview',
        ];

        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $action = (string) $route->getAction('uses');
            $this->assertStringContainsString(MobileViewController::class, $action, $name);
        }
    }

    public function test_mobile_preference_post_routes_accept_requests(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post(route('view-preference.mobile'))->assertRedirect();
        $this->post(route('view-preference.desktop'))->assertRedirect();
    }
}
