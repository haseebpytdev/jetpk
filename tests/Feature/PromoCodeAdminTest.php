<?php

namespace Tests\Feature;

use App\Enums\PromoCodeStatus;
use App\Enums\PromoCodeType;
use App\Models\Agency;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Promo\PromoCodeValidationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PromoCodeAdminTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_value_zero_is_rejected_on_store(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->post(route('admin.promo-codes.store'), [
            'code' => 'ZERO',
            'type' => PromoCodeType::Percent->value,
            'value' => 0,
            'applies_to' => 'flights',
            'status' => PromoCodeStatus::Active->value,
        ])->assertSessionHasErrors('value');
    }

    public function test_agency_admin_can_list_create_edit_and_deactivate_promo_code(): void
    {
        $admin = $this->platformAdmin();
        $agencyId = $admin->current_agency_id;

        $this->actingAs($admin)->get(route('admin.promo-codes.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.promo-codes.store'), [
            'code' => 'SAVE10',
            'name' => 'Ten percent off',
            'type' => PromoCodeType::Percent->value,
            'value' => 10,
            'applies_to' => 'flights',
            'status' => PromoCodeStatus::Active->value,
        ])->assertRedirect(route('admin.promo-codes.index'));

        $promo = PromoCode::query()->where('code', 'SAVE10')->where('agency_id', $agencyId)->first();
        $this->assertNotNull($promo);
        $this->assertSame('SAVE10', $promo->code);

        $this->actingAs($admin)->get(route('admin.promo-codes.edit', $promo))->assertOk();

        $this->actingAs($admin)->patch(route('admin.promo-codes.update', $promo), [
            'code' => 'SAVE10',
            'name' => 'Updated name',
            'type' => PromoCodeType::Percent->value,
            'value' => 15,
            'applies_to' => 'flights',
            'status' => PromoCodeStatus::Active->value,
        ])->assertRedirect(route('admin.promo-codes.index'));

        $promo->refresh();
        $this->assertSame('Updated name', $promo->name);
        $this->assertEquals(15, (float) $promo->value);

        $this->actingAs($admin)->patch(route('admin.promo-codes.toggle-status', $promo))
            ->assertRedirect();

        $promo->refresh();
        $this->assertSame(PromoCodeStatus::Inactive, $promo->status);
    }

    public function test_promo_code_validation_service_rejects_inactive_and_expired(): void
    {
        $agency = Agency::factory()->create();
        $active = PromoCode::factory()->create([
            'agency_id' => $agency->id,
            'code' => 'ACTIVE1',
            'status' => PromoCodeStatus::Active,
            'ends_at' => now()->addWeek(),
        ]);

        $inactive = PromoCode::factory()->inactive()->create([
            'agency_id' => $agency->id,
            'code' => 'OFF1',
        ]);

        $expired = PromoCode::factory()->create([
            'agency_id' => $agency->id,
            'code' => 'OLD1',
            'status' => PromoCodeStatus::Active,
            'ends_at' => now()->subDay(),
        ]);

        $validator = app(PromoCodeValidationService::class);

        $ok = $validator->validate('ACTIVE1', $agency->id, 5000);
        $this->assertTrue($ok['valid']);
        $this->assertSame($active->id, $ok['promo_code']?->id);

        $badInactive = $validator->validate('OFF1', $agency->id);
        $this->assertFalse($badInactive['valid']);
        $this->assertContains('Promo code is not active.', $badInactive['errors']);

        $badExpired = $validator->validate('OLD1', $agency->id);
        $this->assertFalse($badExpired['valid']);
        $this->assertContains('Promo code has expired.', $badExpired['errors']);
    }

    public function test_staff_cannot_access_promo_codes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.promo-codes.index'))->assertForbidden();
    }

    public function test_agent_cannot_access_promo_codes(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)->get(route('admin.promo-codes.index'))->assertForbidden();
    }
}
