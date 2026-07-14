<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalEmailVerificationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_portal_blocked_after_deadline_when_email_unverified(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'email_verified_at' => null,
            'social_email_verification_deadline' => now()->subHour(),
        ]);

        $this->actingAs($customer)
            ->get('/customer')
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status');
    }

    public function test_verified_customer_can_access_portal(): void
    {
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'status' => UserAccountStatus::Active,
            'email_verified_at' => now(),
            'social_email_verification_deadline' => now()->subDay(),
        ]);

        $this->actingAs($customer)
            ->get('/customer')
            ->assertOk();
    }
}
