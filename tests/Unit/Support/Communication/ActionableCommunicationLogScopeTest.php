<?php

namespace Tests\Unit\Support\Communication;

use App\Models\Agency;
use App\Models\CommunicationLog;
use App\Support\Communication\ActionableCommunicationLogScope;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionableCommunicationLogScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_login_noise_is_historical_not_actionable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agencyId = Agency::query()->firstOrFail()->id;

        CommunicationLog::query()->create([
            'agency_id' => $agencyId,
            'channel' => 'email',
            'event' => 'admin_login_success',
            'status' => 'queued',
            'recipient_email' => 'ops@example.test',
            'subject' => 'login',
            'message' => 'noise',
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        $this->assertSame(0, ActionableCommunicationLogScope::actionableQueued(CommunicationLog::query())->count());
        $this->assertSame(1, ActionableCommunicationLogScope::historicalQueuedResidual(CommunicationLog::query())->count());
    }

    public function test_old_booking_notification_remains_actionable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agencyId = Agency::query()->firstOrFail()->id;

        CommunicationLog::query()->create([
            'agency_id' => $agencyId,
            'channel' => 'email',
            'event' => 'booking_confirmation',
            'status' => 'queued',
            'recipient_email' => 'customer@example.test',
            'subject' => 'booking',
            'message' => 'legit',
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        $this->assertSame(1, ActionableCommunicationLogScope::actionableQueued(CommunicationLog::query())->count());
        $this->assertSame(0, ActionableCommunicationLogScope::historicalQueuedResidual(CommunicationLog::query())->count());
    }

    public function test_thirty_day_payment_notification_remains_actionable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agencyId = Agency::query()->firstOrFail()->id;

        CommunicationLog::query()->create([
            'agency_id' => $agencyId,
            'channel' => 'email',
            'event' => 'payment_verified',
            'status' => 'queued',
            'recipient_email' => 'customer@example.test',
            'subject' => 'payment',
            'message' => 'legit',
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ]);

        $this->assertSame(1, ActionableCommunicationLogScope::actionableQueued(CommunicationLog::query())->count());
    }

    public function test_sent_row_is_not_actionable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agencyId = Agency::query()->firstOrFail()->id;

        CommunicationLog::query()->create([
            'agency_id' => $agencyId,
            'channel' => 'email',
            'event' => 'booking_confirmation',
            'status' => 'queued',
            'recipient_email' => 'customer@example.test',
            'subject' => 'booking',
            'message' => 'sent',
            'sent_at' => now()->subDay(),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDay(),
        ]);

        $this->assertSame(0, ActionableCommunicationLogScope::actionableQueued(CommunicationLog::query())->count());
    }

    public function test_failed_status_is_not_queued_actionable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agencyId = Agency::query()->firstOrFail()->id;

        CommunicationLog::query()->create([
            'agency_id' => $agencyId,
            'channel' => 'email',
            'event' => 'booking_confirmation',
            'status' => 'failed',
            'recipient_email' => 'customer@example.test',
            'subject' => 'booking',
            'message' => 'fail',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->assertSame(0, ActionableCommunicationLogScope::actionableQueued(CommunicationLog::query())->count());
    }
}
