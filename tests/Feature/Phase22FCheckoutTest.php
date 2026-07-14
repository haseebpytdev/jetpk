<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\Travel\TravelDocumentFormatter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class Phase22FCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return string Depart date (Y-m-d) aligned with mocked offers.
     */
    protected function seedOtaWithCheckoutDoubles(string $from = 'LHE', string $to = 'DXB'): string
    {
        $depart = now()->addWeek()->format('Y-m-d');
        $this->seed(OtaFoundationSeeder::class);
        PublicCheckoutTestDoubles::bind($this, $depart, $from, $to);

        return $depart;
    }

    public function test_checkout_sign_in_link_contains_redirect_with_offer_context(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $oid = PublicCheckoutTestDoubles::OFFER_ID;
        $url = '/booking/passengers?flight_id='.$oid.'&offer_id='.$oid.'&search_id=sid-test&from=LHE&to=DXB&depart='.$depart;

        $this->get($url)->assertOk()
            ->assertSee('redirect=', false)
            ->assertSee('sid-test', false);
    }

    public function test_open_redirect_to_external_host_is_rejected_for_redirect_query(): void
    {
        $this->get('/login?redirect='.urlencode('https://evil.example/phish'))->assertOk();

        $this->assertNull(session()->get('url.intended'));
    }

    public function test_logged_in_customer_booking_post_sets_customer_id(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->customer()->create([
            'current_agency_id' => $agency->id,
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($customer)->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'logged-in@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($customer->id, $booking->customer_id);
    }

    public function test_guest_checkout_without_account_leaves_customer_id_null(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'pure-guest@example.com',
                'create_account' => '0',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $this->assertNull(Booking::query()->firstOrFail()->customer_id);
    }

    public function test_inline_registration_creates_customer_and_sets_booking_customer_id(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $email = 'inline-new@example.com';
        $password = 'SecurePass99!';

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => $email,
                'create_account' => '1',
                'password' => $password,
                'password_confirmation' => $password,
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($user->isCustomer());

        $booking = Booking::query()->firstOrFail();
        $this->assertSame($user->id, $booking->customer_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_inline_registration_rejects_duplicate_email(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $existing = User::factory()->customer()->create([
            'email' => 'exists@example.com',
            'current_agency_id' => $agency->id,
        ]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => $existing->email,
                'create_account' => '1',
                'password' => 'SecurePass99!',
                'password_confirmation' => 'SecurePass99!',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertSessionHasErrors('email');
    }

    public function test_international_route_requires_passport_fields(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', PublicBookingPassengersPayload::merge([
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
            'email' => 'intl-check@example.com',
        ]))->assertSessionHasErrors(['passport_number', 'passport_issuing_country', 'passport_expiry_date']);
    }

    public function test_domestic_route_does_not_require_passport_by_default(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles('LHE', 'KHI');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', PublicBookingPassengersPayload::merge([
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'from' => 'LHE',
            'to' => 'KHI',
            'depart' => $depart,
            'email' => 'domestic@example.com',
            'document_type' => 'national_id',
            'national_id_number' => '35202-1234567-1',
            'passport_number' => '',
            'passport_issuing_country' => '',
            'passport_expiry_date' => '',
            'passport_issue_date' => '',
            'nationality' => '',
        ]))->assertRedirect(route('booking.review'));
    }

    public function test_passport_expiry_must_be_in_the_future(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'expiry@example.com',
                'passport_number' => 'AB1112223',
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => now()->subYear()->format('Y-m-d'),
                'passport_issue_date' => '2018-01-15',
            ]),
        ))->assertSessionHasErrors('passport_expiry_date');
    }

    public function test_date_of_birth_must_be_before_today(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'dob@example.com',
                'dob' => now()->format('Y-m-d'),
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertSessionHasErrors('dob');
    }

    public function test_review_page_shows_masked_passport_only(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $passport = 'ZZ8877665';

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'mask-review@example.com',
                'passport_number' => $passport,
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => now()->addYears(5)->format('Y-m-d'),
                'passport_issue_date' => '2018-01-15',
            ]),
        ))->assertRedirect(route('booking.review'));

        $masked = TravelDocumentFormatter::maskPassport($passport);
        $this->assertNotSame($passport, $masked);

        $this->get(route('booking.review'))
            ->assertOk()
            ->assertSee($masked, false)
            ->assertDontSee($passport, false);
    }

    public function test_confirmation_page_does_not_show_full_passport(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $passport = 'QQ5544332';

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'confirm-mask@example.com',
                'passport_number' => $passport,
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => now()->addYears(5)->format('Y-m-d'),
                'passport_issue_date' => '2018-01-15',
            ]),
        ));
        $this->post('/booking/review', ['booking_method' => 'pay_later']);

        $this->get(route('booking.confirmation'))
            ->assertOk()
            ->assertDontSee($passport, false);
    }

    public function test_admin_booking_detail_shows_full_passport_number(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $passport = 'ADM123456FULL';

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'admin-doc@example.com',
                'passport_number' => $passport,
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => now()->addYears(5)->format('Y-m-d'),
                'passport_issue_date' => '2018-01-15',
            ]),
        ));
        $this->post('/booking/review', ['booking_method' => 'pay_later']);

        $booking = Booking::query()->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee($passport, false);
    }

    public function test_customer_portal_masks_passport_on_booking_page(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->customer()->create(['current_agency_id' => $agency->id]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $passport = 'CU222221111';

        $this->actingAs($customer)->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => $customer->email,
                'passport_number' => $passport,
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => now()->addYears(5)->format('Y-m-d'),
                'passport_issue_date' => '2018-01-15',
            ]),
        ));
        $this->post('/booking/review', ['booking_method' => 'pay_later']);

        $booking = Booking::query()->firstOrFail();
        $masked = TravelDocumentFormatter::maskPassport($passport);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee($masked, false)
            ->assertDontSee($passport, false);
    }

    public function test_audit_and_communication_logs_do_not_store_raw_passport(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $passport = 'LOGSECRET999';

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'audit-check@example.com',
                'passport_number' => $passport,
                'passport_issuing_country' => 'PK',
                'passport_expiry_date' => now()->addYears(5)->format('Y-m-d'),
                'passport_issue_date' => '2018-01-15',
            ]),
        ));
        $this->post('/booking/review', ['booking_method' => 'pay_later']);

        $booking = Booking::query()->firstOrFail();

        $auditBlob = json_encode(AuditLog::query()->where('auditable_id', $booking->id)->get()->toArray());
        $commBlob = json_encode(CommunicationLog::query()->where('booking_id', $booking->id)->get()->toArray());

        $this->assertStringNotContainsString($passport, $auditBlob);
        $this->assertStringNotContainsString($passport, $commBlob);
    }

    public function test_expired_hold_blocks_final_confirmation_and_requires_recheck(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'hold-expired@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $booking = Booking::query()->firstOrFail();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['payment_required_by'] = now()->subMinutes(10)->toIso8601String();
        $meta['protection_mode'] = 'instant_payment_required';
        $booking->forceFill(['meta' => $meta])->save();

        $this->post('/booking/review', ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors('flight_id');
    }

    public function test_price_changed_requires_explicit_acceptance_before_submit(): void
    {
        $depart = $this->seedOtaWithCheckoutDoubles();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'email' => 'price-confirm@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));

        $booking = Booking::query()->firstOrFail();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['protection_mode'] = 'hold_price_guaranteed';
        $meta['requires_price_change_confirmation'] = true;
        $meta['price_change_old_total'] = 450000;
        $meta['price_change_new_total'] = 462500;
        $booking->forceFill(['meta' => $meta])->save();

        $this->post('/booking/review', ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.review'))
            ->assertSessionHasErrors('confirm_updated_fare');
    }
}
