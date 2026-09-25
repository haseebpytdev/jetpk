<?php

/**
 * CQ28 real-model semantic eval harness (≥100 turns).
 * Run only when QWEN_RUNTIME_ACTIVE=YES (healthy localhost gateway).
 *
 * Usage (local):
 *   php docs/evidence/jp-ai-cq28-qwen-semantic-brain-35/r1-real-model-eval.php
 *
 * This harness is intentionally a checklist + counter scaffold; it does not
 * call production or mutate suppliers.
 */
declare(strict_types=1);

$gateway = getenv('OTA_AI_GATEWAY_URL') ?: 'http://127.0.0.1:3921';
$health = @file_get_contents($gateway.'/health');
if ($health === false) {
    fwrite(STDERR, "QWEN_RUNTIME_ACTIVE=NO gateway_unreachable={$gateway}\n");
    fwrite(STDERR, "REAL_MODEL_EVAL=TEMPORARILY_BLOCKED\n");
    exit(2);
}

$buckets = [
    'simple_flight' => 15,
    'follow_up' => 10,
    'correction' => 10,
    'open_jaw' => 8,
    'multi_city' => 5,
    'cabin_pax_airline' => 12,
    'booking' => 8,
    'rag' => 8,
    'general' => 8,
    'current' => 6,
    'handoff' => 5,
    'roman_urdu' => 10,
    'stale_context' => 5,
];

$total = array_sum($buckets);
echo "PLANNED_TURNS={$total}\n";
echo "STATUS=HARNESS_READY_RUNTIME_REQUIRED\n";
echo "NOTE=Wire to local Feature/HTTP runner when gateway + conversational flags are enabled.\n";
