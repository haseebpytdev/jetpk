<?php

namespace Tests\Unit\Support\Emails;

use App\Enums\CommunicationTemplateEvent;
use App\Enums\OtaNotificationEvent;
use App\Support\Emails\EmailTemplateRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTemplateRegistryTest extends TestCase
{
    #[Test]
    public function registry_exposes_all_ten_categories(): void
    {
        $values = array_column(EmailTemplateRegistry::categories(), 'value');

        $this->assertCount(10, $values);
        $this->assertContains(EmailTemplateRegistry::CATEGORY_BOOKING, $values);
        $this->assertContains(EmailTemplateRegistry::CATEGORY_MARKETING, $values);
    }

    #[Test]
    public function registry_includes_operational_and_customer_paths_for_booking_request_received(): void
    {
        $ops = EmailTemplateRegistry::find('ops-booking_request_received');
        $customer = EmailTemplateRegistry::find('customer-booking_request_received');

        $this->assertNotNull($ops);
        $this->assertNotNull($customer);
        $this->assertTrue($ops->editableNow);
        $this->assertFalse($customer->editableNow);
        $this->assertSame('modern_layout', $ops->sendPath);
        $this->assertSame('modern_layout', $customer->sendPath);
        $this->assertSame('Modern layout', EmailTemplateRegistry::connectionLabelFor($customer));
    }

    #[Test]
    public function registry_includes_communication_template_event_entries(): void
    {
        foreach (CommunicationTemplateEvent::cases() as $event) {
            $hasEntry = collect(EmailTemplateRegistry::all())
                ->contains(fn ($row): bool => $row->event === $event->value);

            $this->assertTrue($hasEntry, 'Missing registry entry for '.$event->value);
        }
    }

    #[Test]
    public function registry_resolves_category_for_every_ota_notification_event_without_throw(): void
    {
        foreach (OtaNotificationEvent::cases() as $event) {
            $entry = EmailTemplateRegistry::find('ops-'.$event->value);
            $this->assertNotNull($entry, 'Missing ops registry entry for '.$event->value);
            $this->assertNotSame('', $entry->category);
        }

        $groupEvent = EmailTemplateRegistry::find('ops-'.OtaNotificationEvent::GroupBookingReservationCreated->value);
        $this->assertNotNull($groupEvent);
        $this->assertSame(EmailTemplateRegistry::CATEGORY_BOOKING, $groupEvent->category);
    }

    #[Test]
    public function registry_covers_every_ota_notification_event(): void
    {
        foreach (OtaNotificationEvent::cases() as $event) {
            $entry = EmailTemplateRegistry::find('ops-'.$event->value);
            $this->assertNotNull($entry, 'Missing ops registry entry for '.$event->value);
            $this->assertSame('agency_message_templates', $entry->templateSource);
        }
    }

    #[Test]
    public function operational_entries_are_marked_modernized(): void
    {
        $ops = EmailTemplateRegistry::find('ops-booking_manual_review_required');
        $this->assertNotNull($ops);
        $this->assertSame('Modern layout', EmailTemplateRegistry::connectionLabelFor($ops));
    }

    #[Test]
    public function customer_booking_mailable_entries_use_modern_layout_and_are_marked_modernized(): void
    {
        $i7Keys = [
            'customer-booking_request_received',
            'customer-booking_confirmed',
            'customer-payment_verified',
            'customer-ticket_issued',
            'customer-itinerary-ready',
            'auth-google-welcome',
        ];

        foreach ($i7Keys as $key) {
            $entry = EmailTemplateRegistry::find($key);
            $this->assertNotNull($entry, 'Missing registry entry: '.$key);
            $this->assertSame('modern_layout', $entry->sendPath);
            $this->assertFalse($entry->editableNow);
            $this->assertTrue($entry->migrationSafeLater);
            $this->assertSame('Modern layout', EmailTemplateRegistry::connectionLabelFor($entry));
        }
    }

    #[Test]
    public function framework_auth_entries_are_marked_framework_managed(): void
    {
        foreach (['auth-email-verification', 'auth-password-reset'] as $key) {
            $entry = EmailTemplateRegistry::find($key);
            $this->assertNotNull($entry);
            $this->assertSame('framework_notification', $entry->sendPath);
            $this->assertSame('Framework-managed', EmailTemplateRegistry::connectionLabelFor($entry));
        }
    }

    #[Test]
    public function i8_entries_use_modern_layout_with_accurate_connection_labels(): void
    {
        $modernized = [
            'auth-customer-welcome',
            'auth-admin-new-customer',
            'marketing-abandoned-search',
        ];
        foreach ($modernized as $key) {
            $entry = EmailTemplateRegistry::find($key);
            $this->assertNotNull($entry, 'Missing: '.$key);
            $this->assertSame('modern_layout', $entry->sendPath);
            $this->assertSame('Modern layout', EmailTemplateRegistry::connectionLabelFor($entry));
        }

        $manual = EmailTemplateRegistry::find('manual-invoice_sent_manual');
        $this->assertNotNull($manual);
        $this->assertSame('modern_layout', $manual->sendPath);
        $this->assertTrue($manual->editableNow);
        $this->assertSame('Editable · Modern layout', EmailTemplateRegistry::connectionLabelFor($manual));
    }
}
