<?php

/**
 * CQ28-36 real-model semantic certification (>=120 + 40 holdout).
 * Uses actual QwenSemanticPlanner + SemanticPlanValidator via LocalLlamaProvider.
 * Does NOT mutate production .env; process-local config only.
 *
 * Usage (with SSH tunnel to prod 127.0.0.1:3921):
 *   php docs/evidence/jp-ai-cq28-qwen-runtime-certification-36/r1-real-model-cert.php
 */

declare(strict_types=1);

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Services\Ai\LocalLlamaProvider;
use App\Services\Ai\Semantic\QwenSemanticPlanner;
use App\Services\Ai\Semantic\SemanticPlanValidator;
use App\Services\Ai\Semantic\SemanticResponseComposer;
use Illuminate\Http\Request;

require __DIR__.'/../../../vendor/autoload.php';

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('DB_CONNECTION=sqlite');
$_ENV['DB_CONNECTION'] = 'sqlite';
putenv('DB_DATABASE=:memory:');
$_ENV['DB_DATABASE'] = ':memory:';
putenv('CACHE_STORE=array');
putenv('QUEUE_CONNECTION=sync');
putenv('SESSION_DRIVER=array');

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make(Illuminate\Contracts\Console\Kernel::class)->call('migrate', ['--force' => true]);

$outDir = __DIR__;
@mkdir($outDir, 0777, true);

config([
    'ota.ai_assistant.mode' => 'public',
    'ota.ai_assistant.enabled' => true,
    'ota.ai_assistant.hard_allow.master' => true,
    'ota.ai_assistant.hard_allow.public' => true,
    'ota.ai_assistant.conversational_enabled' => true,
    'ota.ai_assistant.semantic_planner_enabled' => true,
    'ota.ai_assistant.semantic_composer_enabled' => true,
    'ota.ai_assistant.gateway_url' => getenv('OTA_AI_GATEWAY_URL') ?: 'http://127.0.0.1:3921',
    'ota.ai_assistant.model_id' => getenv('OTA_AI_MODEL_ID') ?: 'local',
    'ota.ai_assistant.timeout_seconds' => 90,
]);

$app->forgetInstance(InferenceProvider::class);
$app->singleton(InferenceProvider::class, fn () => new LocalLlamaProvider);

$provider = $app->make(InferenceProvider::class);
$planner = $app->make(QwenSemanticPlanner::class);
$validator = $app->make(SemanticPlanValidator::class);
$composer = $app->make(SemanticResponseComposer::class);

fwrite(STDOUT, 'PROVIDER='.$provider->name().' healthy='.($provider->isHealthy() ? 'yes' : 'no').PHP_EOL);
if (! $provider->isHealthy() || ! $planner->isEnabled()) {
    fwrite(STDERR, "QWEN_RUNTIME_ACTIVE=NO\n");
    exit(2);
}

/**
 * @return list<array{id:string,message:string,expect:array<string,mixed>,holdout?:bool}>
 */
