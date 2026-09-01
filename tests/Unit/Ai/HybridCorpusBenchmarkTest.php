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

/**
 * Evaluates the JP-AI-ASSIST-01F hybrid corpus (not hard-coded into production).
 */
class HybridCorpusBenchmarkTest extends TestCase
{
    public function test_corpus_meets_quality_targets(): void
    {
        $path = base_path('tests/Fixtures/ai/hybrid-corpus-01f.json');
        $this->assertFileExists($path);
        $raw = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($raw);
        $turns = $raw['turns'] ?? [];
        $this->assertGreaterThanOrEqual(300, count($turns));

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
        $now = Carbon::parse((string) ($raw['anchor_date'] ?? '2026-09-01'));

        $stats = [
            'intent_ok' => 0,
            'intent_n' => 0,
            'critical_ok' => 0,
            'critical_n' => 0,
            'clarify_ok' => 0,
            'clarify_n' => 0,
            'wrong_confident' => 0,
            'llm_bypass' => 0,
            'by_lang' => [],
            'fields' => [
                'origin' => [0, 0], 'destination' => [0, 0], 'depart_date' => [0, 0],
                'return_date' => [0, 0], 'adults' => [0, 0], 'airline' => [0, 0],
                'budget' => [0, 0], 'max_stops' => [0, 0], 'ranking' => [0, 0],
            ],
            'followup_ok' => 0,
            'followup_n' => 0,
            'latencies' => [],
        ];

        foreach ($turns as $turn) {
            $lang = (string) ($turn['lang'] ?? 'en');
            $stats['by_lang'][$lang] ??= ['ok' => 0, 'n' => 0];
            $t0 = hrtime(true);
            $result = $pipeline->parse((string) $turn['message'], $turn['prior'] ?? null, $now);
            $stats['latencies'][] = (hrtime(true) - $t0) / 1e6;
            if ($result->llmBypassed) {
                $stats['llm_bypass']++;
            }

            $expectClarify = (bool) ($turn['clarify'] ?? false);
            $stats['clarify_n']++;
            if ($expectClarify === $result->clarificationRequired) {
                $stats['clarify_ok']++;
            }

            $expect = $turn['expect'] ?? null;
            if (is_array($expect) && isset($expect['intent'])) {
                $stats['intent_n']++;
                if ($result->intent->intent === $expect['intent']) {
                    $stats['intent_ok']++;
                    $stats['by_lang'][$lang]['ok']++;
                }
                $stats['by_lang'][$lang]['n']++;
            } elseif ($expectClarify) {
                $stats['by_lang'][$lang]['n']++;
                if ($result->clarificationRequired) {
                    $stats['by_lang'][$lang]['ok']++;
                }
            }

            if ($result->intent->isSearchable() && $result->clarificationRequired === false) {
                // Wrong-confident: searchable but expected clarify, or critical field mismatch.
                if ($expectClarify) {
                    $stats['wrong_confident']++;
                }
            }

            if (is_array($expect)) {
                foreach (['origin', 'destination', 'depart_date', 'return_date', 'adults', 'airline', 'budget', 'max_stops'] as $field) {
                    if (! array_key_exists($field, $expect)) {
                        continue;
                    }
                    $stats['fields'][$field][1]++;
                    $actual = match ($field) {
                        'origin' => $result->intent->origin,
                        'destination' => $result->intent->destination,
                        'depart_date' => $result->intent->departDate,
                        'return_date' => $result->intent->returnDate,
                        'adults' => $result->intent->adults,
                        'airline' => $result->intent->airline,
                        'budget' => $result->intent->budget,
                        'max_stops' => $result->intent->maxStops,
                        default => null,
                    };
                    $want = $expect[$field];
                    $match = $want === $actual || (is_numeric($want) && is_numeric($actual) && (float) $want === (float) $actual);
                    if ($match) {
                        $stats['fields'][$field][0]++;
                    } elseif ($result->intent->isSearchable() && in_array($field, ['origin', 'destination', 'depart_date'], true)) {
                        $stats['wrong_confident']++;
                    }
                }
                if (array_key_exists('ranking', $expect)) {
                    $stats['fields']['ranking'][1]++;
                    if ($result->rankingPreference === $expect['ranking']) {
                        $stats['fields']['ranking'][0]++;
                    }
                }

                // Critical fields bundle
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
                        if ($expect[$ck] !== $actual && ! (is_numeric($expect[$ck]) && (float) $expect[$ck] === (float) $actual)) {
                            $ok = false;
                        }
                    }
                    if ($ok) {
                        $stats['critical_ok']++;
                    }
                }
            }

