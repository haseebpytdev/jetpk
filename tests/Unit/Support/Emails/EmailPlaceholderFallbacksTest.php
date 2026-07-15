<?php

namespace Tests\Unit\Support\Emails;

use App\Support\Emails\EmailPlaceholderFallbacks;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailPlaceholderFallbacksTest extends TestCase
{
    #[Test]
    public function it_returns_ops_fallbacks_for_common_keys(): void
    {
        $this->assertSame('Not available', EmailPlaceholderFallbacks::fallbackFor('amount'));
        $this->assertSame('Not assigned yet', EmailPlaceholderFallbacks::fallbackFor('pnr'));
        $this->assertSame('Pending / Staff review', EmailPlaceholderFallbacks::fallbackFor('supplier_status'));
        $this->assertSame('Staff review required', EmailPlaceholderFallbacks::fallbackFor('review_reason'));
    }

    #[Test]
    public function it_resolves_aliases_to_canonical_keys(): void
    {
        $this->assertSame('review_reason', EmailPlaceholderFallbacks::canonicalKey('staff_review_reason'));
        $this->assertSame('supplier_status', EmailPlaceholderFallbacks::canonicalKey('supplier_booking_status'));
    }

    #[Test]
    public function customer_audience_uses_customer_safe_supplier_status(): void
    {
        $fallback = EmailPlaceholderFallbacks::fallbackFor('supplier_status', ['audience' => 'customer']);

        $this->assertSame('In progress', $fallback);
        $this->assertNotSame('Pending / Staff review', $fallback);
    }

    #[Test]
    public function it_applies_variable_aliases_when_canonical_is_empty(): void
    {
        $merged = EmailPlaceholderFallbacks::applyVariableAliases([
            'staff_review_reason' => 'Manual review required',
            'supplier_booking_status' => 'pending',
        ]);

        $this->assertSame('Manual review required', $merged['review_reason']);
        $this->assertSame('pending', $merged['supplier_status']);
    }

    #[Test]
    public function support_ticket_and_agent_application_fallbacks_exist(): void
    {
        $this->assertSame('To be assigned', EmailPlaceholderFallbacks::fallbackFor('ticket_reference'));
        $this->assertSame('Requester', EmailPlaceholderFallbacks::fallbackFor('requester_name'));
        $this->assertSame('Applicant', EmailPlaceholderFallbacks::fallbackFor('applicant_name'));
        $this->assertSame('Not provided', EmailPlaceholderFallbacks::fallbackFor('city'));
        $this->assertSame('Not provided', EmailPlaceholderFallbacks::fallbackFor('login_email'));
    }

    #[Test]
    public function agency_and_company_name_fallback_to_brand_chain(): void
    {
        $this->setJetpkDeployment();

        $this->assertSame('JetPakistan', EmailPlaceholderFallbacks::fallbackFor('agency_name', [
            'brand_name' => 'JetPakistan',
        ]));
        $this->assertSame('Acme Travel', EmailPlaceholderFallbacks::fallbackFor('company_name', [
            'agency_name' => 'Acme Travel',
        ]));

        config([
            'ota_client.single_client_mode' => false,
            'ota_client.single_client_root' => false,
        ]);

        $this->assertSame('Travel Platform', EmailPlaceholderFallbacks::fallbackFor('agency_name', [
            'agency_name' => 'Parwaaz Travels',
            'brand_name' => 'YD Travel',
        ]));
    }

    protected function setJetpkDeployment(): void
    {
        config([
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
            'ota_client.slug' => 'jetpk',
        ]);
    }

    #[Test]
    public function forbidden_brand_names_are_detected(): void
    {
        $this->assertTrue(EmailPlaceholderFallbacks::isForbiddenBrandName('Parwaaz Travels'));
        $this->assertTrue(EmailPlaceholderFallbacks::isForbiddenBrandName('{{ agency_name }}'));
        $this->assertFalse(EmailPlaceholderFallbacks::isForbiddenBrandName('JetPakistan'));
    }
}
