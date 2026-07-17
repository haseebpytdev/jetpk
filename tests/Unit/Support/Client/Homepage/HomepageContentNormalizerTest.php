<?php

namespace Tests\Unit\Support\Client\Homepage;

use App\Support\Client\Homepage\HomepageContentNormalizer;
use Tests\TestCase;

class HomepageContentNormalizerTest extends TestCase
{
    public function test_migrates_stale_alias_when_canonical_key_absent(): void
    {
        $result = (new HomepageContentNormalizer)->normalize([
            'groups' => ['enabled' => '0', 'title' => 'Old Groups Title'],
        ]);

        $this->assertSame('0', data_get($result['content'], 'group_cards.enabled'));
        $this->assertSame('Old Groups Title', data_get($result['content'], 'group_cards.title'));
        $this->assertFalse(\Illuminate\Support\Arr::has($result['content'], 'groups.enabled'));
        $this->assertFalse(\Illuminate\Support\Arr::has($result['content'], 'groups.title'));
        $this->assertContains(['old' => 'groups.enabled', 'new' => 'group_cards.enabled'], $result['report']['aliases_migrated']);
    }

    public function test_canonical_value_wins_when_both_old_and_new_present(): void
    {
        $result = (new HomepageContentNormalizer)->normalize([
            'groups' => ['title' => 'Stale Old Title'],
            'group_cards' => ['title' => 'Current Real Title'],
        ]);

        $this->assertSame('Current Real Title', data_get($result['content'], 'group_cards.title'));
        $this->assertFalse(\Illuminate\Support\Arr::has($result['content'], 'groups.title'));
        $this->assertContains(
            ['old' => 'groups.title', 'new' => 'group_cards.title', 'reason' => 'canonical_key_already_present'],
            $result['report']['aliases_dropped'],
        );
    }

    public function test_no_fallback_invented_when_neither_key_present(): void
    {
        $result = (new HomepageContentNormalizer)->normalize([
            'hero' => ['headline' => 'Something'],
        ]);

        $this->assertFalse(\Illuminate\Support\Arr::has($result['content'], 'group_cards.title'));
        $this->assertSame('Something', data_get($result['content'], 'hero.headline'));
    }

    public function test_presence_aware_falsy_values_survive_migration_exactly(): void
    {
        $result = (new HomepageContentNormalizer)->normalize([
            'groups' => ['subtitle' => '', 'cta_text' => false, 'cta_url' => null],
        ]);

        $this->assertSame('', data_get($result['content'], 'group_cards.subtitle'));
        $this->assertFalse(data_get($result['content'], 'group_cards.cta_text'));
        $this->assertTrue(\Illuminate\Support\Arr::has($result['content'], 'group_cards.cta_url'));
        $this->assertNull(data_get($result['content'], 'group_cards.cta_url'));
    }

    public function test_normalization_is_idempotent(): void
    {
        $normalizer = new HomepageContentNormalizer;
        $input = ['groups' => ['enabled' => '1', 'title' => 'X'], 'hero' => ['headline' => 'Y']];

        $first = $normalizer->normalize($input)['content'];
        $second = $normalizer->normalize($first)['content'];

        $this->assertSame($first, $second);
    }

    public function test_unknown_top_level_key_passes_through_untouched(): void
    {
        $result = (new HomepageContentNormalizer)->normalize([
            'hero' => ['headline' => 'Real'],
            '_some_future_field_this_schema_has_never_seen' => ['nested' => 'value'],
        ]);

        $this->assertSame('value', data_get($result['content'], '_some_future_field_this_schema_has_never_seen.nested'));
        $this->assertContains('_some_future_field_this_schema_has_never_seen', $result['report']['unknown_top_level_keys']);
    }

    public function test_deprecated_group_card_fields_are_stripped_but_item_and_siblings_survive(): void
    {
        $result = (new HomepageContentNormalizer)->normalize([
            'group_cards' => [
                'items' => [
                    ['title' => 'Card One', 'route' => 'KHI-DXB', 'alt' => 'Some alt text', 'price' => 50000],
                ],
            ],
        ]);

        $item = data_get($result['content'], 'group_cards.items.0');
        $this->assertSame('Card One', $item['title']);
        $this->assertSame(50000, $item['price']);
        $this->assertArrayNotHasKey('route', $item);
        $this->assertArrayNotHasKey('alt', $item);
        $this->assertContains(['path' => 'group_cards.items.0.route', 'value' => 'KHI-DXB'], $result['report']['deprecated_stripped']);
    }

    public function test_schema_version_is_reported(): void
    {
        $result = (new HomepageContentNormalizer)->normalize(['hero' => ['headline' => 'X']]);

        $this->assertSame(HomepageContentNormalizer::SCHEMA_VERSION, $result['report']['schema_version']);
    }
}
