<?php

namespace Tests\Feature\Ops;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Ops\OpsInboxService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class JpOps08IsolationAndStaleStateTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_agency_b_staff_does_not_receive_agency_a_assignment_inbox(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agencyA = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agencyB = Agency::query()->create([
            'name' => 'JP OPS08 Agency B',
            'slug' => 'jp-ops08-agency-b',
            'is_active' => true,
        ]);

        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agencyA->id])->save();

        $staffA = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agencyA->id,
        ]);
        $staffB = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agencyB->id,
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agencyA->id,
            'status' => BookingStatus::PaymentPending,
            'booking_reference' => 'JP-OPS08-ISO-'.fake()->unique()->numberBetween(100, 999),
        ]);

        app(BookingService::class)->assignStaff($booking, $staffA, $admin);

        $inbox = app(OpsInboxService::class);
        $this->assertSame(1, $inbox->unreadCount($staffA->fresh()));
        $this->assertSame(0, $inbox->unreadCount($staffB->fresh()));

        $this->actingAs($staffB)
            ->getJson(route('api.dashboard.ops.work-queue'))
            ->assertOk()
            ->assertJsonMissing(['reference' => $booking->booking_reference]);
    }

    public function test_reassignment_updates_responsibility_and_does_not_duplicate_prior_assignee_unread_wrongly(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();

        $staffOne = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
        ]);
        $staffTwo = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'booking_reference' => 'JP-OPS08-STALE-'.fake()->unique()->numberBetween(100, 999),
        ]);

        $service = app(BookingService::class);
        $service->assignStaff($booking, $staffOne, $admin);
        $service->assignStaff($booking->fresh(), $staffTwo, $admin);

        $booking->refresh();
        $this->assertSame($staffTwo->id, $booking->assigned_staff_id);

        $inbox = app(OpsInboxService::class);
        $this->assertSame(1, $inbox->unreadCount($staffOne->fresh()));
        $this->assertSame(1, $inbox->unreadCount($staffTwo->fresh()));

        $queueTwo = $this->actingAs($staffTwo)->getJson(route('api.dashboard.ops.work-queue'))->json('data.bookings');
        $refsTwo = collect($queueTwo)->pluck('reference')->all();
        $this->assertContains($booking->booking_reference, $refsTwo);

        $queueOne = $this->actingAs($staffOne)->getJson(route('api.dashboard.ops.work-queue'))->json('data.bookings');
        $refsOne = collect($queueOne)->pluck('reference')->all();
        $this->assertNotContains($booking->booking_reference, $refsOne);
    }

    public function test_customer_cannot_see_internal_note_event_payload_in_inbox_api(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();
        $admin->forceFill(['current_agency_id' => $agency->id])->save();
        $staff = User::factory()->create([
            'account_type' => AccountType::Staff,
            'current_agency_id' => $agency->id,
        ]);
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'assigned_staff_id' => $staff->id,
            'status' => BookingStatus::PaymentPending,
            'booking_reference' => 'JP-OPS08-PRIV-'.fake()->unique()->numberBetween(100, 999),
        ]);

        app(BookingService::class)->addInternalNote($booking, $staff, 'SECRET INTERNAL NOTE', false);

        $this->actingAs($customer)
            ->getJson(route('customer.notifications.index'))
            ->assertOk()
            ->assertJsonMissing(['summary' => 'SECRET INTERNAL NOTE'])
            ->assertDontSee('SECRET INTERNAL NOTE');
    }
}
