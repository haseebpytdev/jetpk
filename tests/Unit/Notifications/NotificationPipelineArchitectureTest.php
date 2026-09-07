<?php

namespace Tests\Unit\Notifications;

use App\Enums\OtaNotificationEvent;
use App\Models\NotificationDelivery;
use App\Models\NotificationOutbox;
use App\Models\NotificationRoute;
use App\Services\Notifications\NotificationDeliveryService;
use App\Services\Notifications\NotificationIdempotency;
use App\Services\Notifications\NotificationOutboxService;
use App\Services\Notifications\NotificationRouteResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationPipelineArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_event_id_is_unique_and_duplicate_record_is_noop(): void
    {
        $service = app(NotificationOutboxService::class);
        $first = $service->record('admin_login_success', ['note' => 'a'], eventId: '11111111-1111-1111-1111-111111111111');
        $second = $service->record('admin_login_success', ['note' => 'b'], eventId: '11111111-1111-1111-1111-111111111111');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, NotificationOutbox::query()->count());
    }

    public function test_idempotency_key_is_stable_and_database_unique(): void
    {
        $key = NotificationIdempotency::key('evt-1', 'email', 'logged_in_user', 'Admin@Example.com');
        $again = NotificationIdempotency::key('evt-1', 'email', 'logged_in_user', 'admin@example.com');
        $this->assertSame($key, $again);

        $deliveries = app(NotificationDeliveryService::class);
        $a = $deliveries->firstOrCreatePending('evt-1', 'admin_login_success', 'logged_in_user', 'admin@example.com', 1, 'default', 'Critical');
        $b = $deliveries->firstOrCreatePending('evt-1', 'admin_login_success', 'logged_in_user', 'ADMIN@example.com', 1, 'default', 'Critical');

        $this->assertTrue($a['created']);
        $this->assertFalse($b['created']);
        $this->assertSame($a['delivery']->id, $b['delivery']->id);
        $this->assertSame(1, NotificationDelivery::query()->count());
    }

    public function test_two_recipients_get_distinct_keys(): void
    {
        $deliveries = app(NotificationDeliveryService::class);
        $a = $deliveries->firstOrCreatePending('evt-2', 'booking_confirmed', 'admin', 'a@example.com', 1, 'default', 'Transactional');
        $b = $deliveries->firstOrCreatePending('evt-2', 'booking_confirmed', 'admin', 'b@example.com', 1, 'default', 'Transactional');

        $this->assertNotSame($a['delivery']->idempotency_key, $b['delivery']->idempotency_key);
        $this->assertSame(2, NotificationDelivery::query()->count());
    }

    public function test_same_recipient_different_events_are_distinct(): void
    {
        $deliveries = app(NotificationDeliveryService::class);
        $a = $deliveries->firstOrCreatePending('evt-3', 'admin_login_success', 'logged_in_user', 'a@example.com', 1, 'default', 'Critical');
        $b = $deliveries->firstOrCreatePending('evt-4', 'staff_login_success', 'logged_in_user', 'a@example.com', 1, 'default', 'Critical');
        $this->assertNotSame($a['delivery']->idempotency_key, $b['delivery']->idempotency_key);
    }

    public function test_retry_after_sent_does_not_create_second_row(): void
    {
        $deliveries = app(NotificationDeliveryService::class);
        $first = $deliveries->firstOrCreatePending('evt-5', 'admin_login_success', 'logged_in_user', 'a@example.com', 1, 'default', 'Critical');
        $deliveries->markSent($first['delivery']);
        $second = $deliveries->firstOrCreatePending('evt-5', 'admin_login_success', 'logged_in_user', 'a@example.com', 1, 'default', 'Critical');
        $this->assertFalse($second['created']);
        $this->assertSame('sent', $second['delivery']->fresh()->status);
    }

    public function test_rolled_back_transaction_does_not_leave_outbox(): void
    {
        try {
            DB::transaction(function (): void {
                app(NotificationOutboxService::class)->record('admin_login_success', ['x' => 1], eventId: '22222222-2222-2222-2222-222222222222');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, NotificationOutbox::query()->count());
    }

    public function test_database_routes_cover_login_events_without_legacy_fallback(): void
    {
        $this->assertGreaterThan(0, NotificationRoute::query()->count());
        $resolved = app(NotificationRouteResolver::class)->audiencesFor(OtaNotificationEvent::AdminLoginSuccess->value);
        $this->assertFalse($resolved['fallback']);
        $this->assertSame(['logged_in_user'], $resolved['audiences']);
    }

    public function test_payload_does_not_persist_otp_or_password(): void
    {
        $row = app(NotificationOutboxService::class)->record('login_otp', [
            'otp' => '123456',
            'password' => 'secret',
            'note' => 'ok',
        ]);
        $this->assertArrayNotHasKey('otp', $row->payload);
        $this->assertArrayNotHasKey('password', $row->payload);
        $this->assertSame('ok', $row->payload['note']);
    }
}