            if (($turn['kind'] ?? '') === 'followup') {
                $stats['followup_n']++;
                $ok = true;
                if (is_array($expect)) {
                    foreach ($expect as $k => $v) {
                        $actual = match ($k) {
                            'origin' => $result->intent->origin,
                            'destination' => $result->intent->destination,
                            'depart_date' => $result->intent->departDate,
                            'airline' => $result->intent->airline,
                            'budget' => $result->intent->budget,
                            'max_stops' => $result->intent->maxStops,
                            'adults' => $result->intent->adults,
                            default => null,
                        };
                        if ($v !== $actual && ! (is_numeric($v) && (float) $v === (float) $actual)) {
                            $ok = false;
                        }
                    }
                }
                // Prior route preserved
                if (($turn['prior']['origin'] ?? null) && $result->intent->origin !== $turn['prior']['origin']) {
                    $ok = false;
                }
                if ($ok) {
                    $stats['followup_ok']++;
                }
            }
        }

        $pct = static fn (int $ok, int $n): float => $n === 0 ? 100.0 : round(100.0 * $ok / $n, 2);
        $intentAcc = $pct($stats['intent_ok'], $stats['intent_n']);
        $critAcc = $pct($stats['critical_ok'], $stats['critical_n']);
        $clarifyAcc = $pct($stats['clarify_ok'], $stats['clarify_n']);
        $followAcc = $pct($stats['followup_ok'], $stats['followup_n']);
        $langAcc = [];
        foreach ($stats['by_lang'] as $lang => $pair) {
            $langAcc[$lang] = $pct($pair['ok'], $pair['n']);
        }
        sort($stats['latencies']);
        $p = static function (array $xs, float $q): float {
            if ($xs === []) {
                return 0.0;
            }
            $idx = (int) max(0, min(count($xs) - 1, (int) floor($q * (count($xs) - 1))));

            return round($xs[$idx], 3);
        };

        $report = [
            'turns' => count($turns),
            'HYBRID_INTENT_ACCURACY' => $intentAcc,
            'HYBRID_CRITICAL_FIELD_ACCURACY' => $critAcc,
            'HYBRID_ENGLISH_ACCURACY' => $langAcc['en'] ?? 0,
            'HYBRID_ROMAN_URDU_ACCURACY' => $langAcc['ru'] ?? 0,
            'HYBRID_URDU_ACCURACY' => $langAcc['ur'] ?? 0,
            'HYBRID_MIXED_ACCURACY' => $langAcc['mixed'] ?? 0,
            'HYBRID_FOLLOWUP_ACCURACY' => $followAcc,
            'HYBRID_CLARIFICATION_ACCURACY' => $clarifyAcc,
            'ORIGIN_ACCURACY' => $pct($stats['fields']['origin'][0], $stats['fields']['origin'][1]),
            'DESTINATION_ACCURACY' => $pct($stats['fields']['destination'][0], $stats['fields']['destination'][1]),
            'DEPART_DATE_ACCURACY' => $pct($stats['fields']['depart_date'][0], $stats['fields']['depart_date'][1]),
            'PASSENGER_ACCURACY' => $pct($stats['fields']['adults'][0], $stats['fields']['adults'][1]),
            'AIRLINE_ACCURACY' => $pct($stats['fields']['airline'][0], $stats['fields']['airline'][1]),
            'BUDGET_ACCURACY' => $pct($stats['fields']['budget'][0], $stats['fields']['budget'][1]),
            'STOPS_ACCURACY' => $pct($stats['fields']['max_stops'][0], $stats['fields']['max_stops'][1]),
            'RANKING_ACCURACY' => $pct($stats['fields']['ranking'][0], $stats['fields']['ranking'][1]),
            'WRONG_CONFIDENT_SEARCHES' => $stats['wrong_confident'],
            'LLM_BYPASS_RATE' => $pct($stats['llm_bypass'], count($turns)),
            'TOTAL_LANGUAGE_PIPELINE_P50' => $p($stats['latencies'], 0.50),
            'TOTAL_LANGUAGE_PIPELINE_P95' => $p($stats['latencies'], 0.95),
        ];

        $evidenceDir = base_path('docs/evidence/jp-ai-assist-01f');
        if (! is_dir($evidenceDir)) {
            mkdir($evidenceDir, 0777, true);
        }
        file_put_contents($evidenceDir.'/quality-matrix.json', json_encode($report, JSON_PRETTY_PRINT));

        $this->assertSame(0, $stats['wrong_confident'], 'WRONG_CONFIDENT_SEARCHES must be 0');
        $this->assertGreaterThanOrEqual(98.0, $intentAcc);
        $this->assertGreaterThanOrEqual(99.0, $critAcc);
        $this->assertGreaterThanOrEqual(99.0, $langAcc['en'] ?? 0);
        $this->assertGreaterThanOrEqual(97.0, $langAcc['ru'] ?? 0);
        $this->assertGreaterThanOrEqual(95.0, $langAcc['ur'] ?? 0);
        $this->assertGreaterThanOrEqual(98.0, $followAcc);
        $this->assertGreaterThanOrEqual(99.0, $clarifyAcc);
        $this->assertSame(100.0, $pct($stats['llm_bypass'], count($turns)));
    }
}
