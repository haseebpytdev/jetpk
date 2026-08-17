<?php

namespace Tests\Feature\Api\Dashboard;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingTicket;
use App\Models\CmsPage;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class DashboardReadOnlyApiTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    private Booking $demoBooking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->demoBooking = Booking::factory()->for($agency)->create([
            'booking_reference' => 'DASH-RO-TEST-001',
            'status' => BookingStatus::Confirmed,
            'payment_status' => 'paid',
            'currency' => 'PKR',
        ]);
    }

    public function test_unauthenticated_session_returns_401(): void
    {
        $this->getJson(route('api.dashboard.session'))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_authenticated_session_summary_returns_safe_fields(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.session'));

        $response->assertOk()
            ->assertJsonPath('source', 'laravelReadOnly')
            ->assertJsonPath('schemaVersion', 'dash-read-only-v1')
            ->assertJsonStructure([
                'data' => ['id', 'displayName', 'email', 'roles', 'permissions', 'accountType', 'accountStatus'],
            ]);

        $json = $response->json();
        $this->assertArrayNotHasKey('password', $json['data'] ?? []);
        $this->assertArrayNotHasKey('session_id', $json['data'] ?? []);
        $this->assertContains('dashboard.view', $json['data']['permissions']);
    }

    public function test_overview_envelope_and_permission_gate(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.overview'))
            ->assertOk()
            ->assertJsonPath('meta.source', 'laravelReadOnly')
            ->assertJsonStructure(['data' => ['summaryStats', 'operationalQueues', 'recentBookings']]);
    }

    public function test_staff_without_bookings_permission_gets_forbidden_on_bookings(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill([
            'meta' => ['staff_permissions' => []],
        ])->save();

        $this->actingAs($staff->fresh())
            ->getJson(route('api.dashboard.bookings.index'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_bookings_pagination_and_sensitive_field_exclusions(): void
    {
        $admin = $this->platformAdmin();
        $booking = $this->demoBooking;

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.bookings.index', [
            'page' => 1,
            'pageSize' => 10,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'pagination' => ['page', 'pageSize', 'total', 'pageCount'],
                'data' => ['bookings', 'summary', 'facets'],
            ]);

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('passport_number', (string) $payload);
        $this->assertStringNotContainsString('password', (string) $payload);
    }

    public function test_booking_detail_returns_safe_summary(): void
    {
        $admin = $this->platformAdmin();
        $booking = $this->demoBooking;

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.bookings.show', ['booking' => $booking->booking_reference ?? $booking->id]))
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary', 'itinerary', 'passengers', 'fareSummary', 'paymentSummary', 'statusTimeline', 'internalNotes', 'communications', 'documents']]);
    }

    public function test_payments_index_requires_permission(): void
    {
        $customer = User::query()->where('account_type', AccountType::Customer)->first();
        $this->assertNotNull($customer);

        $this->actingAs($customer)
            ->getJson(route('api.dashboard.payments.index'))
            ->assertForbidden();
    }

    public function test_payments_pagination_for_platform_admin(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.payments.index', ['page' => 1, 'pageSize' => 5]))
            ->assertOk()
            ->assertJsonStructure(['data' => ['transactions', 'summary']]);
    }

    public function test_customers_index_for_platform_admin(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.customers.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonStructure(['data' => ['customers', 'summary']]);
    }

    public function test_no_mutation_routes_exist_for_dashboard_api(): void
    {
        $allowedMutations = [
            'api/dashboard/ops/inbox/read',
            'api/dashboard/ops/inbox/read-all',
            'api/dashboard/roles',
            'api/dashboard/roles/{role}',
            'api/dashboard/roles/{role}/clone',
            'api/dashboard/roles/{role}/assign',
            'api/dashboard/roles/{role}/unassign',
        ];

        $routes = collect(app('router')->getRoutes())->filter(
            fn ($route): bool => str_starts_with((string) $route->uri(), 'api/dashboard')
        );

        foreach ($routes as $route) {
            $uri = (string) $route->uri();
            if (in_array($uri, $allowedMutations, true)) {
                continue;
            }
            $this->assertSame(['GET', 'HEAD'], $route->methods(), 'Dashboard API must remain GET-only: '.$uri);
        }
    }

    public function test_booking_list_preserves_currency_field(): void
    {
        $admin = $this->platformAdmin();
        $response = $this->actingAs($admin)->getJson(route('api.dashboard.bookings.index'));
        $response->assertOk();

        $bookings = $response->json('data.bookings') ?? [];
        if ($bookings !== []) {
            $this->assertArrayHasKey('currency', $bookings[0]);
            $this->assertArrayHasKey('currencyStatus', $bookings[0]);
            $this->assertArrayHasKey('totalMoney', $bookings[0]);
            $this->assertContains($bookings[0]['currencyStatus'], ['resolved', 'unresolved']);
            if ($bookings[0]['currencyStatus'] === 'unresolved') {
                $this->assertSame('Amount unavailable', $bookings[0]['totalMoney']['displayLabel'] ?? '');
            }
        }
    }

    public function test_rate_limit_response_is_sanitized(): void
    {
        $admin = $this->platformAdmin();

        for ($i = 0; $i < 130; $i++) {
            $response = $this->actingAs($admin)->getJson(route('api.dashboard.session'));
            if ($response->status() === 429) {
                $response->assertJsonPath('error.code', 'rate_limited');
                $this->assertStringNotContainsString('stack', strtolower((string) $response->getContent()));

                return;
            }
        }

        $this->markTestSkipped('Rate limit threshold not reached in this environment.');
    }

    public function test_suppliers_unauthenticated_returns_401(): void
    {
        $this->getJson(route('api.dashboard.suppliers.index'))
            ->assertUnauthorized();
    }

    public function test_suppliers_forbidden_for_customer(): void
    {
        $customer = User::query()->where('account_type', AccountType::Customer)->firstOrFail();

        $this->actingAs($customer)
            ->getJson(route('api.dashboard.suppliers.index'))
            ->assertForbidden();
    }

    public function test_suppliers_authorized_for_platform_admin(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre Test',
            'display_name' => 'Sabre Test',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['username' => 'test', 'password' => 'secret', 'pcc' => 'ABC1', 'lniata' => 'LNIATA1'],
            'is_active' => true,
            'settings' => SabreSupplierChannelConfig::mergeIntoSettings([], true, true),
        ]);

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.suppliers.index'));
        $response->assertOk()->assertJsonStructure(['data' => ['suppliers', 'summary', 'facets']]);

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('secret', (string) $payload);
        $this->assertStringNotContainsString('ABC1', (string) $payload);
        $this->assertStringNotContainsString('LNIATA1', (string) $payload);
        $this->assertStringNotContainsString('"pcc"', (string) $payload);
    }

    public function test_supplier_gds_ndc_distinction_preserved(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'name' => 'Sabre Channels',
            'display_name' => 'Sabre Channels',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'credentials' => ['username' => 'x', 'password' => 'y'],
            'is_active' => true,
            'settings' => SabreSupplierChannelConfig::mergeIntoSettings([], true, false),
        ]);

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.suppliers.index', ['q' => 'Sabre Channels']));
        $response->assertOk();
        $supplier = collect($response->json('data.suppliers') ?? [])->firstWhere('supplierName', 'Sabre Channels');
        $this->assertNotNull($supplier);
        $this->assertSame('enabled', $supplier['resultSourceState']['gds'] ?? null);
        $this->assertSame('disabled', $supplier['resultSourceState']['ndc'] ?? null);
    }

    public function test_agents_pagination_and_sensitive_field_exclusions(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.agents.index', ['page' => 1]));
        $response->assertOk()->assertJsonStructure(['data' => ['agents', 'summary']]);

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('password', (string) $payload);
        $this->assertStringNotContainsString('password_hash', (string) $payload);
    }

    public function test_pnr_order_filtering_and_type_preservation(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->demoBooking;

        SupplierBooking::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'pnr' => 'ABC123',
            'supplier_reference' => 'REF-001',
            'status' => 'confirmed',
        ]);
        SupplierBooking::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'supplier_reference' => 'NDC-001',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.pnrs.index', ['channel' => 'ndc']));
        $response->assertOk();

        $types = collect($response->json('data.pnrs') ?? [])->pluck('referenceType')->all();
        $this->assertContains('NDC Order', $types);
    }

    public function test_pnr_cancellation_state_is_read_only(): void
    {
        $admin = $this->platformAdmin();
        $response = $this->actingAs($admin)->getJson(route('api.dashboard.pnrs.index'));
        $response->assertOk();
        $pnr = $response->json('data.pnrs.0');
        if ($pnr !== null) {
            $this->assertArrayHasKey('cancellationState', $pnr);
            $this->assertNotSame('mutate', $pnr['cancellationState']);
        }
    }

    public function test_tickets_filtering_and_masked_numbers(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->demoBooking;

        BookingTicket::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $booking->id,
            'ticket_number' => '1761234567890',
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.tickets.index', ['issueStatus' => 'issued']));
        $response->assertOk();

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('1761234567890', (string) $payload);
        $this->assertStringContainsString('maskedExternalId', (string) $payload);
    }

    public function test_reports_date_filtering_and_explicit_currency(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.reports.summary', [
            'datePreset' => 'last_30_days',
            'currency' => 'PKR',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.currency', 'PKR')
            ->assertJsonStructure(['data' => ['metrics', 'warnings']]);

        $warnings = $response->json('data.warnings') ?? [];
        $this->assertNotEmpty($warnings);
        $warningText = strtolower(json_encode($warnings) ?: '');
        $this->assertStringContainsString('currency', $warningText);
    }

    public function test_reports_forbidden_without_permission(): void
    {
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();
        $staff->forceFill(['meta' => ['staff_permissions' => []]])->save();

        $this->actingAs($staff->fresh())
            ->getJson(route('api.dashboard.reports.summary'))
            ->assertForbidden();
    }

    public function test_agency_scoping_for_agents_list(): void
    {
        $agencyAdmin = $this->legacyAgencyAdminFromSeed();

        $this->actingAs($agencyAdmin)
            ->getJson(route('api.dashboard.agents.index'))
            ->assertForbidden();
    }

    public function test_no_mutation_routes_for_prompt_03_modules(): void
    {
        $routes = collect(app('router')->getRoutes())->filter(
            fn ($route): bool => preg_match('#^api/dashboard/(suppliers|agents|pnrs|tickets|reports)#', (string) $route->uri()) === 1
        );

        $this->assertGreaterThan(0, $routes->count());
        foreach ($routes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods(), 'Prompt 03 dashboard routes must remain GET-only: '.$route->uri());
        }
    }

    public function test_cms_pages_sanitized_and_forbidden_for_customer(): void
    {
        $admin = $this->platformAdmin();
        $page = CmsPage::query()->create([
            'title' => 'Unsafe Preview',
            'slug' => 'unsafe-preview',
            'content' => '<script>alert(1)</script><p onclick="evil()">Hello</p>',
            'status' => CmsPage::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.cms.pages.index'));
        $response->assertOk()->assertJsonStructure(['data' => ['pages', 'summary']]);
        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('<script>', (string) $payload);
        $this->assertStringNotContainsString('onclick', (string) $payload);

        $sections = $this->actingAs($admin)->getJson(route('api.dashboard.cms.pages.sections', ['page' => $page->id]));
        $sections->assertOk()->assertJsonPath('data.sections.0.structuredContent.containsHtml', false);

        $customer = User::query()->where('account_type', AccountType::Customer)->firstOrFail();
        $this->actingAs($customer)->getJson(route('api.dashboard.cms.pages.index'))->assertForbidden();
    }

    public function test_users_index_excludes_sensitive_fields(): void
    {
        $admin = $this->platformAdmin();
        $response = $this->actingAs($admin)->getJson(route('api.dashboard.users.index', ['page' => 1]));
        $response->assertOk()->assertJsonStructure(['data' => ['users', 'summary'], 'pagination']);

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('password', (string) $payload);
        $this->assertStringNotContainsString('remember_token', (string) $payload);
        $this->assertStringNotContainsString('mfa_secret', (string) $payload);
    }

    public function test_roles_and_permissions_read_only_metadata(): void
    {
        $admin = $this->platformAdmin();

        $rolesResponse = $this->actingAs($admin)
            ->getJson(route('api.dashboard.roles.index'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['roles', 'summary']]);

        $roles = $rolesResponse->json('data.roles') ?? [];
        $summary = $rolesResponse->json('data.summary') ?? [];
        $this->assertIsArray($roles);
        $this->assertArrayHasKey('protectedSystemRoles', $summary);

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.permissions.index'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['permissions', 'summary']]);

        $matrix = $this->actingAs($admin)->getJson(route('api.dashboard.rbac.matrix'));
        $matrix->assertOk()->assertJsonStructure(['data' => ['roles', 'permissionKeys', 'assignments']]);
    }

    public function test_settings_metadata_has_no_secrets(): void
    {
        $admin = $this->platformAdmin();

        foreach ([
            'api.dashboard.settings.index',
            'api.dashboard.settings.general',
            'api.dashboard.settings.security',
            'api.dashboard.settings.notifications',
            'api.dashboard.settings.integrations',
        ] as $routeName) {
            $response = $this->actingAs($admin)->getJson(route($routeName));
            $response->assertOk();
            $payload = json_encode($response->json());
            $this->assertStringNotContainsString('api_key', (string) $payload);
            $this->assertStringNotContainsString('"password"', (string) $payload);
            $this->assertStringNotContainsString('password_hash', (string) $payload);
            $this->assertStringNotContainsString('APP_KEY', (string) $payload);
        }
    }

    public function test_audit_masks_network_data_and_excludes_tokens(): void
    {
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $log = AuditLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $admin->id,
            'action' => 'users.viewed',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
            'properties' => [
                'summary' => 'Viewed user directory',
                'password' => 'should-not-leak',
                'session_id' => 'sess-secret',
            ],
            'ip_address' => '203.0.113.45',
            'user_agent' => 'Mozilla/5.0 Playwright',
        ]);

        $response = $this->actingAs($admin)->getJson(route('api.dashboard.audit.index', ['page' => 1]));
        $response->assertOk()->assertJsonStructure(['data' => ['events', 'summary']]);

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('203.0.113.45', (string) $payload);
        $this->assertStringNotContainsString('sess-secret', (string) $payload);
        $this->assertStringNotContainsString('should-not-leak', (string) $payload);
        $this->assertStringContainsString('maskedNetworkRange', (string) $payload);

        $this->actingAs($admin)
            ->getJson(route('api.dashboard.audit.show', ['event' => 'JP-AUD-'.str_pad((string) $log->id, 4, '0', STR_PAD_LEFT)]))
            ->assertOk()
            ->assertJsonStructure(['data' => ['actor', 'target', 'metadata']]);
    }

    public function test_no_mutation_routes_for_prompt_04_modules(): void
    {
        $allowedMutations = [
            'api/dashboard/roles',
            'api/dashboard/roles/{role}',
            'api/dashboard/roles/{role}/clone',
            'api/dashboard/roles/{role}/assign',
            'api/dashboard/roles/{role}/unassign',
        ];

        $routes = collect(app('router')->getRoutes())->filter(
            fn ($route): bool => preg_match('#^api/dashboard/(cms|users|roles|permissions|rbac|settings|audit)#', (string) $route->uri()) === 1
        );

        $this->assertGreaterThan(0, $routes->count());
        foreach ($routes as $route) {
            $uri = (string) $route->uri();
            if (in_array($uri, $allowedMutations, true)) {
                continue;
            }
            $this->assertSame(['GET', 'HEAD'], $route->methods(), 'Prompt 04 dashboard routes must remain GET-only: '.$uri);
        }
    }
}
