<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Mail\BookingUniversalNotification;
use App\Mail\OtaOperationalNotificationMail;
use App\Models\Agency;
use App\Models\AgencyCommunicationSetting;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthLoginSecurityEmailCanonicalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        AgencyCommunicationSetting::query()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['email_enabled' => true],
        );
        config([
            'app.url' => 'http://127.0.0.1:8088',
            'ota_client.slug' => 'jetpk',
            'ota_client.auth.require_login_otp' => false,
            'ota.notify_admin_login' => true,
            'ota.notify_staff_login' => true,
            'ota.notify_agent_login' => true,
            'ota.notify_customer_login' => true,
            'ota.notify_auth_new_device_login' => true,
            'ota.auth_login_success_email_cooldown_minutes' => 0,
        ]);
    }

    public function test_one_admin_login_sends_one_canonical_security_email_without_localhost_or_booking_snapshot(): void
    {
        Mail::fake();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        $this->post('/login', [
            'login' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));

        Mail::assertSent(OtaOperationalNotificationMail::class, 1);
        Mail::assertNotSent(BookingUniversalNotification::class);

        $html = '';
        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail) use (&$html): bool {
            $html = $mail->htmlBody;
            $this->assertSame('JetPakistan — Admin sign-in detected', $mail->emailSubject);
            $this->assertNotSame('', trim($mail->plainBody));
            $this->assertStringContainsString('Reset password', $mail->plainBody);
            $this->assertSame('emails.themes.jetpakistan.plain-text', $mail->content()->text);
            $this->assertSame($mail->plainBody, $mail->content()->with['plainBody'] ?? null);

            return true;
        });

        $this->assertStringContainsString('jetpk-container', $html);
        $this->assertStringContainsString('max-width:640px', $html);
        $this->assertStringContainsString('content="light only"', $html);
        $this->assertStringNotContainsString('width="620"', $html);
        $this->assertStringNotContainsString('max-width:620px', $html);
        $this->assertStringNotContainsString('href="http://127.0.0.1', $html);
        $this->assertStringNotContainsString('href="https://127.0.0.1', $html);
        $this->assertStringNotContainsString('src="http://127.0.0.1', $html);
        $this->assertStringNotContainsString('src="https://127.0.0.1', $html);
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertStringNotContainsString(':8088', $html);
        $this->assertStringNotContainsString('/index.php/forgot-password', $html);
        $this->assertStringContainsString('https://jetpakistan.pk/forgot-password', $html);
        $this->assertStringNotContainsString('Booking snapshot', $html);
        $this->assertStringNotContainsString('Booking summary', $html);
        $this->assertSame(1, substr_count($html, '>Login successful</h1>'));
        $this->assertStringContainsString('Sign-in details', $html);
        $this->assertStringContainsString('Was this you?', $html);
        $this->assertDatabaseHas('communication_logs', [
            'event' => OtaNotificationEvent::AdminLoginSuccess->value,
        ]);
        $this->assertDatabaseMissing('communication_logs', [
            'event' => OtaNotificationEvent::AuthNewDeviceLogin->value,
        ]);
    }

    public function test_new_device_facts_fold_into_the_same_login_email(): void
    {
        Mail::fake();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        AuditLog::query()->create([
            'agency_id' => $admin->current_agency_id,
            'user_id' => $admin->id,
            'action' => 'auth.login_success',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'properties' => ['account_type' => $admin->account_type?->value],
            'ip_address' => '1.1.1.1',
            'user_agent' => 'OldBrowser/1.0',
        ]);

        $this->withServerVariables(['HTTP_USER_AGENT' => 'NewBrowser/2.0'])
            ->post('/login', [
                'login' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        Mail::assertSent(OtaOperationalNotificationMail::class, 1);
        Mail::assertNotSent(BookingUniversalNotification::class);

        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail): bool {
            $this->assertStringContainsString('New login detected', $mail->htmlBody);
            $this->assertStringContainsString('Sign-in details', $mail->htmlBody);
            $this->assertStringContainsString('new device or browser', strtolower($mail->htmlBody));
            $this->assertLessThanOrEqual(1, substr_count($mail->htmlBody, 'Login successful'));
            $this->assertStringContainsString('jetpk-container', $mail->htmlBody);

            return true;
        });
    }

    public function test_two_successive_admin_logins_each_send_one_security_email(): void
    {
        Mail::fake();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $admin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        $this->post('/login', [
            'login' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));
        Mail::assertSent(OtaOperationalNotificationMail::class, 1);

        $this->post('/logout')->assertRedirect();
        Mail::fake();

        $this->post('/login', [
            'login' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));
        Mail::assertSent(OtaOperationalNotificationMail::class, 1);
        Mail::assertSent(OtaOperationalNotificationMail::class, function (OtaOperationalNotificationMail $mail): bool {
            return $mail->emailSubject === 'JetPakistan — Admin sign-in detected';
        });
    }
}
