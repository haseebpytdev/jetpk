<?php
$j = json_decode(file_get_contents(__DIR__.'/../out/sessions/05-real-qwen-session-run2.json'), true);
$turns = $j['turns'] ?? [];
$q = [];
foreach ($turns as $t) {
    if (! empty($t['resume_ai'])) {
        continue;
    }
    if ((int) ($t['MODEL_CALLS'] ?? 0) > 0) {
        $msg = (string) ($t['user_message'] ?? '');
        $essential = preg_match('/gravity|photosynthesis|I need Dubai|Dubai jana|Check my booking|Talk to support|Jeddah then Medina|via |through /i', $msg) === 1
            || (($t['SEMANTIC_BRAIN_CALLED'] ?? '') === 'YES' && ! in_array('explicit_route_complete', $t['DETERMINISTIC_AUTHORITY_CLASSES'] ?? [], true)
                && ($t['FINAL_RESPONSE_SOURCE'] ?? $t['mode'] ?? '') !== 'DETERMINISTIC_CURRENT_UNVERIFIED');
        // Classify remaining calls manually below defaults
        $class = 'QWEN_ESSENTIAL';
        if (preg_match('/Bitcoin|weather right now|Islamabad to Dubai next Monday|Lahore to Dubai next Monday/i', $msg)) {
            $class = 'UNEXPECTED_SHOULD_BE_DETERMINISTIC';
        }
        $q[] = [
            'USER_MESSAGE' => $msg,
            'INTENT_CLASS' => $t['meta']['INTENT_CLASS'] ?? $t['meta']['intent_class'] ?? ($t['intent']['mode'] ?? null),
            'FINAL_RESPONSE_SOURCE' => $t['meta']['FINAL_RESPONSE_SOURCE'] ?? $t['mode'] ?? null,
            'SEMANTIC_LATENCY_MS' => $t['SEMANTIC_LATENCY_MS'] ?? 0,
            'TOTAL_MS' => $t['latency_ms'] ?? null,
            'VALID' => (($t['SEMANTIC_BRAIN_FALLBACK'] ?? '') === 'YES') ? 'INVALID' : 'VALID',
            'FALLBACK_REASON' => $t['SEMANTIC_FALLBACK_REASON'] ?? null,
            'SEMANTIC_BRAIN_CALLED' => $t['SEMANTIC_BRAIN_CALLED'] ?? null,
            'MODEL_CALLS' => $t['MODEL_CALLS'] ?? 0,
            'CLASSIFICATION' => $class,
        ];
    }
}
file_put_contents(__DIR__.'/../out/08-qwen-call-audit.json', json_encode([
    'PERF02_QWEN_MODEL_CALLS' => count($q),
    'calls' => $q,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['count' => count($q), 'calls' => $q], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
