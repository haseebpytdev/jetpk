<?php

namespace Tests\Unit\Support\Emails;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Support\Emails\OperationalEmailDefaults;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OperationalEmailDefaultsTest extends TestCase
{
    #[Test]
    public function auth_security_events_have_professional_defaults(): void
    {
        foreach (OperationalEmailDefaults::AUTH_SECURITY_EVENT_KEYS as $eventKey) {
            $defaults = OperationalEmailDefaults::forEvent($eventKey);
            $this->assertNotNull($defaults, $eventKey);
            $this->assertStringContainsString('{{ brand_name }}', $defaults['subject']);
            $this->assertNotSame('', trim($defaults['body']));
        }
    }

    #[Test]
    public function login_success_defaults_include_security_placeholders(): void
    {
        $defaults = OperationalEmailDefaults::forEvent(OtaNotificationEvent::AdminLoginSuccess->value);
        $this->assertNotNull($defaults);

        foreach (['user_name', 'user_email', 'account_type', 'timestamp', 'ip', 'user_agent', 'portal_label'] as $placeholder) {
            $this->assertStringContainsString('{{ '.$placeholder.' }}', $defaults['body']);
        }
    }

    #[Test]
    public function portal_and_account_type_labels_are_human_readable(): void
    {
        $this->assertSame('Admin Portal', OperationalEmailDefaults::portalLabel(AccountType::PlatformAdmin));
        $this->assertSame('Staff Portal', OperationalEmailDefaults::portalLabel(AccountType::Staff));
        $this->assertSame('Agent Portal', OperationalEmailDefaults::portalLabel(AccountType::Agent));
        $this->assertSame('Platform Administrator', OperationalEmailDefaults::accountTypeLabel(AccountType::PlatformAdmin));
    }

    #[Test]
    public function business_operational_events_have_professional_defaults(): void
    {
        foreach (OperationalEmailDefaults::BUSINESS_OPERATIONAL_EVENT_KEYS as $eventKey) {
            $this->assertTrue(OperationalEmailDefaults::isBusinessOperationalEvent($eventKey), $eventKey);

            $defaults = OperationalEmailDefaults::forEvent($eventKey);
            $this->assertNotNull($defaults, $eventKey);
            $this->assertStringContainsString('{{ brand_name }}', $defaults['subject']);
            $this->assertNotSame('', trim($defaults['body']));
            $this->assertNotEmpty(OperationalEmailDefaults::variablesForEvent($eventKey));
        }
    }

    #[Test]
    public function booking_request_defaults_include_operational_placeholders(): void
    {
        $defaults = OperationalEmailDefaults::forEvent(OtaNotificationEvent::BookingRequestReceived->value);
        $this->assertNotNull($defaults);

        foreach (['booking_reference', 'route', 'customer_name', 'passenger_name'] as $placeholder) {
            $this->assertStringContainsString('{{ '.$placeholder.' }}', $defaults['body']);
        }
    }
}
