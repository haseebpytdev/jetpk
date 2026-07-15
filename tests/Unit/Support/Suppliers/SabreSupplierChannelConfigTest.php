<?php

namespace Tests\Unit\Support\Suppliers;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use Tests\TestCase;

class SabreSupplierChannelConfigTest extends TestCase
{
    public function test_defaults_gds_on_and_ndc_off_when_settings_missing(): void
    {
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Sabre,
            'settings' => [],
        ]);

        $config = SabreSupplierChannelConfig::fromConnection($connection);

        $this->assertTrue($config->gdsEnabled);
        $this->assertFalse($config->ndcEnabled);
        $this->assertTrue(SabreSupplierChannelConfig::anyChannelEnabled($connection));
    }

    public function test_offer_label_prefers_explicit_source_type(): void
    {
        $this->assertSame('Sabre NDC', SabreSupplierChannelConfig::offerLabel('sabre', 'NDC', null));
        $this->assertSame('Sabre GDS', SabreSupplierChannelConfig::offerLabel('sabre', 'atpco', null));
    }

    public function test_connection_label_reflects_enabled_channels(): void
    {
        $connection = new SupplierConnection([
            'provider' => SupplierProvider::Sabre,
            'settings' => [
                'sabre_gds_enabled' => true,
                'sabre_ndc_enabled' => true,
            ],
        ]);

        $this->assertSame('Sabre', SabreSupplierChannelConfig::connectionAdminLabel($connection));

        $connection->settings = ['sabre_gds_enabled' => true, 'sabre_ndc_enabled' => false];
        $this->assertSame('Sabre GDS', SabreSupplierChannelConfig::connectionAdminLabel($connection));

        $connection->settings = ['sabre_gds_enabled' => false, 'sabre_ndc_enabled' => true];
        $this->assertSame('Sabre NDC', SabreSupplierChannelConfig::connectionAdminLabel($connection));
    }
}
