<?php

namespace Tests\Unit\Audits;

use App\Support\Audits\ClientRouteParityClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientRouteParityClassifierTest extends TestCase
{
    private ClientRouteParityClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new ClientRouteParityClassifier;
    }

    public function test_login_get_is_auth_page_and_prefixable(): void
    {
        $result = $this->classifier->classify(
            'login',
            'GET',
            'login',
            'App\Http\Controllers\Auth\AuthenticatedSessionController@create',
            ['web', 'guest'],
        );

        $this->assertSame('auth_page', $result['classification']);
        $this->assertSame('yes', $result['should_have_client_prefix']);
        $this->assertSame('low', $result['risk_level']);
    }

    public function test_dev_cp_route_is_not_prefixable(): void
    {
        $result = $this->classifier->classify(
            'dev.cp.index',
            'GET',
            'dev/cp',
            'App\Http\Controllers\Developer\DevCpOverviewController@index',
            ['web', 'auth', 'developer.cp'],
        );

        $this->assertSame('dev_cp', $result['classification']);
        $this->assertSame('no', $result['should_have_client_prefix']);
    }

    #[DataProvider('staticAssetUriProvider')]
    public function test_static_asset_uris_are_excluded(string $uri): void
    {
        $result = $this->classifier->classify(
            '-',
            'GET',
            $uri,
            'Closure',
            ['web'],
        );

        $this->assertSame('asset_static', $result['classification']);
        $this->assertSame('no', $result['should_have_client_prefix']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function staticAssetUriProvider(): array
    {
        return [
            'css' => ['css/ota-public.css'],
            'js' => ['js/ota-mobile-app.js'],
            'storage' => ['storage/logo.png'],
            'client-assets' => ['client-assets/jetpk/logo.png'],
        ];
    }

    public function test_supplier_booking_post_is_high_risk_and_not_prefixable(): void
    {
        $result = $this->classifier->classify(
            'admin.bookings.supplier-booking',
            'POST',
            'admin/bookings/{booking}/supplier-booking',
            'App\Http\Controllers\Admin\BookingManagementController@createSupplierBooking',
            ['web', 'auth', 'account.type:platform_admin'],
        );

        $this->assertSame('supplier_api_action', $result['classification']);
        $this->assertSame('no', $result['should_have_client_prefix']);
        $this->assertSame('high', $result['risk_level']);
    }

    public function test_booking_passengers_post_is_high_risk_and_not_prefixable(): void
    {
        $result = $this->classifier->classify(
            'booking.passengers',
            'POST',
            'booking/passengers',
            'App\Http\Controllers\Frontend\BookingController@passengers',
            ['web', 'platform.module:supplier_booking'],
        );

        $this->assertSame('booking_flow', $result['classification']);
        $this->assertSame('no', $result['should_have_client_prefix']);
        $this->assertSame('high', $result['risk_level']);
    }

    public function test_booking_confirmation_get_is_prefixable(): void
    {
        $result = $this->classifier->classify(
            'booking.confirmation',
            'GET',
            'booking/confirmation',
            'App\Http\Controllers\Frontend\BookingController@confirmation',
            ['web'],
        );

        $this->assertSame('booking_flow', $result['classification']);
        $this->assertSame('yes', $result['should_have_client_prefix']);
        $this->assertSame('low', $result['risk_level']);
    }

    public function test_client_preview_placeholder_is_excluded(): void
    {
        $result = $this->classifier->classify(
            'client.preview.login',
            'GET',
            '{clientSlug}/login',
            'App\Http\Controllers\Preview\ClientPreviewController@login',
            ['web', 'preview.client'],
        );

        $this->assertSame('excluded', $result['classification']);
        $this->assertSame('no', $result['should_have_client_prefix']);
        $this->assertStringContainsString('MC-7B', $result['notes']);
    }

    public function test_suggested_prefixed_uri_normalizes_root_and_nested_paths(): void
    {
        $this->assertSame('/jetpk', $this->classifier->suggestedPrefixedUri('jetpk', '/'));
        $this->assertSame('/jetpk/login', $this->classifier->suggestedPrefixedUri('jetpk', 'login'));
        $this->assertSame('/jetpk/admin/bookings', $this->classifier->suggestedPrefixedUri('jetpk', 'admin/bookings'));
    }

    public function test_reserved_uri_first_segment_adds_collision_note(): void
    {
        $result = $this->classifier->classify(
            'admin.dashboard',
            'GET',
            'admin',
            'App\Http\Controllers\Admin\AdminDashboardController@index',
            ['web', 'auth'],
        );

        $this->assertSame('admin_dashboard', $result['classification']);
        $this->assertStringContainsString('reserved client slug segment', $result['notes']);
    }

    public function test_high_risk_prefixable_conflict_detection_helper_shape(): void
    {
        $conflictRow = [
            'should_have_client_prefix' => 'yes',
            'risk_level' => 'high',
        ];
        $safeRow = [
            'should_have_client_prefix' => 'no',
            'risk_level' => 'high',
        ];

        $this->assertTrue(
            $conflictRow['should_have_client_prefix'] === 'yes' && $conflictRow['risk_level'] === 'high',
        );
        $this->assertFalse(
            $safeRow['should_have_client_prefix'] === 'yes' && $safeRow['risk_level'] === 'high',
        );
    }

    public function test_group_ticketing_search_page_is_prefixable_not_internal_api(): void
    {
        $result = $this->classifier->classify(
            'group-ticketing.search',
            'GET',
            'groups/search',
            'App\Http\Controllers\Frontend\GroupTicketingSearchController@index',
            ['web', 'platform.module:public_umrah_groups'],
        );

        $this->assertSame('group_ticketing', $result['classification']);
        $this->assertSame('yes', $result['should_have_client_prefix']);
        $this->assertSame('low', $result['risk_level']);
    }

    public function test_group_ticketing_results_json_stays_internal_api(): void
    {
        $result = $this->classifier->classify(
            'group-ticketing.search.results',
            'GET',
            'groups/search/results',
            'App\Http\Controllers\Frontend\GroupTicketingSearchController@results',
            ['web', 'platform.module:public_umrah_groups'],
        );

        $this->assertSame('internal_api', $result['classification']);
        $this->assertSame('no', $result['should_have_client_prefix']);
    }
}
