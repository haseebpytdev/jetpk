<?php

namespace Tests\Unit\Services\Client;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageContentResolver;
use App\Services\Client\CurrentClientContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPageContentResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_field_preserves_empty_string(): void
    {
        $resolver = app(ClientPageContentResolver::class);
        $saved = ['hero' => ['subtitle' => '']];

        $this->assertSame('', $resolver->resolveField($saved, 'hero.subtitle', 'Default subtitle'));
    }

    public function test_resolve_field_uses_default_when_key_absent(): void
    {
        $resolver = app(ClientPageContentResolver::class);
        $saved = ['hero' => ['headline' => 'Saved headline']];

        $this->assertSame('Default eyebrow', $resolver->resolveField($saved, 'hero.eyebrow', 'Default eyebrow'));
    }

    public function test_resolve_field_preserves_false_like_zero_string(): void
    {
        $resolver = app(ClientPageContentResolver::class);
        $saved = ['hero' => ['search_visible' => '0']];

        $this->assertSame('0', $resolver->resolveField($saved, 'hero.search_visible', '1'));
    }

    public function test_resolve_field_preserves_empty_array(): void
    {
        $resolver = app(ClientPageContentResolver::class);
        $saved = ['trust_chips' => []];

        $this->assertSame([], $resolver->resolveField($saved, 'trust_chips', [['label' => 'x']]));
    }

    public function test_published_empty_subtitle_survives_section_read(): void
    {
        $profile = ClientProfile::query()->create([
            'name' => 'JetPK',
            'slug' => 'jetpk-test',
            'environment' => 'staging',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-test',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);

        ClientPageSetting::query()->create([
            'client_profile_id' => $profile->id,
            'page_key' => 'home',
            'status' => ClientPageSettingStatus::Published,
            'content_json' => ['hero' => ['subtitle' => '']],
        ]);

        app(CurrentClientContext::class)->set($profile);
        $resolver = app(ClientPageContentResolver::class);

        $this->assertSame('', $resolver->section('home', 'hero.subtitle', 'Should not appear'));
    }
}
