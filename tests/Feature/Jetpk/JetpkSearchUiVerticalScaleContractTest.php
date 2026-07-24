<?php

namespace Tests\Feature\Jetpk;

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Services\Homepage\JetpkHomepageContentValidator;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\Homepage\JetpkHomepageHeroSizing;
use App\Support\Client\JetpkHomepageSectionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JetpkHomepageFixture;
use Tests\TestCase;

class JetpkSearchUiVerticalScaleContractTest extends TestCase
{
    use JetpkHomepageFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedJetpkAirports();
        $this->seedJetpkAgency();
    }

    /**
     * @return array<int, array{percent: int, scale: float}>
     */
    private function scaleSteps(): array
    {
        return [
            ['percent' => 80, 'scale' => 0.8],
            ['percent' => 90, 'scale' => 0.9],
            ['percent' => 100, 'scale' => 1.0],
            ['percent' => 115, 'scale' => 1.15],
        ];
    }

    public function test_cms_percent_maps_directly_to_injected_css_scale(): void
    {
        $profile = $this->makeJetpkProfile();

        foreach ($this->scaleSteps() as $step) {
            $content = $this->representativeValidFourCardHomeContent();
            $content['hero']['search_ui_scale'] = (string) $step['percent'];
            $this->seedPublishedHome($profile, $content);

            $html = $this->get(route('home'))->assertOk()->getContent();
            $this->assertIsString($html);
            $this->assertStringContainsString(
                '--jp-search-ui-scale: '.$step['scale'],
                $html,
                'CMS '.$step['percent'].'% must map to scale '.$step['scale'],
            );
        }
    }

    public function test_css_contract_scales_height_not_width(): void
    {
        $tokens = file_get_contents(public_path('themes/frontend/jetpakistan/css/tokens.css')) ?: '';
        $theme = file_get_contents(public_path('themes/frontend/jetpakistan/css/theme.css')) ?: '';

        $this->assertStringContainsString('.jp-home{', $tokens);
        $this->assertStringContainsString('--jp-search-field-outer-min-height:calc(var(--jp-search-field-outer-height-base) * var(--jp-search-ui-scale, 1))', $tokens);
        $this->assertStringContainsString('--jp-search-card-padding-x:var(--jp-search-card-padding-x-base)', $tokens);
        $this->assertStringNotContainsString('--jp-search-box-max-width', $tokens.$theme);
        $this->assertStringNotContainsString('.jp-home #jp-flight-search.search{width:', $theme);
    }

    public function test_draft_search_scale_does_not_leak_before_publish(): void
    {
        $profile = $this->makeJetpkProfile();
        $published = $this->representativeValidFourCardHomeContent();
        $published['hero']['search_ui_scale'] = '100';
        $this->seedPublishedHome($profile, $published);

        ClientPageSetting::query()->updateOrCreate(
            ['client_profile_id' => $profile->id, 'page_key' => ClientPageKeys::HOME, 'status' => ClientPageSettingStatus::Draft],
            ['content_json' => array_merge($published, ['hero' => array_merge($published['hero'], ['search_ui_scale' => '115'])])],
        );

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('--jp-search-ui-scale: 1', $html);
        $this->assertStringNotContainsString('--jp-search-ui-scale: 1.15', $html);
    }

    public function test_validator_clamps_search_ui_scale(): void
    {
        $validator = app(JetpkHomepageContentValidator::class);
        $normalized = $validator->validateAndNormalize(ClientPageKeys::HOME, [
            'hero' => ['search_ui_scale' => '84'],
            'destinations' => ['items' => []],
            'routes' => ['items' => []],
        ]);

        $this->assertSame('84', data_get($normalized, 'hero.search_ui_scale'));
        $this->assertSame(0.84, JetpkHomepageHeroSizing::searchUiScaleDecimal('84'));
    }

    public function test_section_data_css_variables_use_direct_scale(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['hero']['search_ui_scale'] = '90';
        $this->seedPublishedHome($profile, $content);

        $vars = app(JetpkHomepageSectionData::class)->heroLayoutCssVariables();
        $this->assertSame('0.9', $vars['--jp-search-ui-scale']);
    }

    public function test_search_form_action_unchanged_across_scale_values(): void
    {
        $profile = $this->makeJetpkProfile();
        $content = $this->representativeValidFourCardHomeContent();
        $content['hero']['search_ui_scale'] = '80';
        $this->seedPublishedHome($profile, $content);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('name="trip_type"', $html);
        $this->assertStringContainsString('action="/flights/results"', $html);
    }
}
