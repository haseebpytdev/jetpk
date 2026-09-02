<?php

namespace Tests\Unit\Services\Pricing;

use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupRuleType;
use App\Enums\MarkupValueType;
use App\Models\Agency;
use App\Models\MarkupRule;
use App\Services\Pricing\PricingRuleService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PricingRuleServiceRequestMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_markup_rules_are_loaded_once_per_agency_per_request(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->firstOrFail();

        MarkupRule::query()->create([
            'agency_id' => $agency->id,
            'name' => 'JP-LARAVEL-PERF-01 memo rule',
            'rule_type' => MarkupRuleType::Global->value,
            'value' => 1,
            'value_type' => MarkupValueType::Fixed->value,
            'priority' => 10,
            'status' => MarkupRuleStatus::Active->value,
        ]);

        $service = app(PricingRuleService::class);
        $contextA = [
            'route' => 'LHE-DXB',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'supplier' => 'sabre',
            'source_channel' => 'public_guest',
        ];
        $contextB = [
            'route' => 'LHE-DXB',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'airline' => 'pk',
            'supplier' => 'sabre',
            'source_channel' => 'public_guest',
        ];

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->getApplicableRules($agency, $contextA);
        $afterFirst = count(DB::getQueryLog());
        $service->getApplicableRules($agency, $contextB);
        $afterSecond = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertGreaterThan(0, $afterFirst);
        $this->assertSame($afterFirst, $afterSecond, 'Second getApplicableRules must not re-query markup_rules');
    }
}
