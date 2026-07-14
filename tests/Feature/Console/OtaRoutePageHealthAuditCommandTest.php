<?php

namespace Tests\Feature\Console;

use App\Models\Booking;
use App\Models\DeveloperUser;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OtaRoutePageHealthAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ota-developer.enabled', true);
    }

    public function test_guest_only_health_audit_is_read_only_and_passes(): void
    {
        $beforeCount = Booking::query()->count();

        $this->artisan('ota:route-page-health-audit', ['--guest-only' => true])
            ->expectsOutputToContain('Classification: READ-ONLY')
            ->expectsOutputToContain('supplier_mutation_attempted=false')
            ->expectsOutputToContain('ticketing_attempted=false')
            ->expectsOutputToContain('cancellation_attempted=false')
            ->expectsOutputToContain('emails_sent=false')
            ->expectsOutputToContain('Route page health audit passed.')
            ->assertSuccessful();

        $this->assertSame($beforeCount, Booking::query()->count());
    }

    public function test_all_scope_with_seed_hits_admin_booking_show(): void
    {
        DeveloperUser::query()->create([
            'name' => 'Health Dev',
            'email' => 'health-dev@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $beforeSupplierConnections = SupplierConnection::query()->count();

        $this->artisan('ota:route-page-health-audit', ['--all' => true, '--seed' => true])
            ->expectsOutputToContain('admin-booking-show')
            ->expectsOutputToContain('staff-booking-show')
            ->expectsOutputToContain('admin-api-settings')
            ->expectsOutputToContain('Route page health audit passed.')
            ->assertSuccessful();

        $this->assertSame(
            $beforeSupplierConnections,
            SupplierConnection::query()->count(),
            'Route health audit --seed must not create supplier connection placeholders.',
        );
    }

    public function test_source_scan_fails_on_forbidden_blade_pattern(): void
    {
        $path = resource_path('views/dashboard/admin/bookings/_health_audit_trap.blade.php');
        file_put_contents($path, "{{ \$x : display_unknown() }}\n");

        try {
            $this->artisan('ota:route-page-health-audit', ['--guest-only' => true])
                ->expectsOutputToContain('source-scan')
                ->assertFailed();
        } finally {
            @unlink($path);
        }
    }

    public function test_requires_scope_flag(): void
    {
        $this->artisan('ota:route-page-health-audit')
            ->expectsOutputToContain('Specify a scope')
            ->assertFailed();
    }
}
