<?php

namespace Tests\Unit\Notifications;

use App\Enums\OtaNotificationEvent;
use App\Services\Notifications\NotificationEventIdentity;
use App\Services\Notifications\NotificationEventIdentityKind;
use App\Services\Notifications\NotificationEventIdentityPolicy;
use Tests\TestCase;

class NotificationEventIdentityPolicyTest extends TestCase
{
    public function test_payment_reminder_second_occurrence_gets_a_new_id(): void
    {
        $a = NotificationEventIdentity::resolve(OtaNotificationEvent::PaymentReminder->value, null, 'booking', '123');
        $b = NotificationEventIdentity::resolve(OtaNotificationEvent::PaymentReminder->value, null, 'booking', '123');
        $this->assertSame(NotificationEventIdentityKind::RequiresVariantOrOccurrence->value, $a['kind']);
        $this->assertNotSame($a['event_id'], $b['event_id']);
    }

    public function test_booking_status_changed_second_occurrence_gets_a_new_id(): void
    {
        $a = NotificationEventIdentity::resolve(OtaNotificationEvent::BookingStatusChanged->value, null, 'booking', '123');
        $b = NotificationEventIdentity::resolve(OtaNotificationEvent::BookingStatusChanged->value, null, 'booking', '123');
        $this->assertNotSame($a['event_id'], $b['event_id']);
    }

    public function test_support_replies_use_message_occurrence_ids(): void
    {
        $event = OtaNotificationEvent::SupportTicketReplied->value;
        $a = NotificationEventIdentity::resolve($event, null, 'support_ticket', '5', '', 'support_message:101');
        $b = NotificationEventIdentity::resolve($event, null, 'support_ticket', '5', '', 'support_message:102');
        $this->assertNotSame($a['event_id'], $b['event_id']);

        $retry = NotificationEventIdentity::resolve($event, null, 'support_ticket', '5', '', 'support_message:101');
        $this->assertSame($a['event_id'], $retry['event_id']);
    }

    public function test_payment_verified_same_transition_key_dedupes(): void
    {
        $event = OtaNotificationEvent::PaymentVerified->value;
        $a = NotificationEventIdentity::resolve($event, null, 'payment', '456', 'verified:456');
        $b = NotificationEventIdentity::resolve($event, null, 'payment', '456', 'verified:456');
        $this->assertSame($a['event_id'], $b['event_id']);
        $this->assertSame('variant', $a['source']);
    }

    public function test_separate_admin_logins_are_distinct(): void
    {
        $a = NotificationEventIdentity::resolve(OtaNotificationEvent::AdminLoginSuccess->value, null, 'user', '9');
        $b = NotificationEventIdentity::resolve(OtaNotificationEvent::AdminLoginSuccess->value, null, 'user', '9');
        $this->assertNotSame($a['event_id'], $b['event_id']);
    }

    public function test_explicit_event_id_wins(): void
    {
        $id = '11111111-1111-1111-1111-111111111111';
        $a = NotificationEventIdentity::resolve('booking_status_changed', $id, 'booking', '1');
        $b = NotificationEventIdentity::resolve('booking_status_changed', $id, 'booking', '1');
        $this->assertSame($id, $a['event_id']);
        $this->assertSame($id, $b['event_id']);
        $this->assertSame('explicit', $a['source']);
    }

    public function test_one_shot_aggregate_is_stable_on_retry(): void
    {
        $this->assertSame(
            NotificationEventIdentityKind::OneShotAggregate,
            NotificationEventIdentityPolicy::kind(OtaNotificationEvent::BookingConfirmed->value),
        );
        $a = NotificationEventIdentity::resolve(OtaNotificationEvent::BookingConfirmed->value, null, 'booking', '123');
        $b = NotificationEventIdentity::resolve(OtaNotificationEvent::BookingConfirmed->value, null, 'booking', '123');
        $this->assertSame('one_shot_aggregate', $a['source']);
        $this->assertSame($a['event_id'], $b['event_id']);
    }

    public function test_document_generation_is_not_one_shot_aggregate(): void
    {
        foreach ([
            OtaNotificationEvent::InvoiceGenerated->value,
            OtaNotificationEvent::PaymentReceiptGenerated->value,
            OtaNotificationEvent::TicketItineraryGenerated->value,
        ] as $event) {
            $this->assertSame(
                NotificationEventIdentityKind::RequiresVariantOrOccurrence,
                NotificationEventIdentityPolicy::kind($event),
            );
            $first = NotificationEventIdentity::resolve($event, null, 'booking', '99');
            $second = NotificationEventIdentity::resolve($event, null, 'booking', '99');
            $this->assertNotSame($first['event_id'], $second['event_id']);
            $this->assertSame('occurrence', $first['source']);

            $retry = NotificationEventIdentity::resolve($event, null, 'booking', '99', '', 'invoice:version:7');
            $same = NotificationEventIdentity::resolve($event, null, 'booking', '99', '', 'invoice:version:7');
            $this->assertSame($retry['event_id'], $same['event_id']);
            $this->assertSame('occurrence_record', $retry['source']);
        }
    }

    public function test_uncertain_events_default_to_occurrence_scoped(): void
    {
        $this->assertSame(
            NotificationEventIdentityKind::OccurrenceScoped,
            NotificationEventIdentityPolicy::kind('unknown_ops_event'),
        );
        $a = NotificationEventIdentity::resolve('unknown_ops_event', null, 'booking', '1');
        $b = NotificationEventIdentity::resolve('unknown_ops_event', null, 'booking', '1');
        $this->assertNotSame($a['event_id'], $b['event_id']);
    }
}