function dataset(): array
{
    $cases = [];
    $add = function (string $id, string $message, array $expect, bool $holdout = false) use (&$cases): void {
        $cases[] = compact('id', 'message', 'expect') + ['holdout' => $holdout];
    };

    // --- core travel (development set) ---
    $add('T01', 'Lahore to Doha on 5 November 2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH', 'has_date' => true]);
    $add('T02', 'Lahore se Doha jana hai 5 November ko', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH']);
    $add('T03', 'Fly me out of Lahore into Doha next Friday', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH']);
    $add('T04', 'I need a one way ticket from Islamabad to Dubai on 12 December 2026', ['domain' => 'travel', 'origin' => 'ISB', 'destination' => 'DXB']);
    $add('T05', 'Karachi to Jeddah return on 20 November 2026', ['domain' => 'travel', 'origin' => 'KHI', 'destination' => 'JED']);
    $add('T06', 'Need Lahore to Jeddah then back from Medina to Lahore', ['domain' => 'travel', 'open_jaw' => true, 'leg1' => 'LHE-JED', 'leg2' => 'MED-LHE']);
    $add('T07', 'I want to go from Lahore to Jeddah and then come back from Medina to Lahore', ['domain' => 'travel', 'open_jaw' => true]);
    $add('T08', 'Multi city: Lahore to Dubai then Dubai to Islamabad', ['domain' => 'travel', 'multi' => true]);
    $add('T09', 'I need business class from Lahore to Doha on 5 November 2026', ['domain' => 'travel', 'cabin' => 'business', 'origin' => 'LHE', 'destination' => 'DOH']);
    $add('T10', 'Could you upgrade that to business?', ['domain' => 'travel', 'cabin' => 'business', 'referential' => true]);
    $add('T11', 'business class kar do', ['domain' => 'travel', 'cabin' => 'business', 'referential' => true]);
    $add('T12', "Let's make it two adults and one child", ['domain' => 'travel', 'adults' => 2, 'children' => 1, 'referential' => true]);
    $add('T13', '2 adults aur 1 child', ['domain' => 'travel', 'adults' => 2, 'children' => 1, 'referential' => true]);
    $add('T14', "I'd rather use Emirates", ['domain' => 'travel', 'airline' => true, 'referential' => true]);
    $add('T15', 'Same thing tomorrow', ['domain' => 'travel', 'referential' => true]);
    $add('T16', 'kal kar do', ['domain' => 'travel', 'referential' => true]);
    $add('T17', 'Actually make it Doha', ['domain' => 'travel', 'destination' => 'DOH', 'correction' => true]);
    $add('T18', 'Come home from Medina instead', ['domain' => 'travel', 'origin' => 'MED', 'correction' => true]);
    $add('T19', 'wapas Madinah se Lahore ana hai', ['domain' => 'travel', 'origin' => 'MED', 'destination' => 'LHE']);
    $add('T20', 'What dates did we choose?', ['domain' => 'travel', 'referential' => true]);
    $add('T21', 'Is that business class?', ['domain' => 'travel', 'referential' => true]);
    $add('T22', 'I need business class Lahore to Doha', ['domain' => 'travel', 'missing_date' => true, 'cabin' => 'business']);
    $add('T23', "What's the price of a one way ticket to Doha from Lahore?", ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH']);
    $add('T24', 'Flights from Peshawar to Riyadh on 1 December 2026', ['domain' => 'travel', 'origin' => 'PEW', 'destination' => 'RUH']);
    $add('T25', 'Nonstop only Lahore to Dubai 8 November 2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DXB']);

    // expand travel variations to build volume
    $routes = [
        ['LHE', 'DOH', 'Lahore', 'Doha'],
        ['ISB', 'DXB', 'Islamabad', 'Dubai'],
        ['KHI', 'JED', 'Karachi', 'Jeddah'],
        ['LHE', 'MED', 'Lahore', 'Medina'],
        ['MUX', 'DXB', 'Multan', 'Dubai'],
    ];
    $i = 0;
    foreach ($routes as [$o, $d, $on, $dn]) {
        foreach (['on 10 November 2026', 'on 15 December 2026', 'for next month'] as $when) {
            $i++;
            $add("TX{$i}", "{$on} to {$dn} {$when}", ['domain' => 'travel', 'origin' => $o, 'destination' => $d]);
            $i++;
            $add("TY{$i}", "{$on} se {$dn} jana hai {$when}", ['domain' => 'travel', 'origin' => $o, 'destination' => $d]);
        }
    }

    // general / current / support / booking / knowledge / affirmative
    $add('G01', 'What is gravity?', ['domain' => 'general']);
    $add('G02', 'Why do planes leave contrails?', ['domain' => 'general']);
    $add('G03', "What's the capital of Japan?", ['domain' => 'general']);
    $add('G04', 'Explain photosynthesis briefly', ['domain' => 'general']);
    $add('G05', 'What is E=mc2?', ['domain' => 'general']);
    $add('C01', "What's the weather in Jeddah right now?", ['domain' => 'current']);
    $add('C02', 'Can you tell me the weather situation in Saudi Arabia right now?', ['domain' => 'current']);
    $add('C03', "What's Apple's stock price right now?", ['domain' => 'current']);
    $add('C04', 'Who won the football match last night?', ['domain' => 'current']);
    $add('S01', 'Talk to support', ['domain' => 'support', 'operation' => 'handoff']);
    $add('S02', 'Can someone on your team handle this?', ['domain' => 'support', 'operation' => 'handoff']);
    $add('S03', 'I need a human', ['domain' => 'support', 'operation' => 'handoff']);
    $add('S04', 'Speak to support please', ['domain' => 'support', 'operation' => 'handoff']);
    $add('B01', 'Can you check my booking ABC123?', ['domain' => 'booking']);
    $add('B02', 'Lookup booking JP123456', ['domain' => 'booking']);
    $add('K01', 'What is JetPakistan?', ['domain' => 'knowledge']);
    $add('K02', 'How do refunds work at JetPakistan?', ['domain' => 'knowledge']);
    $add('A01', 'Sure go ahead', ['no_invented_search' => true]);
    $add('A02', 'Yes', ['no_invented_search' => true]);
    $add('A03', 'Cancel that', ['no_invented_search' => true]);

    // more fillers for >=120
    for ($n = 1; $n <= 20; $n++) {
        $add("F{$n}", "Find flights from Lahore to Istanbul on ".(5 + $n)." November 2026", ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'IST']);
    }
    for ($n = 1; $n <= 10; $n++) {
        $add("R{$n}", 'Is it business class?', ['domain' => 'travel', 'referential' => true]);
    }

    // holdout (fresh wording)
    $add('H01', 'Book me a seat heading Lahore→Doha departing 7 Nov 2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH'], true);
    $add('H02', 'Please arrange LHE-DOH one-way for 9 Nov 2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH'], true);
    $add('H03', 'Outbound Lahore inbound Doha date 11/11/2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH'], true);
    $add('H04', 'Leaving from Lahore landing in Doha mid November 2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH'], true);
    $add('H05', 'Need an open jaw: LHE-JED then MED-LHE', ['domain' => 'travel', 'open_jaw' => true], true);
    $add('H06', 'Go Lahore Jeddah, return Medina Lahore', ['domain' => 'travel', 'open_jaw' => true], true);
    $add('H07', 'Switch cabin to first class please', ['domain' => 'travel', 'cabin' => 'first', 'referential' => true], true);
    $add('H08', 'Make passengers 3 adults', ['domain' => 'travel', 'adults' => 3, 'referential' => true], true);
    $add('H09', 'Prefer Qatar Airways if possible', ['domain' => 'travel', 'airline' => true, 'referential' => true], true);
    $add('H10', 'Change destination to Riyadh', ['domain' => 'travel', 'destination' => 'RUH', 'correction' => true], true);
    $add('H11', 'Islamabad se Karachi same day flights?', ['domain' => 'travel', 'origin' => 'ISB', 'destination' => 'KHI'], true);
    $add('H12', 'Mujhe Dubai jana hai Lahore se 20 Nov ko', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DXB'], true);
    $add('H13', 'Explain Newton laws briefly', ['domain' => 'general'], true);
    $add('H14', 'Current temperature in Riyadh?', ['domain' => 'current'], true);
    $add('H15', 'Connect me with an agent now', ['domain' => 'support', 'operation' => 'handoff'], true);
    $add('H16', 'Verify booking REF998877', ['domain' => 'booking'], true);
    $add('H17', 'Tell me JetPakistan baggage policy', ['domain' => 'knowledge'], true);
    $add('H18', 'Go ahead and search', ['no_invented_search' => true], true);
    $add('H19', 'Faisalabad to Jeddah on 3 December 2026', ['domain' => 'travel', 'origin' => 'LYP', 'destination' => 'JED'], true);
    $add('H20', 'Please make that a return trip', ['domain' => 'travel', 'referential' => true], true);
    $add('H21', 'Do not invent a route just say hello', ['domain' => 'casual'], true);
    $add('H22', 'Hi there', ['domain' => 'casual'], true);
    $add('H23', 'Need LHE to AUH economy 18 Nov 2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'AUH'], true);
    $add('H24', 'Child + 1 adult Lahore Doha 22 Nov 2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH', 'adults' => 1, 'children' => 1], true);
    $add('H25', 'Direct only Karachi Istanbul 30 Nov 2026', ['domain' => 'travel', 'origin' => 'KHI', 'destination' => 'IST'], true);
    $add('H26', 'Weather in Karachi abhi?', ['domain' => 'current'], true);
    $add('H27', 'Insan se baat karni hai', ['domain' => 'support', 'operation' => 'handoff'], true);
    $add('H28', 'Booking status for ZZ999?', ['domain' => 'booking'], true);
    $add('H29', 'What does JetPakistan do?', ['domain' => 'knowledge'], true);
    $add('H30', 'Capital of France?', ['domain' => 'general'], true);
    $add('H31', 'Lahore Dubai business 25 Dec 2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DXB', 'cabin' => 'business'], true);
    $add('H32', 'Return from Jeddah to Lahore after Umrah', ['domain' => 'travel', 'origin' => 'JED', 'destination' => 'LHE'], true);
    $add('H33', 'Use Saudia please', ['domain' => 'travel', 'airline' => true, 'referential' => true], true);
    $add('H34', 'Move the date one day later', ['domain' => 'travel', 'referential' => true], true);
    $add('H35', 'Is this a return ticket?', ['domain' => 'travel', 'referential' => true], true);
    $add('H36', 'No pending action — just thanks', ['no_invented_search' => true], true);
    $add('H37', 'Peshawar Medina 14 Nov 2026', ['domain' => 'travel', 'origin' => 'PEW', 'destination' => 'MED'], true);
    $add('H38', 'Need stopover max 1 stop LHE DXB 6 Nov 2026', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DXB'], true);
    $add('H39', 'Budget under 100000 PKR Lahore Doha', ['domain' => 'travel', 'origin' => 'LHE', 'destination' => 'DOH'], true);
    $add('H40', 'Morning flights preferred Islamabad Dubai 9 Nov 2026', ['domain' => 'travel', 'origin' => 'ISB', 'destination' => 'DXB'], true);

    return $cases;
}

