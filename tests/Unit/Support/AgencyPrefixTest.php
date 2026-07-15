<?php

namespace Tests\Unit\Support;

use App\Enums\AccountType;
use App\Models\Agency;
use App\Models\User;
use App\Support\Agencies\AgencyPrefixService;
use App\Support\Identity\ActorIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AgencyPrefixTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_prefix_easy_ticket(): void
    {
        $this->assertSame('ET', AgencyPrefixService::suggestPrefix('Easy Ticket'));
    }

    public function test_prefix_saved_in_agency_settings(): void
    {
        $agency = Agency::factory()->create(['name' => 'Easy Ticket']);

        AgencyPrefixService::savePrefix($agency, 'ET');
        $agency->refresh();

        $this->assertSame('ET', AgencyPrefixService::storedPrefix($agency));
        $this->assertSame('ET', $agency->settings['code_prefix'] ?? null);
    }

    public function test_prefix_uniqueness_is_enforced(): void
    {
        $first = Agency::factory()->create(['settings' => ['code_prefix' => 'ET']]);
        $second = Agency::factory()->create(['name' => 'Other Agency']);

        $this->expectException(ValidationException::class);
        AgencyPrefixService::savePrefix($second, 'ET');
    }

    public function test_invalid_prefix_is_rejected(): void
    {
        $agency = Agency::factory()->create();

        $this->expectException(ValidationException::class);
        AgencyPrefixService::savePrefix($agency, 'e');
    }

    public function test_actor_identifier_uses_prefix(): void
    {
        $agency = Agency::factory()->create([
            'name' => 'Easy Ticket',
            'settings' => ['code_prefix' => 'ET'],
        ]);
        $owner = User::factory()->create([
            'name' => 'Ali Staff',
            'account_type' => AccountType::AgentStaff,
            'current_agency_id' => $agency->id,
        ]);

        $this->assertSame('ET-AGST-'.$owner->id.'-Ali', ActorIdentifier::forUser($owner));
    }
}
