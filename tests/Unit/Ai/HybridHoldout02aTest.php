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

class HybridHoldout02aTest extends TestCase
{
    public function test_unseen_holdout_meets_targets(): void
    {
        $path = base_path('tests/Fixtures/ai/hybrid-holdout-02a.json');
        $this->assertFileExists($path);
        $raw = json_decode((string) file_get_contents($path), true);
        $turns = $raw['turns'] ?? [];
        $this->assertGreaterThanOrEqual(500, count($turns));

        $pipeline = $this->pipeline();
        $now = Carbon::parse((string) ($raw['anchor_date'] ?? '2026-09-01'));

        $stats = [
            'intent_ok' => 0, 'intent_n' => 0,
            'critical_ok' => 0, 'critical_n' => 0,
            'clarify_ok' => 0, 'clarify_n' => 0,
            'wrong_confident' => 0,
            'followup_ok' => 0, 'followup_n' => 0,
            'by_lang' => [],
        ];

        foreach ($turns as $turn) {
            $lang = (string) ($turn['lang'] ?? 'en');
            $stats['by_lang'][$lang] ??= ['ok' => 0, 'n' => 0];
            $result = $pipeline->parse((string) $turn['message'], $turn['prior'] ?? null, $now);
            $expectClarify = (bool) ($turn['clarify'] ?? false);
            $stats['clarify_n']++;
            if ($expectClarify === $result->clarificationRequired) {
                $stats['clarify_ok']++;
            }
            if ($result->intent->isSearchable() && $expectClarify && ! $result->clarificationRequired) {
                $stats['wrong_confident']++;
            }

            $expect = $turn['expect'] ?? null;
            if (is_array($expect) && isset($expect['intent'])) {
                $stats['intent_n']++;
                $stats['by_lang'][$lang]['n']++;
                if ($result->intent->intent === $expect['intent']) {
                    $stats['intent_ok']++;
                    $stats['by_lang'][$lang]['ok']++;
                }
                $critKeys = array_intersect(array_keys($expect), ['origin', 'destination', 'depart_date', 'adults']);
                if ($critKeys !== []) {
                    $stats['critical_n']++;
                    $ok = true;
                    foreach ($critKeys as $ck) {
                        $actual = match ($ck) {
                            'origin' => $result->intent->origin,
                            'destination' => $result->intent->destination,
                            'depart_date' => $result->intent->departDate,
                            'adults' => $result->intent->adults,
                            default => null,
                        };
                        if ($expect[$ck] != $actual) {
                            $ok = false;
                            if ($result->intent->isSearchable() && in_array($ck, ['origin', 'destination', 'depart_date'], true)) {
                                $stats['wrong_confident']++;
                            }
                        }
                    }
                    if ($ok) {
                        $stats['critical_ok']++;
                    }
                }
            } elseif ($expectClarify) {
                $stats['by_lang'][$lang]['n']++;
                if ($result->clarificationRequired) {
                    $stats['by_lang'][$lang]['ok']++;
                }
            }

            if (($turn['kind'] ?? '') === 'followup') {
                $stats['followup_n']++;
                $ok = true;
                if (is_array($expect)) {
                    foreach ($expect as $k => $v) {
                        $actual = match ($k) {
                            'depart_date' => $result->intent->departDate,
                            'airline' => $result->intent->airline,
                            'budget' => $result->intent->budget,
                            'max_stops' => $result->intent->maxStops,
                            default => null,
                        };
                        if ($v != $actual) {
                            $ok = false;
                        }
                    }
                }
                if (($turn['prior']['origin'] ?? null) && $result->intent->origin !== $turn['prior']['origin']) {
                    $ok = false;
                }
                if ($ok) {
                    $stats['followup_ok']++;
                }
            }
        }

        $pct = static fn (int $ok, int $n): float => $n === 0 ? 100.0 : round(100.0 * $ok / $n, 2);
        $langAcc = function (string $l) use ($stats, $pct): float {
            return $pct($stats['by_lang'][$l]['ok'] ?? 0, $stats['by_lang'][$l]['n'] ?? 0);
        };

        $report = [
            'HOLDOUT_TURNS' => count($turns),
            'HOLDOUT_INTENT_ACCURACY' => $pct($stats['intent_ok'], $stats['intent_n']),
            'HOLDOUT_CRITICAL_FIELD_ACCURACY' => $pct($stats['critical_ok'], $stats['critical_n']),
            'HOLDOUT_ENGLISH_ACCURACY' => $langAcc('en'),
            'HOLDOUT_ROMAN_URDU_ACCURACY' => $langAcc('ru'),
            'HOLDOUT_URDU_ACCURACY' => $langAcc('ur'),
            'HOLDOUT_MIXED_ACCURACY' => $langAcc('mixed'),
            'HOLDOUT_FOLLOWUP_ACCURACY' => $pct($stats['followup_ok'], $stats['followup_n']),
            'HOLDOUT_CLARIFICATION_ACCURACY' => $pct($stats['clarify_ok'], $stats['clarify_n']),
            'HOLDOUT_WRONG_CONFIDENT_SEARCHES' => $stats['wrong_confident'],
        ];

        $dir = base_path('docs/evidence/jp-ai-assist-02a');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/holdout-quality.json', json_encode($report, JSON_PRETTY_PRINT));

        $this->assertSame(0, $stats['wrong_confident']);
        $this->assertGreaterThanOrEqual(98.0, $report['HOLDOUT_INTENT_ACCURACY']);
        $this->assertGreaterThanOrEqual(99.0, $report['HOLDOUT_CRITICAL_FIELD_ACCURACY']);
        $this->assertGreaterThanOrEqual(99.0, $report['HOLDOUT_ENGLISH_ACCURACY']);
        $this->assertGreaterThanOrEqual(97.0, $report['HOLDOUT_ROMAN_URDU_ACCURACY']);
        $this->assertGreaterThanOrEqual(95.0, $report['HOLDOUT_URDU_ACCURACY']);
        $this->assertGreaterThanOrEqual(98.0, $report['HOLDOUT_FOLLOWUP_ACCURACY']);
        $this->assertGreaterThanOrEqual(99.0, $report['HOLDOUT_CLARIFICATION_ACCURACY']);
    }