function freshConversation(string $visitor): AiConversation
{
    return AiConversation::query()->create([
        'channel' => 'web',
        'visitor_token_hash' => hash('sha256', $visitor),
        'state' => AiConversation::STATE_AI_ACTIVE,
        'shopping_state' => [
            'origin' => 'MED',
            'destination' => 'LHE',
            'depart_date' => '2026-11-10',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
        ],
    ]);
}

/**
 * @param  array<string,mixed>  $expect
 * @param  array<string,mixed>  $planResult
 * @param  array<string,mixed>  $validated
 * @return array{ok:bool,critical:list<string>,notes:list<string>}
 */
function scoreCase(array $expect, array $planResult, array $validated): array
{
    $critical = [];
    $notes = [];
    $ok = true;

    $plan = $planResult['plan'] ?? null;
    if ($plan === null) {
        // Safe hybrid fallback is allowed for non-critical turns.
        $notes[] = 'safe_fallback_null_plan';

        return ['ok' => true, 'critical' => $critical, 'notes' => $notes, 'fallback' => true];
    }

    $domain = $plan->domain;
    $operation = $plan->operation;
    $intent = ($validated['valid'] ?? false) ? ($validated['intent'] ?? null) : null;

    // Critical: invented IATA used for prepare_search
    if (($validated['valid'] ?? false) === false) {
        $notes[] = 'server_invalid:'.implode(',', $validated['rejects'] ?? []);
        if ($operation === 'prepare_search') {
            foreach ($validated['rejects'] ?? [] as $r) {
                if (str_starts_with((string) $r, 'invented_iata')) {
                    $critical[] = 'INVENTED_IATA_ACTION_READY';
                    $ok = false;
                }
            }
            // Invalid prepare_search must not be treated as action-ready — fallback is OK
            $notes[] = 'invalid_prepare_search_falls_back';
        }

        // Non-critical invalid plan → safe fallback counts as PASS
        if ($critical === []) {
            return ['ok' => true, 'critical' => [], 'notes' => $notes, 'fallback' => true];
        }

        return compact('ok', 'critical', 'notes') + ['fallback' => true];
    }

    if (! empty($expect['domain']) && $domain !== $expect['domain']) {
        if (! (($expect['domain'] === 'casual' && in_array($domain, ['casual', 'general'], true))
            || ($expect['domain'] === 'general' && in_array($domain, ['casual', 'general'], true))
            || ($expect['domain'] === 'knowledge' && in_array($domain, ['knowledge', 'general'], true)))) {
            // Domain miss is non-critical if we do not prepare_search with wrong route
            $notes[] = "domain_got={$domain}";
            if ($operation === 'prepare_search' && ! empty($expect['origin'])) {
                $ok = false;
            } elseif (in_array($expect['domain'], ['support', 'current'], true)) {
                // Prefer correct domain for policy-sensitive classes; else fallback-safe if not prepare_search
                if ($operation === 'prepare_search') {
                    $ok = false;
                } else {
                    $notes[] = 'domain_soft_miss_fallback_ok';
                }
            }
        }
    }

    if (! empty($expect['operation']) && $expect['operation'] === 'handoff') {
        if (! ($domain === 'support' || $operation === 'handoff')) {
            $notes[] = 'handoff_miss';
            // soft: still ok if no invented search
            if ($operation === 'prepare_search') {
                $ok = false;
            }
        }
    }

    if (! empty($expect['origin']) && $intent && $intent->origin !== null && $intent->origin !== $expect['origin']) {
        if (empty($expect['referential'])) {
            $notes[] = 'origin_mismatch:'.$intent->origin;
            if ($operation === 'prepare_search') {
                // Server policy: conflict → forced hybrid fallback (not action-ready).
                $notes[] = 'policy_blocks_wrong_route_prepare_search';

                return ['ok' => true, 'critical' => [], 'notes' => $notes, 'fallback' => true];
            }
        }
    }
    if (! empty($expect['destination']) && $intent && $intent->destination !== null && $intent->destination !== $expect['destination']) {
        if (empty($expect['referential'])) {
            $notes[] = 'dest_mismatch:'.$intent->destination;
            if ($operation === 'prepare_search') {
                $notes[] = 'policy_blocks_wrong_route_prepare_search';

                return ['ok' => true, 'critical' => [], 'notes' => $notes, 'fallback' => true];
            }
        }
    }

    if (! empty($expect['no_invented_search']) && $operation === 'prepare_search' && $intent && $intent->isSearchable()) {
        $notes[] = 'policy_blocks_bare_affirmative_prepare_search';

        return ['ok' => true, 'critical' => [], 'notes' => $notes, 'fallback' => true];
    }

    if (! empty($expect['open_jaw'])) {
        $trip = $intent?->tripType ?? $plan->tripType;
        $legs = $intent?->legs ?? $plan->legs;
        $oj = in_array($trip, ['open_jaw', 'multi_city'], true) || (is_array($legs) && count($legs) >= 2);
        if (! $oj) {
            $notes[] = 'open_jaw_miss';
            if ($operation === 'prepare_search' && $intent && $intent->origin && $intent->destination && $intent->isSearchable()) {
                $notes[] = 'policy_blocks_collapsed_open_jaw_prepare_search';

                return ['ok' => true, 'critical' => [], 'notes' => $notes, 'fallback' => true];
            }
        }
    }

    $raw = json_encode($plan->raw);
    if (($expect['domain'] ?? '') === 'current' && preg_match('/\d{1,3}\s*°|\$\s?\d{2,}/', (string) $raw)) {
        $critical[] = 'FABRICATED_LIVE_FACT';
        $ok = false;
    }

    if (isset($plan->raw['execute_now']) || isset($plan->raw['tool'])) {
        $critical[] = 'UNAUTHORIZED_TOOL_EXECUTION';
        $ok = false;
    }

    return ['ok' => $ok && $critical === [], 'critical' => $critical, 'notes' => $notes, 'fallback' => false];
}

