<?php

namespace Tests\Feature;

use App\Models\SavedTraveler;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSavedTravelerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_guest_cannot_list_saved_travelers(): void
    {
        $this->getJson(route('booking.saved-travelers.index'))
            ->assertUnauthorized();
    }

    public function test_customer_lists_own_travelers_masked(): void
    {
        $customer = User::factory()->customer()->create();
        SavedTraveler::factory()->create([
            'user_id' => $customer->id,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'document_number' => 'AB1234567',
            'is_default' => true,
        ]);

        $response = $this->actingAs($customer)
            ->getJson(route('booking.saved-travelers.index'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('travelers.0.first_name', 'Ali');

        $this->assertNotNull($response->json('default_traveler_id'));
        $payload = $response->json('travelers.0');
        $this->assertArrayNotHasKey('document_number', $payload);
        $this->assertNotSame('AB1234567', $payload['document_number_masked'] ?? null);
        $this->assertArrayHasKey('document_expiry_status', $payload);
    }

    public function test_customer_cannot_show_another_customers_traveler(): void
    {
        $owner = User::factory()->customer()->create();
        $intruder = User::factory()->customer()->create();
        $traveler = SavedTraveler::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->getJson(route('booking.saved-travelers.show', $traveler))
            ->assertForbidden();
    }

    public function test_owner_can_show_full_document_for_fill(): void
    {
        $customer = User::factory()->customer()->create();
        $traveler = SavedTraveler::factory()->create([
            'user_id' => $customer->id,
            'document_number' => 'PK9988776',
        ]);

        $this->actingAs($customer)
            ->getJson(route('booking.saved-travelers.show', $traveler))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('traveler.document_number', 'PK9988776');
    }

    public function test_staff_cannot_use_checkout_saved_traveler_endpoints(): void
    {
        $staff = User::factory()->staff()->create();
        SavedTraveler::factory()->create(['user_id' => $staff->id]);

        $this->actingAs($staff)
            ->getJson(route('booking.saved-travelers.index'))
            ->assertForbidden();
    }
}
