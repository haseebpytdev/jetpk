<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingPayment;
use App\Models\GuestBookingAccessToken;
use App\Models\User;
use App\Services\Customer\GuestBookingAccessService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPortalAndGuestLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);
    }

    public function test_customer_can_view_own_booking(): void
    {
        [$customer, $booking] = $this->customerBooking();

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))->assertOk();
    }

    public function test_customer_cannot_view_another_customer_booking(): void
    {
        [$customer] = $this->customerBooking();
        [, $otherBooking] = $this->customerBooking();

        $this->actingAs($customer)->get(route('customer.bookings.show', $otherBooking))->assertForbidden();
    }

    public function test_customer_can_download_own_booking_document(): void
    {
        [$customer, $booking] = $this->customerBooking();
        $document = $this->documentForBooking($booking);

        $this->actingAs($customer)->get(route('customer.documents.download', $document))->assertOk();
    }

    public function test_customer_cannot_download_another_booking_document(): void
    {
        [$customer] = $this->customerBooking();
        [, $otherBooking] = $this->customerBooking();
        $document = $this->documentForBooking($otherBooking);

        $this->actingAs($customer)->get(route('customer.documents.download', $document))->assertForbidden();
    }

    public function test_customer_can_submit_payment_proof_for_own_booking(): void
    {
        [$customer, $booking] = $this->customerBooking();

        $this->actingAs($customer)->post(route('customer.bookings.payment-proof', $booking), [
            'method' => 'bank_transfer',
            'amount' => 1000,
            'payment_reference' => 'CUST-1',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_payments', ['booking_id' => $booking->id, 'status' => 'submitted']);
    }

    public function test_guest_lookup_requires_booking_reference_plus_matching_email(): void
    {
        [, $booking] = $this->customerBooking();
        $booking->contact()->update(['email' => 'guestmatch@example.test']);

        $this->post(route('lookup-booking.submit'), [
            'booking_reference' => $booking->booking_reference,
            'email' => 'guestmatch@example.test',
        ])->assertRedirectContains('/guest/bookings/'.$booking->id.'/access/');
    }

    public function test_guest_lookup_rejects_reference_only_access(): void
    {
        [, $booking] = $this->customerBooking();

        $this->post(route('lookup-booking.submit'), [
            'booking_reference' => $booking->booking_reference,
        ])->assertSessionHasErrors('email');
    }

    public function test_guest_access_token_is_hashed_at_rest(): void
    {
        [, $booking] = $this->customerBooking();
        $raw = app(GuestBookingAccessService::class)->createTokenForBooking($booking, 'a@example.test', null);
        $stored = GuestBookingAccessToken::query()->where('booking_id', $booking->id)->latest('id')->firstOrFail();

        $this->assertNotSame($raw, $stored->token_hash);
        $this->assertSame(hash('sha256', $raw), $stored->token_hash);
    }

    public function test_valid_guest_token_allows_booking_view(): void
    {
        [, $booking] = $this->customerBooking();
        $token = app(GuestBookingAccessService::class)->createTokenForBooking($booking, 'a@example.test', null);

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))->assertOk();
    }

    public function test_expired_guest_token_is_denied(): void
    {
        [, $booking] = $this->customerBooking();
        $token = app(GuestBookingAccessService::class)->createTokenForBooking($booking, 'a@example.test', null);
        GuestBookingAccessToken::query()->where('booking_id', $booking->id)->update(['expires_at' => now()->subMinute()]);

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))->assertForbidden();
    }

    public function test_guest_can_download_allowed_document_with_valid_token(): void
    {
        [, $booking] = $this->customerBooking();
        $document = $this->documentForBooking($booking);
        $token = app(GuestBookingAccessService::class)->createTokenForBooking($booking, 'a@example.test', null);

        $this->get(route('guest.documents.download', ['bookingDocument' => $document, 'token' => $token]))->assertOk();
    }

    public function test_guest_cannot_access_admin_staff_internal_notes(): void
    {
        [, $booking] = $this->customerBooking();
        $booking->bookingNotes()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => null,
            'note_type' => 'internal',
            'note' => 'Internal Admin Secret Note',
            'is_customer_visible' => false,
        ]);
        $token = app(GuestBookingAccessService::class)->createTokenForBooking($booking, 'a@example.test', null);

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertDontSee('Internal Admin Secret Note');
    }

    public function test_customer_portal_does_not_expose_audit_logs_or_raw_supplier_payload(): void
    {
        [$customer, $booking] = $this->customerBooking([
            'meta' => ['supplier_payload' => ['token' => 'secret_token_value']],
        ]);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('audit_logs')
            ->assertDontSee('secret_token_value');
    }

    public function test_public_lookup_routes_remain_unauthenticated(): void
    {
        $this->get(route('booking.lookup'))->assertOk();
        $this->post(route('lookup-booking.submit'), ['booking_reference' => 'ABC'])->assertSessionHasErrors('email');
    }

    public function test_customer_routes_remain_authenticated_and_customer_only(): void
    {
        [, $booking] = $this->customerBooking();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->get(route('customer.bookings.index'))->assertRedirect();
        $this->actingAs($staff)->get(route('customer.bookings.show', $booking))->assertForbidden();
    }

    public function test_customer_dashboard_shows_kpis_and_recent_bookings(): void
    {
        [$customer, $booking] = $this->customerBooking();

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('data-testid="customer-dashboard-kpis"', false)
            ->assertSee('Total bookings', false)
            ->assertSee('Pending payment', false)
            ->assertSee($booking->display_reference, false);
    }

    public function test_customer_bookings_index_supports_pending_payment_filter(): void
    {
        [$customer] = $this->customerBooking(['payment_status' => 'unpaid']);
        [, $paidBooking] = $this->customerBooking([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'balance_due' => 0,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($customer)->get(route('customer.bookings.index', ['filter' => 'pending_payment']))
            ->assertOk()
            ->assertSee('data-testid="customer-bookings-filters"', false)
            ->assertSee('ota-bstat', false)
            ->assertDontSee($paidBooking->booking_reference, false);
    }

    public function test_customer_booking_show_labels_pnr_synced_itinerary(): void
    {
        [$customer, $booking] = $this->customerBooking([
            'pnr' => 'ABC123',
            'meta' => [
                'pnr_itinerary_snapshot' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'DXB',
                            'departure_at' => '2026-06-01T08:00:00+05:00',
                            'arrival_at' => '2026-06-01T10:30:00+05:00',
                            'airline_code' => 'PK',
                            'flight_number' => '233',
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('PNR/airline itinerary', false)
            ->assertSee('data-testid="customer-itinerary-source"', false);
    }

    public function test_customer_booking_show_warns_when_pnr_without_synced_snapshot(): void
    {
        [$customer, $booking] = $this->customerBooking([
            'pnr' => 'XYZ999',
            'meta' => [
                'flight_offer_snapshot' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'DXB',
                            'departure_at' => '2026-06-01T08:00:00+05:00',
                            'arrival_at' => '2026-06-01T10:30:00+05:00',
                            'airline_code' => 'PK',
                            'flight_number' => '233',
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="customer-itinerary-snapshot-warning"', false)
            ->assertSee('final airline itinerary not yet synced', false);
    }

    public function test_customer_booking_show_operational_pnr_sync_success_without_tickets(): void
    {
        [$customer, $booking] = $this->customerBooking($this->operationalPnrSyncOverrides());

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="customer-booking-timeline"', false)
            ->assertSee('PNR / airline booking', false)
            ->assertSee('PNR: TQMNEV', false)
            ->assertSee('PNR/airline itinerary', false)
            ->assertSee('data-testid="customer-itinerary-source"', false)
            ->assertSee('Final airline itinerary synced from PNR.', false)
            ->assertSee('Ticketing process started.', false)
            ->assertDontSee('data-testid="booking-pnr-ticketing"', false);
    }

    public function test_guest_booking_show_operational_pnr_sync_success_without_tickets(): void
    {
        [, $booking] = $this->customerBooking(array_merge($this->operationalPnrSyncOverrides(), [
            'customer_id' => null,
        ]));
        $booking->contact()->update(['email' => 'guestops@example.test']);

        $token = app(GuestBookingAccessService::class)->createTokenForBooking(
            $booking->fresh(['contact']),
            'guestops@example.test',
            null,
        );

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="customer-booking-timeline"', false)
            ->assertSee('PNR / airline booking', false)
            ->assertSee('PNR: TQMNEV', false)
            ->assertSee('PNR/airline itinerary', false)
            ->assertSee('Final airline itinerary synced from PNR.', false)
            ->assertSee('Ticketing process started.', false)
            ->assertDontSee('data-testid="booking-pnr-ticketing"', false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function operationalPnrSyncOverrides(): array
    {
        return [
            'pnr' => 'TQMNEV',
            'supplier_reference' => 'TQMNEV',
            'supplier_booking_status' => 'pending_payment_or_ticketing',
            'payment_status' => 'unpaid',
            'ticketing_status' => 'pending',
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_sync' => [
                    'status' => 'synced',
                    'is_ticketed' => false,
                ],
                'pnr_itinerary_snapshot' => [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'segments' => [
                        [
                            'origin' => 'LHE',
                            'destination' => 'BAH',
                            'departure_at' => '2026-07-29T08:00:00+05:00',
                            'arrival_at' => '2026-07-29T10:00:00+05:00',
                            'airline_code' => 'GF',
                            'flight_number' => '765',
                            'segment_status' => 'HK',
                        ],
                        [
                            'origin' => 'BAH',
                            'destination' => 'DXB',
                            'departure_at' => '2026-07-29T14:00:00+05:00',
                            'arrival_at' => '2026-07-29T16:30:00+05:00',
                            'airline_code' => 'GF',
                            'flight_number' => '500',
                            'segment_status' => 'HK',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_payment_proof_form_only_when_eligible(): void
    {
        [$customer, $unpaid] = $this->customerBooking(['payment_status' => 'unpaid', 'balance_due' => 5000]);
        [, $paid] = $this->customerBooking([
            'customer_id' => $customer->id,
            'payment_status' => 'paid',
            'balance_due' => 0,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($customer)->get(route('customer.bookings.show', $unpaid))
            ->assertOk()
            ->assertSee('data-testid="customer-payment-proof-form"', false)
            ->assertSee('data-testid="booking-payment-summary"', false);

        $this->actingAs($customer)->get(route('customer.bookings.show', $paid))
            ->assertOk()
            ->assertDontSee('data-testid="customer-payment-proof-form"', false)
            ->assertSee('data-testid="booking-payment-verified"', false);
    }

    public function test_customer_pending_proof_hides_upload_form(): void
    {
        [$customer, $booking] = $this->customerBooking(['payment_status' => 'unpaid', 'balance_due' => 5000]);
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 5000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="booking-payment-awaiting-review"', false)
            ->assertDontSee('data-testid="customer-payment-proof-form"', false);
    }

    public function test_guest_booking_show_includes_payment_summary_without_internal_notes(): void
    {
        [, $booking] = $this->customerBooking(['payment_status' => 'unpaid', 'balance_due' => 3000]);
        BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'bank_transfer',
            'status' => 'submitted',
            'amount' => 3000,
            'currency' => 'PKR',
            'notes' => 'Internal staff note must not show',
            'meta' => ['rejection_reason' => 'Invalid transfer'],
            'submitted_at' => now(),
        ]);
        $token = app(GuestBookingAccessService::class)->createTokenForBooking($booking, 'a@example.test', null);

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="booking-payment-summary"', false)
            ->assertSee('data-testid="booking-documents-center"', false)
            ->assertDontSee('Internal staff note must not show')
            ->assertDontSee('Invalid transfer');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Booking}
     */
    protected function customerBooking(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);
        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
        ], $overrides));
        $booking->contact()->create([
            'email' => 'customer@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
        ]);
        $booking->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
        ]);

        return [$customer, $booking->fresh(['contact', 'passengers'])];
    }

    protected function documentForBooking(Booking $booking): BookingDocument
    {
        $path = 'private/agency-'.$booking->agency_id.'/bookings/'.$booking->id.'/documents/test.pdf';
        Storage::disk('local')->put($path, 'PDF FILE');

        return BookingDocument::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'document_type' => BookingDocumentType::BookingConfirmation,
            'document_number' => 'BC-'.$booking->booking_reference,
            'title' => 'Booking Confirmation',
            'file_path' => $path,
            'status' => BookingDocumentStatus::Generated,
            'generated_by' => $booking->customer_id,
            'generated_at' => now(),
        ]);
    }
}