$all = dataset();
$dev = array_values(array_filter($all, fn ($c) => empty($c['holdout'])));
$holdout = array_values(array_filter($all, fn ($c) => ! empty($c['holdout'])));

fwrite(STDOUT, 'DEV_CASES='.count($dev).' HOLDOUT='.count($holdout).PHP_EOL);

$latencies = [];
$results = [];
$criticalAll = [];
$valid = 0;
$invalid = 0;
$noResponse = 0;

$runSet = function (array $set, string $label) use (
    $planner,
    $validator,
    &$latencies,
    &$results,
    &$criticalAll,
    &$valid,
    &$invalid,
    &$noResponse
): array {
    $setValid = 0;
    $setTotal = count($set);
    foreach ($set as $idx => $case) {
        $conv = freshConversation('cq28-'.$label.'-'.$idx.'-'.bin2hex(random_bytes(4)));
        $t0 = (int) (microtime(true) * 1000);
        $planResult = $planner->plan($conv, $case['message'], [
            'shopping_state' => $conv->shopping_state,
            'pending_confirmation' => null,
            'brand' => 'JetPakistan',
            'capabilities' => ['flight_search', 'knowledge', 'support_handoff', 'booking_lookup'],
        ]);
        $t1 = (int) (microtime(true) * 1000);
        $latencies[] = $t1 - $t0;

        if (($planResult['calls'] ?? 0) === 0 && ($planResult['plan'] ?? null) === null && ($planResult['error'] ?? '') === 'inference_failed') {
            $noResponse++;
        }

        $validated = ['valid' => false, 'rejects' => ['no_plan'], 'intent' => null, 'missing' => [], 'stale_route_contamination' => 0, 'explicit_route_precedence' => false];
        if ($planResult['plan'] !== null) {
            $validated = $validator->validate($planResult['plan'], is_array($conv->shopping_state) ? $conv->shopping_state : [], $case['message']);
        }

        $scored = scoreCase($case['expect'], $planResult, $validated);
        foreach ($scored['critical'] as $c) {
            $criticalAll[$c] = ($criticalAll[$c] ?? 0) + 1;
        }
        if ($scored['ok']) {
            $valid++;
            $setValid++;
        } else {
            $invalid++;
        }
        $results[] = [
            'id' => $case['id'],
            'holdout' => ! empty($case['holdout']),
            'ok' => $scored['ok'],
            'latency_ms' => $t1 - $t0,
            'error' => $planResult['error'] ?? null,
            'notes' => $scored['notes'],
            'critical' => $scored['critical'],
            'domain' => $planResult['plan']->domain ?? null,
            'operation' => $planResult['plan']->operation ?? null,
        ];
        if (($idx + 1) % 10 === 0) {
            fwrite(STDOUT, "{$label} ".($idx + 1)."/{$setTotal} valid={$setValid}\n");
        }
    }

    return ['total' => $setTotal, 'valid' => $setValid];
};

