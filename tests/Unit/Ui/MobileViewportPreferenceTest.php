<?php

namespace Tests\Unit\Ui;

use App\Support\Ui\MobileViewPreference;
use Illuminate\Http\Request;
use Tests\TestCase;

class MobileViewportPreferenceTest extends TestCase
{
    public function test_viewport_header_prefers_mobile_at_or_below_breakpoint(): void
    {
        $pref = app(MobileViewPreference::class);
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_SEC_CH_VIEWPORT_WIDTH' => '390',
        ]);

        $this->assertTrue($pref->viewportPrefersMobile($request));
    }

    public function test_viewport_header_prefers_desktop_above_breakpoint(): void
    {
        $pref = app(MobileViewPreference::class);
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_SEC_CH_VIEWPORT_WIDTH' => '1440',
        ]);

        $this->assertFalse($pref->viewportPrefersMobile($request));
    }

    public function test_auto_shell_query_overrides_without_cookie(): void
    {
        $pref = app(MobileViewPreference::class);

        $mobile = Request::create('/?_ota_auto_shell=mobile', 'GET');
        $desktop = Request::create('/?_ota_auto_shell=desktop', 'GET');

        $this->assertTrue($pref->viewportPrefersMobile($mobile));
        $this->assertFalse($pref->viewportPrefersMobile($desktop));
    }
}
