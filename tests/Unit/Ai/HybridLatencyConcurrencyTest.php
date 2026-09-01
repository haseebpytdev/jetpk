<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Hybrid\AirlineResolver;
use App\Services\Ai\Hybrid\BudgetNormalizer;
use App\Services\Ai\Hybrid\ClarificationBuilder;
use App\Services\Ai\Hybrid\DateExpressionResolver;
use App\Services\Ai\Hybrid\HybridTravelPipeline;
use App\Services\Ai\Hybrid\IntentConfidenceGate;
use App\Services\Ai\Hybrid\LanguageNormalizer;
use App\Services\Ai\Hybrid\LocationResolver;
use App\Services\Ai\Hybrid\PassengerExpressionResolver;
use App\Services\Ai\Hybrid\TravelConstraintResolver;
use Carbon\Carbon;
use Tests\TestCase;

class HybridLatencyConcurrencyTest extends TestCase
{
    public function test_pipeline_latency_and_concurrency_local(): void
    {
        $pipeline = new HybridTravelPipeline(
            new LanguageNormalizer,
            new LocationResolver,
            new AirlineResolver,
            new DateExpressionResolver,
            new BudgetNormalizer,
            new PassengerExpressionResolver,
            new TravelConstraintResolver,
            new ClarificationBuilder,
            new IntentConfidenceGate,
        );
        $now = Carbon::parse('2026-09-01');
        $msg = 'Lahore to Dubai on 18 Sep 2 adults';

        $norm = new LanguageNormalizer;
        $loc = new LocationResolver;
        $normSamples = [];
        $parseSamples = [];
        $canonSamples = [];
        for ($i = 0; $i < 40; $i++) {
            $t0 = hrtime(true);
            $n = $norm->normalize($msg);
            $normSamples[] = (hrtime(true) - $t0) / 1e6;

            $t1 = hrtime(true);
            $loc->extractRoute($n['normalized'], $n['original']);
            $canonSamples[] = (hrtime(true) - $t1) / 1e6;

            $t2 = hrtime(true);
            $pipeline->parse($msg, null, $now);
            $parseSamples[] = (hrtime(true) - $t2) / 1e6;
        }

        $p95 = static function (array $xs): float {
            sort($xs);
            $idx = (int) floor(0.95 * (count($xs) - 1));

            return round($xs[$idx], 3);
        };
        $p50 = static function (array $xs): float {
            sort($xs);
            $idx = (int) floor(0.50 * (count($xs) - 1));

            return round($xs[$idx], 3);
        };

        $concurrency = [];
        foreach ([1, 10, 50] as $n) {
            $samples = [];
            for ($round = 0; $round < 5; $round++) {
                $t0 = hrtime(true);
                for ($i = 0; $i < $n; $i++) {
                    $pipeline->parse($msg, null, $now);
                }
                $samples[] = ((hrtime(true) - $t0) / 1e6) / $n;
            }
            $concurrency[$n] = $p95($samples);
        }

        $report = [
            'NORMALIZER_P50' => $p50($normSamples),
            'NORMALIZER_P95' => $p95($normSamples),
            'PARSER_P50' => $p50($parseSamples),
            'PARSER_P95' => $p95($parseSamples),
            'CANONICALIZER_P50' => $p50($canonSamples),
            'CANONICALIZER_P95' => $p95($canonSamples),
            'TOTAL_LANGUAGE_PIPELINE_P50' => $p50($parseSamples),
            'TOTAL_LANGUAGE_PIPELINE_P95' => $p95($parseSamples),
            'PARSER_CONCURRENCY_1_P95' => $concurrency[1],
            'PARSER_CONCURRENCY_10_P95' => $concurrency[10],
            'PARSER_CONCURRENCY_50_P95' => $concurrency[50],
        ];

        $dir = base_path('docs/evidence/jp-ai-assist-01f');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/latency.json', json_encode($report, JSON_PRETTY_PRINT));
        file_put_contents($dir.'/scalability.json', json_encode([
            'PARSER_CONCURRENCY_1_P95' => $concurrency[1],
            'PARSER_CONCURRENCY_10_P95' => $concurrency[10],
            'PARSER_CONCURRENCY_50_P95' => $concurrency[50],
        ], JSON_PRETTY_PRINT));

        $this->assertLessThan(100.0, $report['TOTAL_LANGUAGE_PIPELINE_P95']);
    }
}
