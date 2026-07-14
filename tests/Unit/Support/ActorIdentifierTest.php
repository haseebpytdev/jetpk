<?php

namespace Tests\Unit\Support;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use App\Support\Identity\ActorIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActorIdentifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_identifier(): void
    {
        $user = User::factory()->create([
            'name' => 'Haseeb Admin',
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $this->assertSame('ADM-'.$user->id.'-Haseeb', ActorIdentifier::forUser($user));
    }

    public function test_staff_identifier(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo Staff',
            'account_type' => AccountType::Staff,
        ]);

        $this->assertSame('STF-'.$user->id.'-Demo', ActorIdentifier::forUser($user));
    }

    public function test_agency_owner_identifier_uses_prefix(): void
    {
        $agency = Agency::factory()->create([
            'name' => 'Easy Ticket',
            'settings' => ['code_prefix' => 'ET'],
        ]);
        $user = User::factory()->create([
            'name' => 'Asif Owner',
            'account_type' => AccountType::Agent,
            'current_agency_id' => $agency->id,
        ]);

        $this->assertSame('ET-AGM-'.$user->id.'-Asif', ActorIdentifier::forUser($user));
    }

    public function test_customer_identifier(): void
    {
        $user = User::factory()->create([
            'name' => 'Ahmed Customer',
            'account_type' => AccountType::Customer,
        ]);

        $this->assertSame('CU-'.$user->id.'-Ahmed', ActorIdentifier::forUser($user));
    }

    public function test_guest_identifier(): void
    {
        $this->assertSame('GU-5-Usman', ActorIdentifier::forGuest([
            'guest_id' => 5,
            'first_name' => 'Usman',
        ]));
    }

    public function test_missing_name_falls_back_to_user(): void
    {
        $user = User::factory()->create([
            'name' => '',
            'account_type' => AccountType::Staff,
        ]);

        $this->assertSame('STF-'.$user->id.'-User', ActorIdentifier::forUser($user));
    }
}
