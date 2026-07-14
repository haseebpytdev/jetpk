<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\MarkupRule;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase20AProductionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_pages_render_safe_copy_for_common_statuses(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/_phase20a/errors/401', fn () => abort(401));
            Route::get('/_phase20a/errors/403', fn () => abort(403));
            Route::get('/_phase20a/errors/404', fn () => abort(404));
            Route::get('/_phase20a/errors/419', fn () => abort(419));
            Route::get('/_phase20a/errors/500', fn () => abort(500));
            Route::get('/_phase20a/errors/503', fn () => abort(503));
            Route::get('/_phase20a/errors/throw-403', fn () => throw new AuthorizationException('forbidden'));
        });

        $this->get('/_phase20a/errors/401')->assertStatus(401)->assertSee('Please sign in to continue.');
        $this->get('/_phase20a/errors/403')->assertStatus(403)->assertSee('You do not have permission to access this area.');
        $this->get('/_phase20a/errors/404')->assertStatus(404)->assertSee('The page you are looking for could not be found.');
        $this->get('/_phase20a/errors/419')->assertStatus(419)->assertSee('Your session expired. Please refresh and try again.');
        $this->get('/_phase20a/errors/500')->assertStatus(500)->assertSee('Something went wrong on our side.');
        $this->get('/_phase20a/errors/503')->assertStatus(503)->assertSee('The service is temporarily unavailable.');
        $this->get('/_phase20a/errors/throw-403')->assertStatus(403)->assertSee('forbidden');
    }

    public function test_rate_limited_route_renders_clean_429_page(): void
    {
        Route::middleware(['web', 'throttle:1,1'])->get('/_phase20a/errors/429', fn () => 'ok');

        $this->get('/_phase20a/errors/429')->assertOk();
        $this->get('/_phase20a/errors/429')->assertStatus(429)->assertSee('Too many requests. Please wait a moment and try again.');
    }

    public function test_error_pages_do_not_expose_sensitive_or_raw_exception_markers(): void
    {
        Route::middleware('web')->get('/_phase20a/errors/500', fn () => abort(500));
        $response = $this->get('/_phase20a/errors/500');
        $response->assertStatus(500);

        foreach (['SQLSTATE', 'stack trace', 'APP_KEY', 'password', 'token', 'client_secret', 'C:\\', '/var/www'] as $sensitive) {
            $response->assertDontSee($sensitive);
        }
    }

    public function test_json_error_responses_are_generic_and_safe(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/_phase20a/errors/json-403', fn () => throw new AuthorizationException('raw internal detail'));
            Route::get('/_phase20a/errors/json-db', function (): void {
                DB::table('missing_table_for_phase20a')->count();
            });
        });

        $this->getJson('/_phase20a/errors/json-403')
            ->assertStatus(403)
            ->assertJson(['message' => 'You do not have permission to access this area.']);

        $dbResponse = $this->getJson('/_phase20a/errors/json-db')
            ->assertStatus(500)
            ->assertJson(['message' => 'Something went wrong on our side.']);
        $this->assertStringNotContainsString('SQLSTATE', $dbResponse->getContent());
    }

    public function test_homepage_returns_200_when_branding_tables_or_data_are_missing(): void
    {
        $this->get(route('home'))->assertOk();

        Schema::dropIfExists('agency_settings');
        Schema::dropIfExists('agency_homepage_sections');
        Schema::dropIfExists('agency_media');
        Schema::dropIfExists('agencies');

        $this->get(route('home'))->assertOk();
    }

    public function test_homepage_uses_db_settings_when_present_and_escapes_unsafe_html(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agency->agencySetting()->updateOrCreate(
            ['agency_id' => $agency->id],
            ['display_name' => 'Aurora DB Brand', 'tagline' => '<script>alert(1)</script>']
        );

        $response = $this->get(route('home'))->assertOk();
        $response->assertSee('Aurora DB Brand');
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_product_ui_routes_are_db_wired_and_professional_copy(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Pending,
            'booking_reference' => 'BKG-PHASE20A',
            'payment_status' => 'unpaid',
            'route' => 'LHE-KHI',
        ]);
        MarkupRule::factory()->create(['agency_id' => $admin->current_agency_id]);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Operator command center')
            ->assertDontSee('Demo only');
        $this->actingAs($admin)->get(route('admin.bookings'))->assertOk()->assertSee('BKG-PHASE20A');
        $this->actingAs($admin)->get(route('admin.reports'))->assertOk()->assertSee('Reports &amp; Analytics', false);
        $this->actingAs($admin)->get(route('admin.markups'))->assertOk();
        $this->actingAs($admin)->get(route('admin.api-settings'))->assertOk();

        $this->actingAs($staff)->get(route('staff.bookings.index'))->assertOk();
        $this->actingAs($agent)->get(route('agent.bookings.index'))->assertOk();
    }

    public function test_customer_and_agent_portals_use_authenticated_db_backed_access(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);
        Booking::factory()->create(['agency_id' => $agency->id, 'customer_id' => $customer->id]);

        $this->get(route('customer.bookings.index'))->assertRedirectContains('/login');
        $this->actingAs($customer)->get(route('customer.bookings.index'))->assertOk();
        $this->actingAs($agent)->get(route('customer.bookings.index'))->assertForbidden();
    }
}
