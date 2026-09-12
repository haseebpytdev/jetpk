<?php

namespace Tests\Feature;

use App\Models\AgentApplication;
use App\Support\Agents\AgentApplicationNotificationPayload;
use App\Support\Emails\EmailOperationalSubjectFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentApplicationAdminEmailPayloadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_application_payload_includes_reference_agency_and_review_link(): void
    {
        config(['app.url' => 'https://jetpakistan.pk']);

        $application = AgentApplication::query()->create([
            'first_name' => 'Auth',
            'last_name' => 'Six',
            'email' => 'auth06@example.test',
            'mobile' => '+92000000001',
            'company_name' => 'Auth06 Agency',
            'business_type' => 'travel_agency',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'office_address' => 'Suite 1',
            'status' => 'pending',
        ]);

        $payload = AgentApplicationNotificationPayload::fromApplication($application);
        $subject = EmailOperationalSubjectFormatter::adminAgentApplication($application, 'New Agent Application');

        $this->assertNotEmpty($application->application_reference);
        $this->assertSame($application->application_reference, $payload['application_reference']);
        $this->assertSame('Auth06 Agency', $payload['application']['agency_name'] ?? null);
        $this->assertSame('Auth Six', $payload['application']['applicant_name'] ?? null);
        $this->assertStringStartsWith('https://jetpakistan.pk/', (string) ($payload['review_url'] ?? ''));
        $this->assertStringContainsString('[ADMIN]', $subject);
        $this->assertStringContainsString('Auth06 Agency', $subject);
        $this->assertStringContainsString((string) $application->application_reference, $subject);
    }
}
