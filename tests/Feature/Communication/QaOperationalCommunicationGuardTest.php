<?php

namespace Tests\Feature\Communication;

use App\Models\Booking;
use App\Services\Communication\QaOperationalCommunicationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaOperationalCommunicationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_qa_booking_redirects_ops_recipients_to_sink(): void
    {
        config([
            'ota.qa_communication.isolation_enabled' => true,
            'ota.qa_communication.controlled_domains' => ['example.invalid'],
            'ota.qa_communication.ops_sink_emails' => ['qa-ops-sink@example.invalid'],
        ]);

        $booking = new Booking([
            'supplier' => 'local_qa_inert',
            'meta' => ['jp_ops_qa' => true],
        ]);

        $guard = app(QaOperationalCommunicationGuard::class);
        $bundle = $guard->filterRecipientBundle([
            'to' => ['ops@gmail.com', 'customer@example.invalid'],
            'cc' => ['admin@yoursdomain.com'],
            'bcc' => [],
            'scope' => 'ops',
            'buckets' => ['platform_admin'],
            'skipped_buckets' => [],
        ], $booking);

        $this->assertTrue($bundle['qa_isolation_applied'] ?? false);
        $this->assertSame(['customer@example.invalid', 'qa-ops-sink@example.invalid'], $bundle['to']);
        $this->assertSame([], $bundle['cc']);
        $this->assertDoesNotContain('ops@gmail.com', $bundle['to']);
    }

    public function test_normal_booking_routing_unchanged(): void
    {
        config([
            'ota.qa_communication.isolation_enabled' => true,
            'ota.qa_communication.ops_sink_emails' => ['qa-ops-sink@example.invalid'],
        ]);

        $booking = new Booking([
            'supplier' => 'sabre',
            'meta' => [],
        ]);

        $guard = app(QaOperationalCommunicationGuard::class);
        $bundle = $guard->filterRecipientBundle([
            'to' => ['ops@gmail.com'],
            'cc' => [],
            'bcc' => [],
            'scope' => 'ops',
            'buckets' => ['platform_admin'],
            'skipped_buckets' => [],
        ], $booking);

        $this->assertArrayNotHasKey('qa_isolation_applied', $bundle);
        $this->assertSame(['ops@gmail.com'], $bundle['to']);
    }

    private function assertDoesNotContain(string $needle, array $haystack): void
    {
        $this->assertFalse(in_array($needle, $haystack, true));
    }
}
