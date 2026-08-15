<?php

namespace Tests\Unit\Suppliers\AlHaider;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AlHaider\AlHaiderClient;
use App\Services\Suppliers\AlHaider\AlHaiderProviderException;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlHaiderClientManualTokenConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('suppliers.al_haider.enabled', true);
        Config::set('suppliers.al_haider.token', '');
        Config::set('suppliers.al_haider.username', 'fallback-user@example.test');
        Config::set('suppliers.al_haider.password', 'fallback-password');
        Config::set('suppliers.al_haider.default_base_url', 'https://alhaider.test');
        Config::set('suppliers.al_haider.login_path', '/api/login');
        Config::set('suppliers.al_haider.groups_path', '/api/available/groups');
        Config::set('suppliers.al_haider.token_generation_enabled', true);
    }

    public function test_manual_token_connection_is_used_without_login(): void
    {
        $this->seedConnectionToken('connection-manual-token');

        Http::fake([
            'alhaider.test/api/available/groups*' => function ($request) {
                $this->assertSame('Bearer connection-manual-token', $request->header('Authorization')[0] ?? '');

                return Http::response(['groups' => []], 200);
            },
        ]);

        app(AlHaiderClient::class)->listGroups();

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
    }

    public function test_manual_token_401_does_not_retry_login(): void
    {
        $this->seedConnectionToken('rejected-manual-token');

        Http::fake([
            'alhaider.test/api/available/groups*' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);

        $this->expectException(AlHaiderProviderException::class);
        $this->expectExceptionMessage('Token expired / rejected');

        try {
            app(AlHaiderClient::class)->listGroups();
        } finally {
            Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '/api/login'));
        }
    }

    public function test_expired_manual_token_reports_token_expired(): void
    {
        $agency = Agency::factory()->create();
        SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::AlHaider,
            'name' => 'Expired token',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'expired-token',
                'token_expires_at' => '2000-01-01',
            ],
        ]);

        Http::fake();

        $this->expectException(AlHaiderProviderException::class);
        $this->expectExceptionMessage('Token expired / rejected');

        app(AlHaiderClient::class)->listGroups();
    }

    public function test_connection_base_url_override_is_used_for_requests(): void
    {
        $agency = Agency::factory()->create();
        SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::AlHaider,
            'name' => 'Custom base',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://custom-alhaider.test',
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'custom-base-token',
            ],
        ]);

        Http::fake([
            'custom-alhaider.test/api/available/groups*' => Http::response(['groups' => []], 200),
        ]);

        app(AlHaiderClient::class)->listGroups();

        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'custom-alhaider.test'));
    }

    private function seedConnectionToken(string $token): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->firstOrFail();

        SupplierConnection::query()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::AlHaider,
            'name' => 'Manual token connection',
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => $token,
            ],
        ]);
    }
}
