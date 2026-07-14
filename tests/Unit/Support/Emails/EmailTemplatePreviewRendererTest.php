<?php

namespace Tests\Unit\Support\Emails;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Support\Branding\CompanyEmailProfileResolver;
use App\Support\Emails\EmailTemplatePreviewRenderer;
use App\Support\Emails\EmailTemplateRegistry;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTemplatePreviewRendererTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function preview_returns_html_for_editable_operational_template(): void
    {
        Mail::fake();
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => 'booking_confirmed',
            'channel' => 'email',
            'subject' => 'Confirmed {{ booking_reference }}',
            'body' => "Hello {{ customer_name }},\nYour booking {{ booking_reference }} is confirmed.",
            'is_enabled' => true,
        ]);

        $result = app(EmailTemplatePreviewRenderer::class)->render($agency, 'ops-booking_confirmed');

        $this->assertStringContainsString('<!DOCTYPE html>', $result->html);
        $this->assertStringContainsString('Confirmed GXJDHD8K', $result->subject);
        $this->assertTrue($result->usedDbTemplate);
        $this->assertFalse($result->notConnectedToLiveSending);
        Mail::assertNothingSent();
    }

    #[Test]
    public function preview_uses_company_profile_branding(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();
        $profile = CompanyEmailProfileResolver::resolve($agency);

        $result = app(EmailTemplatePreviewRenderer::class)->render($agency, 'ops-booking_confirmed');

        $this->assertStringContainsString(e($profile->name), $result->html);
        if ($profile->support_email) {
            $this->assertStringContainsString(e($profile->support_email), $result->html);
        }
    }

    #[Test]
    public function preview_replaces_sample_placeholders_and_escapes_markup(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();

        AgencyMessageTemplate::query()->create([
            'agency_id' => $agency->id,
            'event' => 'booking_confirmed',
            'channel' => 'email',
            'subject' => 'Test',
            'body' => 'Ref {{ booking_reference }} <script>alert(1)</script>',
            'is_enabled' => true,
        ]);

        $result = app(EmailTemplatePreviewRenderer::class)->render($agency, 'ops-booking_confirmed');

        $this->assertStringContainsString('GXJDHD8K', $result->innerBody);
        $this->assertStringNotContainsString('<script>', $result->html);
    }

    #[Test]
    public function srcdoc_attribute_escapes_quotes_without_entity_encoding_html_tags(): void
    {
        $html = '<!DOCTYPE html><html><body><p class="title">Hello</p></body></html>';

        $attribute = EmailTemplatePreviewRenderer::srcdocAttribute($html);

        $this->assertStringContainsString('<p class=', $attribute);
        $this->assertStringNotContainsString('&lt;p', $attribute);
        $this->assertSame(
            '<!DOCTYPE html><html><body><p class=&quot;title&quot;>Hello</p></body></html>',
            EmailTemplatePreviewRenderer::srcdocAttribute('<!DOCTYPE html><html><body><p class="title">Hello</p></body></html>')
        );
    }

    #[Test]
    public function default_fields_returns_placeholder_subject_and_body(): void
    {
        $definition = EmailTemplateRegistry::find('ops-booking_confirmed');
        $this->assertNotNull($definition);

        $fields = app(EmailTemplatePreviewRenderer::class)->defaultFields($definition);

        $this->assertStringContainsString('{{ brand_name }}', $fields['subject']);
        $this->assertStringContainsString('{{ booking_reference }}', $fields['body']);
    }

    #[Test]
    public function future_migration_template_shows_not_connected_warning(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();

        $definition = EmailTemplateRegistry::find('customer-booking_confirmed');
        $this->assertNotNull($definition);
        $this->assertFalse($definition->editableNow);

        $result = app(EmailTemplatePreviewRenderer::class)->render($agency, $definition);

        $this->assertTrue($result->notConnectedToLiveSending);
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('not connected', strtolower($result->warnings[0]));
    }
}
