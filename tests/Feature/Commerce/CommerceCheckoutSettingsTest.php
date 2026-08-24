<?php

namespace Tests\Feature\Commerce;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingFareBreakdown;
use App\Models\CommerceCheckoutSetting;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Commerce\CommerceCheckoutSettingsService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class CommerceCheckoutSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
    }

    private function platformAdmin(): User
    {
        return User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $this->agency->id,
            'email_verified_at' => now(),
        ]);
    }

    private function customer(): User
    {
        return User::query()->where('email', 'customer@ota.demo')->firstOrFail();
    }

    private function configureAbhiPay(): PaymentGateway
    {
        return PaymentGateway::query()->create([
            'agency_id' => $this->agency->id,
            'code' => PaymentGateway::CODE_ABHIPAY,
            'name' => 'AbhiPay',
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'MERCHANT-123',
            'merchant_secret_key' => 'secret-key-test-value',
            'base_url' => PaymentGateway::DEFAULT_BASE_URL,
            'callback_url' => route('payments.abhipay.callback'),
        ]);
    }

    private function setGates(bool $guestBooking, bool $cardPayment): CommerceCheckoutSetting
    {
        return CommerceCheckoutSetting::query()->updateOrCreate(
            ['agency_id' => $this->agency->id],
            [
                'guest_booking_enabled' => $guestBooking,
                'card_payment_enabled' => $cardPayment,
            ],
        );
    }

    private function seedPassengerSession(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'search_id' => 'test-search-1',
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'first_name' => 'Gate',
                'last_name' => 'Test',
                'email' => 'gate-test@example.com',
                'terms_accepted' => '1',
                'terms_version' => (string) config('ota_checkout_consent.terms_version'),
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));
    }

    private function passengersQuery(): string
    {
        $depart = now()->addWeek()->format('Y-m-d');

        return http_build_query([
            'format' => 'json',
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'search_id' => 'test-search-1',
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
            'adults' => 1,
        ]);
    }

    public function test_defaults_are_enabled_when_no_settings_row_exists(): void
    {
        $service = app(CommerceCheckoutSettingsService::class);

        $this->assertTrue($service->isGuestBookingEnabled($this->agency->id));
        $this->assertTrue($service->isCardPaymentEnabled($this->agency->id));

        $this->getJson(route('booking.commerce-gates'))
            ->assertOk()
            ->assertJsonPath('guest_booking_enabled', true)
            ->assertJsonPath('card_payment_enabled', true);
    }

    public function test_admin_can_update_settings_and_writes_audit_log(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->patchJson(route('admin.settings.booking-checkout.update'), [
                'guest_booking_enabled' => false,
                'card_payment_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('settings.guest_booking_enabled', false)
            ->assertJsonPath('settings.card_payment_enabled', false);

        $this->assertDatabaseHas('commerce_checkout_settings', [
            'agency_id' => $this->agency->id,
            'guest_booking_enabled' => 0,
            'card_payment_enabled' => 0,
        ]);

        $this->assertTrue(
            AuditLog::query()->where('action', 'commerce_checkout.settings_updated')->exists()
        );
    }

    public function test_admin_can_read_booking_checkout_settings_json(): void
    {
        $this->setGates(true, false);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson(route('admin.settings.booking-checkout.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('settings.guest_booking_enabled', true)
            ->assertJsonPath('settings.card_payment_enabled', false);
    }

    public function test_guest_gate_toggle_save_reload_restore_leaves_zero_residue(): void
    {
        $admin = $this->platformAdmin();
        $baselineGuest = true;
        $baselineCard = true;
        $this->setGates($baselineGuest, $baselineCard);
        AuditLog::query()->delete();

        $this->actingAs($admin)
            ->getJson(route('admin.settings.booking-checkout.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('settings.guest_booking_enabled', $baselineGuest);

        $this->actingAs($admin)
            ->patchJson(route('admin.settings.booking-checkout.update'), [
                'guest_booking_enabled' => false,
                'card_payment_enabled' => $baselineCard,
            ])
            ->assertOk()
            ->assertJsonPath('settings.guest_booking_enabled', false);

        $this->actingAs($admin)
            ->getJson(route('admin.settings.booking-checkout.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('settings.guest_booking_enabled', false)
            ->assertJsonPath('settings.card_payment_enabled', $baselineCard);

        $this->assertTrue(
            AuditLog::query()->where('action', 'commerce_checkout.settings_updated')->exists()
        );

        $this->actingAs($admin)
            ->patchJson(route('admin.settings.booking-checkout.update'), [
                'guest_booking_enabled' => $baselineGuest,
                'card_payment_enabled' => $baselineCard,
            ])
            ->assertOk()
            ->assertJsonPath('settings.guest_booking_enabled', $baselineGuest);

        $this->actingAs($admin)
            ->getJson(route('admin.settings.booking-checkout.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('settings.guest_booking_enabled', $baselineGuest)
            ->assertJsonPath('settings.card_payment_enabled', $baselineCard);

        $this->assertDatabaseHas('commerce_checkout_settings', [
            'agency_id' => $this->agency->id,
            'guest_booking_enabled' => 1,
            'card_payment_enabled' => 1,
        ]);
    }

    public function test_card_gate_toggle_save_reload_restore_leaves_zero_residue(): void
    {
        $admin = $this->platformAdmin();
        $baselineGuest = true;
        $baselineCard = true;
        $this->setGates($baselineGuest, $baselineCard);
        AuditLog::query()->delete();

        $this->actingAs($admin)
            ->getJson(route('admin.settings.booking-checkout.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('settings.card_payment_enabled', $baselineCard);

        $this->actingAs($admin)
            ->patchJson(route('admin.settings.booking-checkout.update'), [
                'guest_booking_enabled' => $baselineGuest,
                'card_payment_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('settings.card_payment_enabled', false);

        $this->actingAs($admin)
            ->getJson(route('admin.settings.booking-checkout.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('settings.card_payment_enabled', false)
            ->assertJsonPath('settings.guest_booking_enabled', $baselineGuest);

        $this->assertTrue(
            AuditLog::query()->where('action', 'commerce_checkout.settings_updated')->exists()
        );

        $this->actingAs($admin)
            ->patchJson(route('admin.settings.booking-checkout.update'), [
                'guest_booking_enabled' => $baselineGuest,
                'card_payment_enabled' => $baselineCard,
            ])
            ->assertOk()
            ->assertJsonPath('settings.card_payment_enabled', $baselineCard);

        $this->actingAs($admin)
            ->getJson(route('admin.settings.booking-checkout.show', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('settings.card_payment_enabled', $baselineCard)
            ->assertJsonPath('settings.guest_booking_enabled', $baselineGuest);

        $this->assertDatabaseHas('commerce_checkout_settings', [
            'agency_id' => $this->agency->id,
            'guest_booking_enabled' => 1,
            'card_payment_enabled' => 1,
        ]);
    }

    public function test_non_admin_roles_cannot_mutate_booking_checkout_settings(): void
    {
        $this->setGates(true, true);

        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $this->agency->id,
            'email_verified_at' => now(),
        ]);
        $customer = $this->customer();

        $this->actingAs($staff)
            ->patchJson(route('admin.settings.booking-checkout.update'), [
                'guest_booking_enabled' => false,
                'card_payment_enabled' => false,
            ])
            ->assertForbidden();

        $this->actingAs($customer)
            ->patchJson(route('admin.settings.booking-checkout.update'), [
                'guest_booking_enabled' => false,
                'card_payment_enabled' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('commerce_checkout_settings', [
            'agency_id' => $this->agency->id,
            'guest_booking_enabled' => 1,
            'card_payment_enabled' => 1,
        ]);
    }

    public function test_guest_booking_disabled_blocks_unauthenticated_passengers_json(): void
    {
        $this->setGates(false, true);
        PublicCheckoutTestDoubles::bind($this, now()->addWeek()->format('Y-m-d'), 'LHE', 'DXB');

        $this->getJson('/booking/passengers?'.$this->passengersQuery())
            ->assertUnauthorized()
            ->assertJsonPath('status', 'guest_booking_disabled');
    }

    public function test_guest_booking_enabled_allows_unauthenticated_passengers_json(): void
    {
        $this->setGates(true, true);
        PublicCheckoutTestDoubles::bind($this, now()->addWeek()->format('Y-m-d'), 'LHE', 'DXB');

        $this->getJson('/booking/passengers?'.$this->passengersQuery())
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_guest_booking_disabled_does_not_block_authenticated_customer(): void
    {
        $this->setGates(false, true);
        PublicCheckoutTestDoubles::bind($this, now()->addWeek()->format('Y-m-d'), 'LHE', 'DXB');

        $this->actingAs($this->customer())
            ->getJson('/booking/passengers?'.$this->passengersQuery())
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_card_payment_disabled_hides_card_on_review_even_when_abhipay_active(): void
    {
        $this->setGates(true, false);
        $this->configureAbhiPay();
        $this->seedPassengerSession();

        $methods = collect($this->getJson('/booking/review?format=json')->json('payment_methods'))->pluck('code')->all();

        $this->assertContains('manual', $methods);
        $this->assertNotContains('card', $methods);
    }

    public function test_card_payment_enabled_shows_card_on_review_when_abhipay_active(): void
    {
        $this->setGates(true, true);
        $this->configureAbhiPay();
        $this->seedPassengerSession();

        $methods = collect($this->getJson('/booking/review?format=json')->json('payment_methods'))->pluck('code')->all();

        $this->assertContains('manual', $methods);
        $this->assertContains('card', $methods);
    }

    public function test_card_payment_disabled_rejects_abhipay_start_with_clear_message(): void
    {
        $this->setGates(true, false);
        $this->configureAbhiPay();

        $booking = Booking::factory()->for($this->agency)->create([
            'customer_id' => $this->customer()->id,
            'booking_reference' => 'CARDGATE1',
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'currency' => 'PKR',
        ]);

        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 50000,
            'taxes' => 0,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 50000,
            'currency' => 'PKR',
        ]);

        $this->actingAs($this->customer())
            ->postJson(route('payments.abhipay.start', $booking))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Card payments are currently disabled.');
    }

    public function test_review_submit_rejects_online_card_when_card_payment_disabled(): void
    {
        $this->setGates(true, false);
        $this->configureAbhiPay();
        $this->seedPassengerSession();

        $this->postJson('/booking/review?format=json', [
            'booking_method' => 'online_card',
        ])->assertStatus(422);

        $booking = Booking::query()->firstOrFail();
        $this->assertNull($booking->submitted_at);
        $this->assertSame(BookingStatus::Draft, $booking->status);
    }

    #[DataProvider('gateMatrixProvider')]
    public function test_gate_matrix_for_public_commerce_gates_endpoint(bool $guest, bool $card): void
    {
        $this->setGates($guest, $card);

        $this->getJson(route('booking.commerce-gates'))
            ->assertOk()
            ->assertJsonPath('guest_booking_enabled', $guest)
            ->assertJsonPath('card_payment_enabled', $card);
    }

    /**
     * @return array<string, array{0: bool, 1: bool}>
     */
    public static function gateMatrixProvider(): array
    {
        return [
            'guest_on_card_on' => [true, true],
            'guest_on_card_off' => [true, false],
            'guest_off_card_on' => [false, true],
            'guest_off_card_off' => [false, false],
        ];
    }

    public function test_public_config_includes_commerce_gates(): void
    {
        $this->setGates(false, true);

        $this->getJson(route('api.public.content.config'))
            ->assertOk()
            ->assertJsonPath('commerce_gates.guest_booking_enabled', false)
            ->assertJsonPath('commerce_gates.card_payment_enabled', true);
    }
}
