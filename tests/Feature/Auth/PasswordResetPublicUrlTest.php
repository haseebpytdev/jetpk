<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Url\PublicActionUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetPublicUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_notification_uses_public_app_url_not_request_root(): void
    {
        config(['app.url' => 'https://jetpakistan.pk']);

        Notification::fake();

        $user = User::factory()->create();

        $this->withServerVariables([
            'HTTP_HOST' => '127.0.0.1:8088',
            'SERVER_NAME' => '127.0.0.1',
            'SERVER_PORT' => '8088',
            'SCRIPT_NAME' => '/index.php',
        ])->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            $this->assertStringStartsWith('https://jetpakistan.pk/reset-password/', $url);
            $this->assertStringNotContainsString('127.0.0.1', $url);
            $this->assertStringNotContainsString('localhost', $url);
            $this->assertStringNotContainsString(':8088', $url);
            $this->assertStringNotContainsString('/index.php/', $url);

            return true;
        });
    }

    public function test_public_action_url_helper_strips_index_php_and_uses_app_url(): void
    {
        config(['app.url' => 'https://jetpakistan.pk']);

        $url = PublicActionUrl::passwordReset('sample-token', 'qa@jetpakistan.test');

        $this->assertSame(
            'https://jetpakistan.pk/reset-password/sample-token?email=qa%40jetpakistan.test',
            $url,
        );
    }
}