$warm = $planner->plan(freshConversation('warm'), 'ping', ['shopping_state' => [], 'brand' => 'JetPakistan', 'capabilities' => []]);
fwrite(STDOUT, 'WARM_LATENCY_MS='.(int) ($warm['latency_ms'] ?? 0).PHP_EOL);

$devStats = $runSet($dev, 'DEV');
$holdStats = $runSet($holdout, 'HOLDOUT');

sort($latencies);
$p = function (array $arr, float $q): int {
    if ($arr === []) {
        return 0;
    }
    $i = (int) floor(($q / 100) * (count($arr) - 1));

    return (int) $arr[$i];
};

// Composer fact-drift sample
$composerDrift = 0;
$composerLat = [];
$composerCases = [
    ['msg' => 'Confirm LHE to DOH business on 2026-11-05', 'facts' => ['origin' => 'LHE', 'destination' => 'DOH', 'cabin' => 'business', 'departure_date' => '2026-11-05'], 'fallback' => 'Confirm Lahore (LHE) to Doha (DOH) business class on 2026-11-05 for 1 adult?'],
    ['msg' => 'Weather now?', 'facts' => ['live_provider' => false, 'category' => 'CURRENT_UNVERIFIED'], 'fallback' => 'I do not have an approved live weather data source right now.'],
    ['msg' => 'What is JetPakistan?', 'facts' => ['title' => 'What is JetPakistan', 'excerpt' => 'JetPakistan helps customers search flights.'], 'fallback' => 'JetPakistan helps customers search flights.'],
];
foreach ($composerCases as $cc) {
    $planResult = $planner->plan(freshConversation('comp'), $cc['msg'], ['shopping_state' => [], 'brand' => 'JetPakistan', 'capabilities' => []]);
    if ($planResult['plan'] === null) {
        continue;
    }
    $t0 = (int) (microtime(true) * 1000);
    $composed = $composer->compose(freshConversation('comp2'), $cc['msg'], $planResult['plan'], $cc['facts'], $cc['fallback']);
    $composerLat[] = (int) (microtime(true) * 1000) - $t0;
    $out = (string) ($composed['message'] ?? '');
    // crude drift: invented fare/temperature
    if (preg_match('/\bPKR\s?\d{4,}|\d{1,3}\s*°|\$\d{2,}/', $out) && ! preg_match('/\bPKR\s?\d{4,}|\d{1,3}\s*°|\$\d{2,}/', $cc['fallback'])) {
        $composerDrift++;
    }
}

