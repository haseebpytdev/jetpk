<?php

namespace Tests\Unit\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Support\Suppliers\SupplierProviderFieldCatalog;
use App\Support\Suppliers\SupplierRegistry;
use Tests\TestCase;

class ApiProviderRegistryAuditTest extends TestCase
{
    public function test_every_enum_provider_has_credential_metadata(): void
    {
        foreach (SupplierProvider::cases() as $provider) {
            $fields = SupplierProviderFieldCatalog::fieldsFor($provider->value);
            $this->assertNotEmpty(
                $fields,
                'Provider '.$provider->value.' must expose credential metadata for API Connections management.'
            );
        }
    }

    public function test_installed_providers_include_flight_and_group_adapters(): void
    {
        $installed = [];
        foreach (SupplierProvider::cases() as $provider) {
            if (SupplierRegistry::adapterInstalled($provider)) {
                $installed[] = $provider->value;
            }
        }

        $this->assertContains(SupplierProvider::Sabre->value, $installed);
        $this->assertContains(SupplierProvider::PiaNdc->value, $installed);
        $this->assertContains(SupplierProvider::Airblue->value, $installed);
        $this->assertContains(SupplierProvider::Duffel->value, $installed);
        $this->assertContains(SupplierProvider::AlHaider->value, $installed);
    }

    public function test_airblue_credential_fields_are_zapways_only(): void
    {
        $fields = collect(SupplierProviderFieldCatalog::fieldsFor(SupplierProvider::Airblue->value));
        $this->assertNull($fields->firstWhere('key', 'api_channel'));

        $channelScoped = $fields->filter(fn (array $field): bool => isset($field['channel']))->pluck('channel')->unique()->values()->all();
        $this->assertContains('zapways_ota', $channelScoped);
        $this->assertNotContains('crane_ndc', $channelScoped);
        $this->assertNotNull($fields->firstWhere('key', 'client_id'));
    }

    public function test_al_haider_manual_token_fields_are_metadata_driven(): void
    {
        $fields = collect(SupplierProviderFieldCatalog::fieldsFor(SupplierProvider::AlHaider->value));
        $authMode = $fields->firstWhere('key', 'auth_mode');
        $this->assertNotNull($authMode);
        $this->assertNotEmpty($authMode['options'] ?? []);

        $tokenModeFields = $fields->filter(fn (array $field): bool => ($field['channel'] ?? null) === 'token_modes')->pluck('key')->all();
        $this->assertContains('existing_token', $tokenModeFields);
        $this->assertContains('token_expires_at', $tokenModeFields);
    }

    public function test_pending_providers_remain_catalog_visible_without_live_adapter(): void
    {
        foreach ([SupplierProvider::Iati, SupplierProvider::OneApi, SupplierProvider::Amadeus, SupplierProvider::Travelport] as $provider) {
            $fields = SupplierProviderFieldCatalog::fieldsFor($provider->value);
            $this->assertNotEmpty($fields, $provider->value.' must remain administrable while pending activation.');
        }
    }
}
