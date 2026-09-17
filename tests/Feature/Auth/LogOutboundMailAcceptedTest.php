<?php

namespace Tests\Feature\Auth;

use App\Listeners\LogOutboundMailAccepted;
use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Support\Auth\LoginOtpMailDiagnostics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LogOutboundMailAcceptedTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_sent_listener_is_registered(): void
    {
        $registered = collect(Event::getRawListeners()[MessageSent::class] ?? [])
            ->contains(function (mixed $listener): bool {
                if (is_string($listener)) {
                    return $listener === LogOutboundMailAccepted::class
                        || str_starts_with($listener, LogOutboundMailAccepted::class.'@');
                }

                if (is_array($listener) && isset($listener[0])) {
                    $class = is_object($listener[0]) ? $listener[0]::class : (string) $listener[0];

                    return $class === LogOutboundMailAccepted::class;
                }

                return false;
            });

        $this->assertTrue($registered, 'LogOutboundMailAccepted must listen for MessageSent.');
    }

    public function test_mask_email_never_exposes_full_local_part(): void
    {
        $masked = LoginOtpMailDiagnostics::maskEmail('qa.owner@gmail.com');
        $this->assertStringContainsString('@gmail.com', $masked);
        $this->assertStringNotContainsString('qa.owner', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function test_login_otp_mail_send_logs_acceptance_without_otp(): void
    {
        Config::set('mail.default', 'array');
        Config::set('mail.from.address', 'ota@jetpakistan.pk');
        Config::set('mail.from.name', 'JetPakistan');

        Log::spy();

        $user = User::factory()->create([
            'email' => 'qa.otp.accept@example.test',
        ]);

        Mail::to($user->email)->send(new LoginOtpMail(
            user: $user,
            brandName: 'JetPakistan',
            otpCode: '123456',
            expiryMinutes: 10,
            clientSlug: 'jetpk',
        ));

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                if ($message !== 'Outbound mail accepted by transport.') {
                    return false;
                }

                $encoded = json_encode($context);
                if (! is_string($encoded) || str_contains($encoded, '123456')) {
                    return false;
                }

                return isset($context['to_masked']) && is_array($context['to_masked']);
            })
            ->atLeast()
            ->once();
    }
}
