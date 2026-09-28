<?php
// Local-only recompute helper (also runnable on server).
$path = $argv[1] ?? __DIR__.'/../../jp-ai-cq43-final-resoak/out/sessions/01-primary-long-session.json';
$j = json_decode(file_get_contents($path), true);
$turns = $j['turns'] ?? [];
$user = 0;
$resume = 0;
$qwen = 0;
$sem = [];
$tot = [];
$fallbacks = 0;
foreach ($turns as $t) {
    if (! empty($t['resume_ai'])) {
        $resume++;
        continue;
    }
    $user++;
    $m = is_array($t['meta'] ?? null) ? $t['meta'] : [];
    $c = (int) ($m['MODEL_CALLS'] ?? $m['GENERAL_MODEL_CALLS'] ?? 0);
    $qwen += $c;
    $tot[] = (int) round((float) ($t['latency_ms'] ?? 0));
    if (($m['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES') {
        $sem[] = (int) ($m['SEMANTIC_LATENCY_MS'] ?? 0);
    }
    if (($m['SEMANTIC_BRAIN_FALLBACK'] ?? '') === 'YES'
        && ($m['SEMANTIC_FALLBACK_REASON'] ?? '') !== 'deterministic_authority_complete'
    ) {
        $fallbacks++;
    }
}
function nearest_rank(array $a, float $p): ?int
{
    if ($a === []) {
        return null;
    }
    sort($a);
    $n = count($a);
    $rank = (int) ceil(($p / 100) * $n);
    $rank = max(1, min($n, $rank));

    return $a[$rank - 1];
}
echo json_encode([
    'path' => $path,
    'PERCENTILE_METHOD' => 'nearest-rank: rank=ceil(p/100*N), 1-indexed value a[rank-1]',
    'SESSION_RECORDS' => count($turns),
    'USER_MESSAGES' => $user,
    'RESUME_ACTIONS' => $resume,
    'QWEN_MODEL_CALLS' => $qwen,
    'TOTAL_P50' => nearest_rank($tot, 50),
    'TOTAL_P95' => nearest_rank($tot, 95),
    'TOTAL_MAX' => $tot === [] ? null : max($tot),
    'SEMANTIC_SAMPLE_N' => count($sem),
    'SEMANTIC_VALUES_MS' => $sem,
    'SEMANTIC_P50' => nearest_rank($sem, 50),
    'SEMANTIC_P95' => nearest_rank($sem, 95),
    'SEMANTIC_MAX' => $sem === [] ? null : max($sem),
    'GT_20S' => count(array_filter($tot, fn ($x) => $x > 20000)),
    'GT_30S' => count(array_filter($tot, fn ($x) => $x > 30000)),
    'FIRST29_QWEN' => array_sum(array_map(function ($t) {
        if (! empty($t['resume_ai'])) {
            return 0;
        }
        $m = is_array($t['meta'] ?? null) ? $t['meta'] : [];

        return (int) ($m['MODEL_CALLS'] ?? $m['GENERAL_MODEL_CALLS'] ?? 0);
    }, array_slice(array_values(array_filter($turns, fn ($t) => empty($t['resume_ai']))), 0, 29))),
    'FALLBACKS' => $fallbacks,
], JSON_PRETTY_PRINT)."\n";