    public function test_metamorphic_language_invariance(): void
    {
        $pipeline = $this->pipeline();
        $now = Carbon::parse('2026-09-01');
        $variants = [
            'Lahore to Dubai on 20 Sep',
            'Dubai from Lahore on 20 Sep',
            'LHE DXB 20 Sep',
            'lahore TO dubai ON 20 sep',
            'Please kindly find Lahore to Dubai on 20 Sep!!!',
            'Lahore se Dubai 20 Sep',
        ];
        $base = null;
        foreach ($variants as $msg) {
            $r = $pipeline->parse($msg, null, $now);
            $this->assertFalse($r->clarificationRequired, $msg);
            $this->assertSame('LHE', $r->intent->origin, $msg);
            $this->assertSame('DXB', $r->intent->destination, $msg);
            $this->assertSame('2026-09-20', $r->intent->departDate, $msg);
            $base ??= $r->intent->toArray();
        }
        $this->assertNotNull($base);
    }

    public function test_latency_units_are_milliseconds(): void
    {
        $pipeline = $this->pipeline();
        $norm = new LanguageNormalizer;
        $loc = new LocationResolver;
        $now = Carbon::parse('2026-09-01');
        $msg = 'Multan to Abu Dhabi on 20 Sep 2 adults';
        $parse = [];
        $normS = [];
        $canon = [];
        for ($i = 0; $i < 30; $i++) {
            $t0 = hrtime(true);
            $n = $norm->normalize($msg);
            $normS[] = (hrtime(true) - $t0) / 1e6;
            $t1 = hrtime(true);
            $loc->extractRoute($n['normalized'], $n['original']);
            $canon[] = (hrtime(true) - $t1) / 1e6;
            $t2 = hrtime(true);
            $pipeline->parse($msg, null, $now);
            $parse[] = (hrtime(true) - $t2) / 1e6;
        }
        $p = static function (array $xs, float $q): float {
            sort($xs);

            return round($xs[(int) floor($q * (count($xs) - 1))], 3);
        };
        $report = [
            'LANGUAGE_LATENCY_UNIT' => 'milliseconds',
            'NORMALIZER_P50_MS' => $p($normS, 0.5),
            'NORMALIZER_P95_MS' => $p($normS, 0.95),
            'PARSER_P50_MS' => $p($parse, 0.5),
            'PARSER_P95_MS' => $p($parse, 0.95),
            'CANONICALIZER_P50_MS' => $p($canon, 0.5),
            'CANONICALIZER_P95_MS' => $p($canon, 0.95),
            'TOTAL_LANGUAGE_PIPELINE_P50_MS' => $p($parse, 0.5),
            'TOTAL_LANGUAGE_PIPELINE_P95_MS' => $p($parse, 0.95),
        ];
        $dir = base_path('docs/evidence/jp-ai-assist-02a');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/latency-units.json', json_encode($report, JSON_PRETTY_PRINT));
        $this->assertSame('milliseconds', $report['LANGUAGE_LATENCY_UNIT']);
        $this->assertLessThan(100.0, $report['TOTAL_LANGUAGE_PIPELINE_P95_MS']);
    }

    private function pipeline(): HybridTravelPipeline
    {
        return new HybridTravelPipeline(
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
    }
}
