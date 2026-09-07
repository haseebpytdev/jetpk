<?php

namespace Tests\Unit\Notifications;

use App\Enums\OtaNotificationEvent;
use App\Jobs\Notifications\DispatchNotificationOutboxEvent;
use App\Models\Agency;
use App\Models\NotificationDelivery;
use App\Models\NotificationOutbox;
use App\Models\NotificationRoute;
use App\Services\Notifications\NotificationDeliveryService;
use App\Services\Notifications\NotificationEventIdentity;
use App\Services\Notifications\NotificationIdempotency;
use App\Services\Notifications\NotificationOutboxService;
use App\Services\Notifications\NotificationPipeline;
use App\Services\Notifications\NotificationRouteResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

    public function test_outbox_duplicate_key_insert_path_returns_winner_without_throwing(): void
    {
        $service = app(NotificationOutboxService::class);
        $eventId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $first = $service->record('booking_confirmed', ['n' => 1], eventId: $eventId);
        $uncaught = 0;
        try {
            $second = $service->record('booking_confirmed', ['n' => 2], eventId: $eventId);
        } catch (\Throwable) {
            $uncaught++;
            $second = null;
        }

        $this->assertSame(0, $uncaught);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, NotificationOutbox::query()->where('event_id', $eventId)->count());
    }

    public function test_delivery_duplicate_key_insert_path_returns_winner(): void
    {
        $deliveries = app(NotificationDeliveryService::class);
        $a = $deliveries->firstOrCreatePending('evt-dup', 'booking_confirmed', 'admin', 'a@example.com', 1, 'notifications-transactional', 'Transactional');
        $uncaught = 0;
        try {
            $b = $deliveries->firstOrCreatePending('evt-dup', 'booking_confirmed', 'admin', 'a@example.com', 1, 'notifications-transactional', 'Transactional');
        } catch (\Throwable) {
            $uncaught++;
            $b = null;
        }

        $this->assertSame(0, $uncaught);
        $this->assertNotNull($b);
        $this->assertFalse($b['created']);
        $this->assertSame($a['delivery']->id, $b['delivery']->id);
        $this->assertSame(1, NotificationDelivery::query()->count());
    }

    public function test_semantic_event_identity_is_stable_and_login_is_occurrence_scoped(): void
    {
        $a = NotificationEventIdentity::resolve('booking_confirmed', null, 'booking', '123');
        $b = NotificationEventIdentity::resolve('booking_confirmed', null, 'booking', '123');
        $this->assertSame('one_shot_aggregate', $a['source']);
        $this->assertSame($a['event_id'], $b['event_id']);

        $loginA = NotificationEventIdentity::resolve('admin_login_success', null, 'user', '9');
        $loginB = NotificationEventIdentity::resolve('admin_login_success', null, 'user', '9');
        $this->assertSame('occurrence', $loginA['source']);
        $this->assertNotSame($loginA['event_id'], $loginB['event_id']);
    }

    public function test_after_commit_does_not_dispatch_before_commit_or_on_rollback(): void
    {
        config(['notifications.pipeline.async' => true]);
        Bus::fake();

        try {
            DB::transaction(function (): void {
                $row = app(NotificationOutboxService::class)->record(
                    'admin_login_success',
                    ['x' => 1],
                    eventId: '33333333-3333-3333-3333-333333333333',
                );
                app(NotificationPipeline::class)->dispatchOutbox($row);
                Bus::assertNothingDispatched();
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, NotificationOutbox::query()->count());
        $this->assertSame(0, NotificationDelivery::query()->count());
        Bus::assertNothingDispatched();
    }

    public function test_dispatch_runs_only_after_commit(): void
    {
        config(['notifications.pipeline.async' => true]);
        Bus::fake();

        DB::transaction(function (): void {
            $row = app(NotificationOutboxService::class)->record(
                'admin_login_success',
                ['x' => 1],
                eventId: '44444444-4444-4444-4444-444444444444',
            );
            app(NotificationPipeline::class)->dispatchOutbox($row);
            Bus::assertNothingDispatched();
        });

        Bus::assertDispatched(DispatchNotificationOutboxEvent::class);
        $this->assertSame(1, NotificationOutbox::query()->count());
    }

    public function test_agency_routes_override_global_and_disabled_agency_falls_back_to_global(): void
    {
        $event = OtaNotificationEvent::AdminLoginSuccess->value;
        NotificationRoute::query()->create([
            'route_key' => 'agency-9|'.$event.'|email|admin',
            'agency_id' => 9,
            'event_type' => $event,
            'channel' => 'email',
            'audience' => 'admin',
            'recipient_strategy' => 'admin',
            'provider' => 'laravel_mail',
            'template_key' => $event,
            'priority' => 'Critical',
            'queue_name' => 'notifications-critical',
            'enabled' => true,
        ]);

        $overridden = app(NotificationRouteResolver::class)->resolve($event, 9);
        $this->assertFalse($overridden->fallback);
        $this->assertSame('agency', $overridden->source);
        $this->assertSame(['admin'], $overridden->audiences());
        $this->assertSame('notifications-critical', $overridden->routes[0]->queueName);

        $global = app(NotificationRouteResolver::class)->resolve($event, 8);
        $this->assertFalse($global->fallback);
        $this->assertSame('global', $global->source);
        $this->assertSame(['logged_in_user'], $global->audiences());

        NotificationRoute::query()->where('route_key', 'agency-9|'.$event.'|email|admin')->update(['enabled' => false]);
        $disabled = app(NotificationRouteResolver::class)->resolve($event, 9);
        $this->assertSame('global', $disabled->source);
        $this->assertSame(['logged_in_user'], $disabled->audiences());
    }

    public function test_legacy_fallback_logs_when_no_db_route(): void
    {
        $resolved = app(NotificationRouteResolver::class)->resolve('unknown_event_family_xyz', null);
        $this->assertTrue($resolved->fallback);
        $this->assertSame('legacy', $resolved->source);
    }

    public function test_delivery_retry_increments_once_and_retry_after_sent_skips(): void
    {
        $deliveries = app(NotificationDeliveryService::class);
        $created = $deliveries->firstOrCreatePending('evt-retry', 'admin_login_success', 'logged_in_user', 'a@example.com', 1, 'default', 'Critical');
        $delivery = $created['delivery'];

        $this->assertTrue($deliveries->beginAttempt($delivery));
        $this->assertSame(1, $delivery->fresh()->attempt_count);
        $this->assertSame('processing', $delivery->fresh()->status);
        $deliveries->markFailed($delivery->fresh(), 'provider throw');
        $failed = $delivery->fresh();
        $this->assertSame('failed', $failed->status);
        $this->assertSame(1, $failed->attempt_count);
        $this->assertNotNull($failed->failed_at);

        $this->assertTrue($deliveries->beginAttempt($failed));
        $retried = $failed->fresh();
        $this->assertSame(2, $retried->attempt_count);
        $this->assertNull($retried->failed_at);
        $deliveries->markSent($retried);
        $sent = $retried->fresh();
        $this->assertSame('sent', $sent->status);
        $this->assertSame(2, $sent->attempt_count);

        $this->assertFalse($deliveries->beginAttempt($sent->fresh()));
        $this->assertSame(2, $sent->fresh()->attempt_count);
    }

    public function test_stale_processing_lock_is_claimable(): void
    {
        $row = app(NotificationOutboxService::class)->record('booking_confirmed', ['x' => 1], eventId: '55555555-5555-5555-5555-555555555555');
        $row->forceFill([
            'status' => 'processing',
            'locked_at' => now()->subMinutes(30),
            'attempt_count' => 1,
        ])->save();

        $claimed = app(NotificationOutboxService::class)->claim($row->fresh());
        $this->assertNotNull($claimed);
        $this->assertSame('processing', $claimed->status);
        $this->assertSame(2, $claimed->attempt_count);
    }

    public function test_async_publish_does_not_wait_on_bus(): void
    {
        config(['notifications.pipeline.async' => true]);
        Bus::fake();
        $row = app(NotificationOutboxService::class)->record('daily_admin_report', ['x' => 1], eventId: '66666666-6666-6666-6666-666666666666');
        $started = hrtime(true);
        app(NotificationPipeline::class)->dispatchOutbox($row);
        $asyncMs = (hrtime(true) - $started) / 1e6;
        Bus::assertDispatched(DispatchNotificationOutboxEvent::class);
        $this->assertLessThan(500, $asyncMs);
    }

    public function test_publish_inside_open_transaction_does_not_mail_and_rolls_back(): void
    {
        Mail::fake();
        config([
            'notifications.pipeline.async' => false,
            'notifications.pipeline.enabled' => true,
        ]);
        $agency = Agency::factory()->create();
        $originalName = $agency->name;

        try {
            DB::transaction(function () use ($agency): void {
                $agency->forceFill(['name' => $agency->name.'-tx'])->save();
                app(NotificationPipeline::class)->publishOperational(
                    $agency,
                    OtaNotificationEvent::AdminLoginSuccess->value,
                    ['notification_event_id' => '77777777-7777-7777-7777-777777777777'],
                    null,
                    null,
                    'subj',
                    'body',
                    [],
                    ['logged_in_user_email' => 'admin@example.test'],
                );
                Mail::assertNothingSent();
                $this->assertSame(1, NotificationOutbox::query()->count());
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame($originalName, $agency->fresh()->name);
        $this->assertSame(0, NotificationOutbox::query()->count());
        $this->assertSame(0, NotificationDelivery::query()->count());
        Mail::assertNothingSent();
    }

    public function test_publish_dispatches_only_after_commit(): void
    {
        Mail::fake();
        config(['notifications.pipeline.async' => true, 'notifications.pipeline.enabled' => true]);
        Bus::fake();
        $agency = Agency::factory()->create();

        DB::transaction(function () use ($agency): void {
            app(NotificationPipeline::class)->publishOperational(
                $agency,
                OtaNotificationEvent::AdminLoginSuccess->value,
                ['notification_event_id' => '88888888-8888-8888-8888-888888888888'],
                null,
                null,
                'subj',
                'body',
                [],
                ['logged_in_user_email' => 'admin@example.test'],
            );
            Bus::assertNothingDispatched();
            Mail::assertNothingSent();
        });

        Bus::assertDispatched(DispatchNotificationOutboxEvent::class);
        $this->assertSame(1, NotificationOutbox::query()->count());
        Mail::assertNothingSent();
    }
}
