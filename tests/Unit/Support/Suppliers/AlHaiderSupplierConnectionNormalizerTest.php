<?php

namespace Tests\Unit\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\AlHaiderSupplierConnectionNormalizer;
use Tests\TestCase;

class AlHaiderSupplierConnectionNormalizerTest extends TestCase
{
    public function test_manual_mode_blank_token_keeps_existing_value(): void
    {
        $existing = new SupplierConnection([
            'provider' => SupplierProvider::AlHaider,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'keep-me',
            ],
        ]);

        $normalized = AlHaiderSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::AlHaider->value,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => '',
            ],
        ], $existing);

        $this->assertSame('keep-me', $normalized['credentials']['existing_token']);
    }

    public function test_clear_existing_token_removes_stored_secret(): void
    {
        $existing = new SupplierConnection([
            'provider' => SupplierProvider::AlHaider,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'existing_token' => 'remove-me',
            ],
        ]);

        $normalized = AlHaiderSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::AlHaider->value,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_MANUAL,
                'clear_existing_token' => '1',
            ],
        ], $existing);

        $this->assertArrayNotHasKey('existing_token', $normalized['credentials']);
    }

    public function test_auto_mode_strips_manual_token_fields(): void
    {
        $normalized = AlHaiderSupplierConnectionNormalizer::normalizePayload([
            'provider' => SupplierProvider::AlHaider->value,
            'credentials' => [
                'auth_mode' => AlHaiderSupplierConnectionNormalizer::AUTH_MODE_AUTO,
                'existing_token' => 'should-not-remain',
                'username' => 'api-user',
                'password' => 'api-pass',
            ],
        ]);

        $this->assertArrayNotHasKey('existing_token', $normalized['credentials']);
        $this->assertSame('api-user', $normalized['credentials']['username']);
    }
}
