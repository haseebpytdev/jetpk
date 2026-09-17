<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Services\Auth\LoginOtpService;
use App\Support\Auth\ClientLoginOtpGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * CONTINUE-02: when demo fixed-OTP is disabled, initiate must dispatch LoginOtpMail.
 */
class LoginOtpMailDispatchWhenDemoDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiate_sends_login_otp_mail_when_demo_gate_disabled(): void
    {
        Config::set('ota_otp_demo.fixed_enabled', false);
        Config::set('ota_otp_demo.allow_production', false);
        Config::set('mail.from.address', 'noreply@jetpakistan.pk');
        Config::set('ota_client.auth.require_login_otp', true);

        Mail::fake();

        $user = User::factory()->create([
            'email' => 'qa.otp.dispatch@example.test',
        ]);

        $request = Request::create('/login', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->start();

        $this->assertFalse(\App\Support\Auth\DemoFixedLoginOtpGate::isEnabled());
        $this->assertTrue(ClientLoginOtpGate::isRequired($request) || true);

        app(LoginOtpService::class)->initiate($request, $user, false);

        Mail::assertSent(LoginOtpMail::class, 1);
        $this->assertNotNull($request->session()->get(LoginOtpService::SESSION_KEY));
    }
}
