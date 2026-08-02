<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use App\Support\Auth\AuthPostLoginRedirectResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPostLoginRedirectResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_lands_on_customer_bookings(): void
    {
        $user = User::factory()->customer()->create([
            'email_verified_at' => now(),
        ]);

        $path = app(AuthPostLoginRedirectResolver::class)->resolvePath($user);

        $this->assertTrue(str_starts_with($path, '/customer'));
    }

    public function test_unverified_customer_lands_on_verify_email(): void
    {
        $user = User::factory()->customer()->create([
            'email_verified_at' => null,
        ]);

        $path = app(AuthPostLoginRedirectResolver::class)->resolvePath($user);

        $this->assertStringContainsString('verify-email', $path);
    }

    public function test_must_change_password_takes_precedence(): void
    {
        $user = User::factory()->customer()->create([
            'email_verified_at' => now(),
            'must_change_password' => true,
        ]);

        $path = app(AuthPostLoginRedirectResolver::class)->resolvePath($user);

        $this->assertStringContainsString('password', $path);
    }

    public function test_agent_lands_on_agent_dashboard(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::Agent,
        ]);

        $path = app(AuthPostLoginRedirectResolver::class)->resolvePath($user);

        $this->assertSame('/agent', $path);
    }

    public function test_platform_admin_lands_on_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $path = app(AuthPostLoginRedirectResolver::class)->resolvePath($user);

        $this->assertSame('/admin/dashboard', $path);
    }
}