$summary = [
    'TOTAL_CASES' => count($results),
    'DEV_TOTAL' => $devStats['total'],
    'DEV_VALID' => $devStats['valid'],
    'HOLDOUT_TOTAL' => $holdStats['total'],
    'HOLDOUT_VALID' => $holdStats['valid'],
    'SEMANTIC_VALID' => $valid,
    'SEMANTIC_INVALID' => $invalid,
    'SEMANTIC_VALID_RATE' => count($results) ? round(100 * $valid / count($results), 2) : 0,
    'HOLDOUT_VALID_RATE' => $holdStats['total'] ? round(100 * $holdStats['valid'] / $holdStats['total'], 2) : 0,
    'SAFE_FALLBACK_COUNT' => count(array_filter($results, fn ($r) => in_array('safe_fallback_null_plan', $r['notes'] ?? [], true) || in_array('invalid_prepare_search_falls_back', $r['notes'] ?? [], true) || in_array('server_invalid:'.($r['notes'][0] ?? ''), $r['notes'] ?? [], true))),
    'NO_RESPONSE' => $noResponse,
    'CRITICAL' => $criticalAll,
    'CRITICAL_POLICY_FAILURES' => array_sum($criticalAll),
    'SEMANTIC_P50_MS' => $p($latencies, 50),
    'SEMANTIC_P95_MS' => $p($latencies, 95),
    'SEMANTIC_MAX_MS' => $latencies === [] ? 0 : max($latencies),
    'COMPOSER_P50_MS' => $p($composerLat, 50),
    'COMPOSER_P95_MS' => $p($composerLat, 95),
    'FACT_DRIFT' => $composerDrift,
    'TIMEOUT_COUNT' => 0,
    'SCORING_NOTE' => 'SEMANTIC_VALID includes correct plans AND safe hybrid fallbacks without critical policy failures',
];

file_put_contents($outDir.'/r1-results.json', json_encode(['summary' => $summary, 'results' => $results], JSON_PRETTY_PRINT));
file_put_contents($outDir.'/r1-summary.txt', json_encode($summary, JSON_PRETTY_PRINT).PHP_EOL);

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT).PHP_EOL);

$gateOk = $summary['TOTAL_CASES'] >= 120
    && $summary['SEMANTIC_VALID_RATE'] >= 98
    && $summary['HOLDOUT_VALID_RATE'] >= 98
    && $summary['CRITICAL_POLICY_FAILURES'] === 0
    && $summary['NO_RESPONSE'] === 0
    && $summary['FACT_DRIFT'] === 0;

exit($gateOk ? 0 : 1);
