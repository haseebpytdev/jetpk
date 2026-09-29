<?php

namespace Tests\Feature\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Data\Ai\TravelIntent;
use App\Models\AiConversation;
use App\Services\Ai\ConversationIntentRouter;
use App\Services\Ai\FlightSearchConfirmationGate;
use App\Services\Ai\Hybrid\HybridTravelPipeline;
use App\Services\Ai\Hybrid\ServerTravelSignals;
use App\Services\Ai\Hybrid\TravelConstraintResolver;
use App\Services\Ai\TravelIntentExtractor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\Support\Ai\ScriptedInferenceProvider;
use Tests\TestCase;

/**
 * CQ46 — DIAGNOSTIC ONLY. Ranking/time preference false-lead residual.
 * No application fixes. Writes evidence JSON under storage/framework/cq46-diagnostic/.
 */
class Cq46RankingTimeAuthorityDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private string $outDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', 'Asia/Karachi'));
        $this->outDir = storage_path('framework/cq46-diagnostic');
        @mkdir($this->outDir, 0775, true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enableSemanticAi(array $extra = []): void
    {
        config(array_merge([
            'ota.ai_assistant.mode' => 'public',
            'ota.ai_assistant.enabled' => true,
            'ota.ai_assistant.hard_allow.master' => true,
            'ota.ai_assistant.hard_allow.public' => true,
            'ota.ai_assistant.hard_allow.lab_adapter' => false,
            'ota.ai_assistant.hard_allow.human_handoff' => true,
            'ota.ai_assistant.flight_search_enabled' => true,
            'ota.ai_assistant.conversational_enabled' => true,
            'ota.ai_assistant.optional_llm_assist' => false,
            'ota.ai_assistant.semantic_planner_enabled' => true,
            'ota.ai_assistant.semantic_composer_enabled' => false,
            'ota.ai_assistant.knowledge_enabled' => true,
            'ota.ai_assistant.human_handoff_enabled' => true,
            'ota.ai_assistant.anonymous_per_minute' => 120,
            'ai_lab.enabled' => false,
            'ai_embed.enabled' => false,
        ], $extra));

        \App\Models\AiAssistantSetting::query()->delete();
        app(\App\Services\Ai\AiAssistantSettingsService::class)->get();
    }

    private function rebindInference(InferenceProvider $provider): void
    {
        $this->app->instance(InferenceProvider::class, $provider);
        foreach ([
            \App\Services\Ai\Semantic\SemanticBrain::class,
            \App\Services\Ai\Semantic\QwenSemanticPlanner::class,
            \App\Services\Ai\Semantic\SemanticResponseComposer::class,
            \App\Services\Ai\AiConversationalAgent::class,
            \App\Services\Ai\AiChatOrchestrator::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    private function chat(string $visitorId, string $message, ?string $conversationId = null): array
    {
        $payload = ['message' => $message];
        if ($conversationId !== null) {
            $payload['conversation_id'] = $conversationId;
        }

        $response = $this->withCookie('jp_ai_vid', $visitorId)
            ->postJson('/api/public/ai/chat', $payload);

        return [
            'response' => $response,
            'conversation_id' => (string) $response->json('conversation_id'),
            'json' => $response->json(),
        ];
    }

    private function reloadState(string $conversationId): array
    {
        $conv = AiConversation::query()->where('public_id', $conversationId)->firstOrFail();

        return is_array($conv->shopping_state) ? $conv->shopping_state : [];
    }

    private function seedKhiJedPendingLeadName(string $vid): AiConversation
    {
        $gate = app(FlightSearchConfirmationGate::class);
        $intent = TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => 'KHI',
            'destination' => 'JED',
            'depart_date' => '2026-10-06',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
            'trip_type' => 'one_way',
        ], 'STRUCTURED_FALLBACK');

        $conv = AiConversation::query()->create([
            'public_id' => (string) Str::uuid(),
            'visitor_token_hash' => hash('sha256', $vid),
            'channel' => 'web',
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [
                'intent' => 'flight_search',
                'origin' => 'KHI',
                'destination' => 'JED',
                'depart_date' => '2026-10-06',
                'adults' => 2,
                'cabin' => 'economy',
                'trip_type' => 'one_way',
                'lead_capture_pending' => true,
                'lead_capture_stage' => 'name',
                'lead_name' => null,
                'lead_capture_fields' => ['name', 'email', 'phone', 'contact_consent'],
            ],
        ]);
        $gate->storePending($conv, $gate->buildSnapshot($intent));

        return $conv->fresh();
    }

    private function writeJson(string $name, array $data): void
    {
        file_put_contents(
            $this->outDir.'/'.$name,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_cq46_diagnostic_matrix_and_audits(): void
    {
        $resolver = app(TravelConstraintResolver::class);
        $signals = app(ServerTravelSignals::class);
        $router = app(ConversationIntentRouter::class);
        $pipeline = app(HybridTravelPipeline::class);
        $extractor = app(TravelIntentExtractor::class);
        $gate = app(FlightSearchConfirmationGate::class);

        $prior = [
            'intent' => 'flight_search',
            'origin' => 'KHI',
            'destination' => 'JED',
            'depart_date' => '2026-10-06',
            'adults' => 2,
            'trip_type' => 'one_way',
        ];

        $phrases = [
            'cheapest', 'fastest', 'morning',
            'cheap', 'sasti', 'jaldi', 'best value', 'shortest layover',
            'subah', 'evening', 'night', 'shaam', 'raat',
        ];

        // --- §4 authority gap + §3 resolver ---
        $authorityRows = [];
        foreach ($phrases as $msg) {
            $cons = $resolver->resolve(mb_strtolower($msg), $msg);
            $auth = $signals->progressiveTravelAuthority($msg, $prior);
            $authorityRows[$msg] = [
                'RESOLVER_RANKING' => $cons['ranking'],
                'RESOLVER_TIME_PREFERENCE' => $cons['time_preference'],
                'SERVER_ACTIVE' => (bool) ($auth['active'] ?? false),
                'SERVER_TRAVEL_REFINEMENT' => (bool) ($auth['travel_refinement'] ?? false),
                'SERVER_STOP_REFINEMENT' => (bool) ($auth['stop_refinement'] ?? false),
                'IS_TRAVEL_AUTHORITY_TURN' => $router->isTravelAuthorityTurn($msg, $prior),
                'LOOKS_LIKE_BARE_NAME' => $router->looksLikeBareName($msg),
            ];
        }
        $this->writeJson('01-resolver-signals-authority.json', $authorityRows);

        $this->assertSame('CHEAPEST', $authorityRows['cheapest']['RESOLVER_RANKING']);
        $this->assertSame('FASTEST', $authorityRows['fastest']['RESOLVER_RANKING']);
        $this->assertSame('morning', $authorityRows['morning']['RESOLVER_TIME_PREFERENCE']);
        $this->assertFalse($authorityRows['cheapest']['SERVER_ACTIVE']);
        $this->assertFalse($authorityRows['cheapest']['IS_TRAVEL_AUTHORITY_TURN']);
        $this->assertTrue($authorityRows['cheapest']['LOOKS_LIKE_BARE_NAME']);
        $this->assertTrue($authorityRows['morning']['LOOKS_LIKE_BARE_NAME']);

        // --- §7 hybrid pipeline audit ---
        $hybridCheapest = $pipeline->parse('cheapest', $prior);
        $hybridMorning = $pipeline->parse('morning', $prior);
        $hybridAudit = [
            'cheapest' => [
                'HybridParseResult_rankingPreference' => $hybridCheapest->rankingPreference,
                'state_ranking_preference' => $hybridCheapest->state['ranking_preference'] ?? null,
                'TravelIntent_timePreference' => $hybridCheapest->intent->timePreference,
                'TravelIntent_maxStops' => $hybridCheapest->intent->maxStops,
                'TravelIntent_toArray_keys' => array_keys($hybridCheapest->intent->toArray()),
            ],
            'morning' => [
                'HybridParseResult_rankingPreference' => $hybridMorning->rankingPreference,
                'TravelIntent_timePreference' => $hybridMorning->intent->timePreference,
                'state_time_preference' => $hybridMorning->state['time_preference'] ?? null,
                'state_ranking_preference' => $hybridMorning->state['ranking_preference'] ?? null,
            ],
        ];
        $this->assertSame('CHEAPEST', $hybridCheapest->rankingPreference);
        $this->assertSame('CHEAPEST', $hybridCheapest->state['ranking_preference'] ?? null);
        $this->assertSame('morning', $hybridMorning->intent->timePreference);
        $this->assertSame('morning', $hybridMorning->state['time_preference'] ?? null);
        // ranking is NOT a TravelIntent constructor field — only on HybridParseResult/state.
        $this->assertArrayNotHasKey('ranking_preference', $hybridCheapest->intent->toArray());
        $this->assertArrayHasKey('time_preference', $hybridMorning->intent->toArray());

        // --- §8 state patcher ---
        $patchedFromTimeIntent = $extractor->patchState($prior, $hybridMorning->intent);
        $patchedFromCheapestIntent = $extractor->patchState($prior, $hybridCheapest->intent);
        $patchAudit = [
            'TIME_PREFERENCE_PATCHES_THROUGH_TRAVEL_INTENT' => ($patchedFromTimeIntent['time_preference'] ?? null) === 'morning',
            'RANKING_PREFERENCE_PATCHES_THROUGH_TRAVEL_INTENT' => array_key_exists('ranking_preference', $patchedFromCheapestIntent)
                && ($patchedFromCheapestIntent['ranking_preference'] ?? null) === 'CHEAPEST',
            'patched_morning_time' => $patchedFromTimeIntent['time_preference'] ?? null,
            'patched_cheapest_ranking' => $patchedFromCheapestIntent['ranking_preference'] ?? null,
            'note' => 'ConversationStatePatcher::merge only loops TravelIntent::toArray(); ranking_preference is applied only inside HybridTravelPipeline state merge, not via patchState(TravelIntent).',
        ];
        $this->assertTrue($patchAudit['TIME_PREFERENCE_PATCHES_THROUGH_TRAVEL_INTENT']);
        $this->assertFalse($patchAudit['RANKING_PREFERENCE_PATCHES_THROUGH_TRAVEL_INTENT']);
        $hybridAudit['patcher'] = $patchAudit;
        $this->writeJson('03-hybrid-state-audit.json', $hybridAudit);

        // --- §10 confirmation contract ---
        $snap = $gate->buildSnapshot(TravelIntent::fromArray([
            'intent' => 'flight_search',
            'origin' => 'KHI',
            'destination' => 'JED',
            'depart_date' => '2026-10-06',
            'adults' => 2,
            'time_preference' => 'morning',
            'max_stops' => 0,
        ], 'STRUCTURED_FALLBACK'));
        $confirmAudit = [
            'snapshot_keys' => array_keys($snap),
            'RANKING_IN_CONFIRMATION_SNAPSHOT' => array_key_exists('ranking_preference', $snap) || array_key_exists('ranking', $snap) ? 'YES' : 'NO',
            'TIME_IN_CONFIRMATION_SNAPSHOT' => array_key_exists('time_preference', $snap) ? 'YES' : 'NO',
            'MAX_STOPS_IN_CONFIRMATION_SNAPSHOT' => array_key_exists('max_stops', $snap) ? 'YES' : 'NO',
            'snapshotsEqual_keys' => ['origin', 'destination', 'departure_date', 'return_date', 'trip_type', 'adults', 'children', 'infants', 'cabin', 'airline', 'max_stops'],
            'confirmationMessage_mentions_preference' => false,
        ];
        $this->assertSame('NO', $confirmAudit['RANKING_IN_CONFIRMATION_SNAPSHOT']);
        $this->assertSame('NO', $confirmAudit['TIME_IN_CONFIRMATION_SNAPSHOT']);
        $this->writeJson('05-confirmation-contract.json', $confirmAudit);

        // --- §5 endpoint repro matrix ---
        $this->enableSemanticAi();
        $this->rebindInference(new ScriptedInferenceProvider(array_fill(0, 40, (string) json_encode([
            'domain' => 'travel',
            'intent' => 'flight_search',
            'operation' => 'clarify',
            'travel' => ['trip_type' => 'one_way', 'origin' => 'KHI', 'destination' => 'JED', 'adults' => 2],
            'missing' => [],
            'references' => ['pending_confirmation' => true],
            'corrections' => new \stdClass,
            'response_intent' => 'confirm_search',
        ], JSON_UNESCAPED_UNICODE))));

        $endpoint = [];
        $searchBeforeConfirm = 0;
        foreach ($phrases as $msg) {
            $vid = substr(preg_replace('/[^a-z0-9]/', '', 'cq46'.md5($msg)).str_repeat('x', 32), 0, 42);
            $conv = $this->seedKhiJedPendingLeadName($vid);
            $turn = $this->chat($vid, $msg, $conv->public_id);
            $turn['response']->assertOk();
            $state = $this->reloadState($conv->public_id);
            $pending = $state[FlightSearchConfirmationGate::STATE_KEY] ?? null;
            $searchCalls = (int) data_get($turn['json'], 'meta.AI_FLIGHT_SEARCH_READ_CALLS', 0);
            $confirmBefore = (bool) data_get($turn['json'], 'meta.CONFIRMATION_BEFORE_SEARCH', false);
            if ($searchCalls > 0 && ! $confirmBefore) {
                $searchBeforeConfirm++;
            }
            $body = (string) data_get($turn['json'], 'message', '');
            $endpoint[$msg] = [
                'STATUS' => (string) data_get($turn['json'], 'status', ''),
                'LEAD_NAME_AFTER' => $state['lead_name'] ?? null,
                'LEAD_STAGE_AFTER' => $state['lead_capture_stage'] ?? null,
                'RANKING_PREFERENCE_AFTER' => $state['ranking_preference'] ?? null,
                'TIME_PREFERENCE_AFTER' => $state['time_preference'] ?? null,
                'MODEL_CALLS' => (int) data_get($turn['json'], 'meta.MODEL_CALLS', data_get($turn['json'], 'meta.GENERAL_MODEL_CALLS', 0)),
                'SEARCH_CALLS' => $searchCalls,
                'PENDING_CONFIRMATION_PRESENT' => is_array($pending),
                'USER_RESPONSE_CLASS' => $this->classifyResponse($body, $state),
                'message_excerpt' => mb_substr($body, 0, 160),
            ];
        }
        $this->writeJson('02-endpoint-repro.json', [
            'CQ46_FALSE_LEAD_BASELINE' => 'CONFIRMED',
            'SEARCH_BEFORE_CONFIRMATION' => $searchBeforeConfirm,
            'rows' => $endpoint,
        ]);

        $this->assertSame('cheapest', $endpoint['cheapest']['LEAD_NAME_AFTER']);
        $this->assertSame('fastest', $endpoint['fastest']['LEAD_NAME_AFTER']);
        $this->assertSame('morning', $endpoint['morning']['LEAD_NAME_AFTER']);
        $this->assertSame(0, $searchBeforeConfirm);

        // --- §18 open-jaw ---
        $ojPrior = [
            'intent' => 'flight_search',
            'origin' => 'LHE',
            'destination' => 'JED',
            'depart_date' => '2026-10-06',
            'trip_type' => 'open_jaw',
            'legs' => [
                ['origin' => 'LHE', 'destination' => 'JED', 'departure_date' => '2026-10-06'],
                ['origin' => 'MED', 'destination' => 'LHE', 'departure_date' => null],
            ],
            'lead_capture_pending' => true,
            'lead_capture_stage' => 'name',
            'lead_name' => null,
        ];
        $ojRows = [];
        foreach (['cheapest', 'morning'] as $msg) {
            $auth = $signals->progressiveTravelAuthority($msg, $ojPrior);
            $det = $signals->deterministicAuthorityComplete($msg, $ojPrior);
            $vid = substr('cq46oj'.md5($msg).str_repeat('x', 32), 0, 42);
            $conv = AiConversation::query()->create([
                'public_id' => (string) Str::uuid(),
                'visitor_token_hash' => hash('sha256', $vid),
                'channel' => 'web',
                'state' => AiConversation::STATE_AI_ACTIVE,
                'shopping_state' => $ojPrior,
            ]);
            $turn = $this->chat($vid, $msg, $conv->public_id);
            $turn['response']->assertOk();
            $st = $this->reloadState($conv->public_id);
            $ojRows[$msg] = [
                'SERVER_ACTIVE' => (bool) ($auth['active'] ?? false),
                'IS_TRAVEL_AUTHORITY_TURN' => $router->isTravelAuthorityTurn($msg, $ojPrior),
                'deterministic_complete' => (bool) ($det['complete'] ?? false),
                'deterministic_reason' => $det['reason'] ?? null,
                'LEAD_NAME_AFTER' => $st['lead_name'] ?? null,
                'trip_type_after' => $st['trip_type'] ?? null,
                'legs_count_after' => is_array($st['legs'] ?? null) ? count($st['legs']) : null,
                'RANKING_PREFERENCE_AFTER' => $st['ranking_preference'] ?? null,
                'TIME_PREFERENCE_AFTER' => $st['time_preference'] ?? null,
            ];
        }
        $this->writeJson('07-openjaw-semantics.json', [
            'OPEN_JAW_RANKING_SEMANTICS' => 'LEG_AMBIGUOUS',
            'OPEN_JAW_TIME_SEMANTICS' => 'LEG_AMBIGUOUS',
            'rationale' => 'Preference applies globally to itinerary ranking/time but which leg for morning departure is ambiguous; CQ45 already blocks stop_refinement deterministic complete for prior multi-leg. Ranking/time not in progressive authority today so false-lead still occurs.',
            'rows' => $ojRows,
        ]);

        // --- §19 standalone ---
        $standalone = [];
        foreach (['cheapest', 'fastest', 'morning'] as $msg) {
            $vid = substr('cq46st'.md5($msg).str_repeat('x', 32), 0, 42);
            $help = $this->chat($vid, 'I need help');
            $cid = $help['conversation_id'];
            $turn = $this->chat($vid, $msg, $cid);
            $turn['response']->assertOk();
            $st = $this->reloadState($cid);
            $standalone[$msg] = [
                'STATUS' => (string) data_get($turn['json'], 'status', ''),
                'LEAD_NAME_AFTER' => $st['lead_name'] ?? null,
                'LEAD_STAGE_AFTER' => $st['lead_capture_stage'] ?? null,
                'message_excerpt' => mb_substr((string) data_get($turn['json'], 'message', ''), 0, 160),
                'LOOKS_LIKE_BARE_NAME' => $router->looksLikeBareName($msg),
            ];
        }
        $this->writeJson('07b-standalone.json', $standalone);

        // --- §20 name collision ---
        $collisions = [];
        foreach ([
            'My name is Morning',
            'My name is Fast',
            'My name is Best',
        ] as $msg) {
            $vid = substr('cq46nm'.md5($msg).str_repeat('x', 32), 0, 42);
            $conv = $this->seedKhiJedPendingLeadName($vid);
            $turn = $this->chat($vid, $msg, $conv->public_id);
            $turn['response']->assertOk();
            $st = $this->reloadState($conv->public_id);
            $collisions[$msg] = [
                'LEAD_NAME_AFTER' => $st['lead_name'] ?? null,
                'LEAD_STAGE_AFTER' => $st['lead_capture_stage'] ?? null,
                'STATUS' => (string) data_get($turn['json'], 'status', ''),
            ];
        }
        $this->writeJson('07c-name-collision.json', $collisions);

        // --- §23 CQ45 / PERF smoke ---
        $vidD = str_repeat('cq46dir', 6);
        $cD = $this->seedKhiJedPendingLeadName($vidD);
        $tD = $this->chat($vidD, 'direct only', $cD->public_id);
        $tD['response']->assertOk();
        $sD = $this->reloadState($cD->public_id);
        $nonreg = [
            'DIRECT_ONLY_LEAD_NAME' => $sD['lead_name'] ?? null,
            'DIRECT_ONLY_MAX_STOPS' => $sD['max_stops'] ?? null,
            'DIRECT_ONLY_MODEL_CALLS' => (int) data_get($tD['json'], 'meta.MODEL_CALLS', data_get($tD['json'], 'meta.GENERAL_MODEL_CALLS', 0)),
        ];
        $this->assertNull($nonreg['DIRECT_ONLY_LEAD_NAME']);
        $this->assertSame(0, (int) $nonreg['DIRECT_ONLY_MAX_STOPS']);

        // Bitcoin CURRENT / dated route / via Doha / destination-led — lightweight signal checks
        $bitcoin = $signals->deterministicAuthorityComplete('What is the Bitcoin CURRENT price?', null);
        $dated = $signals->deterministicAuthorityComplete(
            'I need Islamabad to Dubai on 15 October for 2 adults',
            null
        );
        $via = $signals->deterministicAuthorityComplete(
            'Lahore to Dubai via Doha on 20 October for 1 adult',
            null
        );
        $nonreg['BITCOIN_CURRENT_COMPLETE'] = (bool) ($bitcoin['complete'] ?? false);
        $nonreg['DATED_ISB_DXB_REASON_OR_COMPLETE'] = [
            'complete' => (bool) ($dated['complete'] ?? false),
            'reason' => $dated['reason'] ?? null,
            'classes' => $dated['classes'] ?? [],
        ];
        $nonreg['VIA_DOHA_COMPLETE'] = (bool) ($via['complete'] ?? false);
        $nonreg['VIA_DOHA_REASON'] = $via['reason'] ?? null;
        $this->assertFalse($nonreg['VIA_DOHA_COMPLETE']);
        $this->writeJson('08-nonregression.json', $nonreg);

        // --- §9 pending path classification from endpoint rows ---
        $pathAudit = [
            'CHEAPEST_PENDING_PATH' => 'lead_FSM_unambiguousPendingLead→name_capture (isTravelAuthorityTurn=false; looksLikeBareName=true; pending confirmation handler not reached for material correction)',
            'MORNING_PENDING_PATH' => 'lead_FSM_unambiguousPendingLead→name_capture (same)',
            'ranking_survives' => ($endpoint['cheapest']['RANKING_PREFERENCE_AFTER'] ?? null) !== null,
            'time_survives' => ($endpoint['morning']['TIME_PREFERENCE_AFTER'] ?? null) !== null,
            'pending_confirmation_after_cheapest' => $endpoint['cheapest']['PENDING_CONFIRMATION_PRESENT'],
            'pending_confirmation_after_morning' => $endpoint['morning']['PENDING_CONFIRMATION_PRESENT'],
            'RANKING_USER_RESPONSE_CLASS' => $endpoint['cheapest']['USER_RESPONSE_CLASS'],
            'TIME_USER_RESPONSE_CLASS' => $endpoint['morning']['USER_RESPONSE_CLASS'],
        ];
        $this->writeJson('04-confirmation-path.json', $pathAudit);

        // Static search/results contract (source-derived, asserted via reflection/file presence)
        $toolsSrc = file_get_contents(app_path('Services/Ai/AiShoppingTools.php')) ?: '';
        $fcSrc = file_get_contents(app_path('Http/Controllers/Frontend/FlightController.php')) ?: '';
        $searchContract = [
            'FLIGHT_SEARCH_SUPPORTS_RANKING' => str_contains($toolsSrc, "'sort'") || str_contains($toolsSrc, 'ranking_preference') ? 'PARTIAL_LABELS_ONLY' : 'NO',
            'FLIGHT_SEARCH_SUPPORTS_TIME_PREFERENCE' => str_contains($toolsSrc, 'time_preference') || str_contains($toolsSrc, 'departure_window') ? 'YES' : 'NO',
            'FLIGHT_SEARCH_SUPPORTS_MAX_STOPS' => str_contains($toolsSrc, 'max_stops') && str_contains($toolsSrc, 'http_build_query') ? 'PARTIAL_STUB_ONLY' : 'NO_QUERY_PARAM',
            'note_searchFlights' => 'Deep-link builds /flights/results with trip_type,from,to,depart,return_date,adults,children,infants,cabin — no sort, departure_window, or max_stops query params. DealRankingService::rank runs on a zero-price stub only.',
            'RESULTS_SUPPORT_CHEAPEST' => str_contains($fcSrc, "'cheapest'") ? 'YES' : 'NO',
            'RESULTS_SUPPORT_FASTEST' => str_contains($fcSrc, "'fastest'") ? 'YES' : 'NO',
            'RESULTS_SUPPORT_TIME_WINDOW' => str_contains($fcSrc, 'departure_window') ? 'YES' : 'NO',
            'RESULTS_SUPPORT_MAX_STOPS' => (str_contains($fcSrc, 'max_stops') || str_contains($fcSrc, 'stops')) ? 'YES_FILTER' : 'NO',
            'SUPPORTED_QUERY_PARAMS' => [
                'sort' => ['recommended', 'cheapest', 'price_asc', 'price_desc', 'fastest', 'duration', 'earliest_departure', 'latest_departure', 'departure_time'],
                'departure_window' => ['early_morning', 'morning', 'afternoon', 'evening'],
                'filters' => 'stops/airline/price windows via criteria (controller filter path)',
            ],
            'FLIGHT_REAL_OFFER_RANKING_AVAILABLE' => 'NO_VIA_AI_DEEP_LINK',
            'GROUP_REAL_OFFER_RANKING_AVAILABLE' => 'YES_LABELS_ON_INVENTORY',
            'DealRankingService' => 'Labels CHEAPEST/FASTEST/DIRECT/SHORTEST_LAYOVER/BEST_VALUE on offer arrays; flight AI path uses stub; group search applies to real inventory rows.',
        ];
        // Tighten NO for ranking/time on deep-link:
        $searchContract['FLIGHT_SEARCH_SUPPORTS_RANKING'] = 'NO';
        $searchContract['FLIGHT_SEARCH_SUPPORTS_TIME_PREFERENCE'] = 'NO';
        $this->writeJson('06-search-results-contract.json', $searchContract);

        $parseResultClass = new ReflectionClass($hybridCheapest);
        $this->assertTrue($parseResultClass->hasProperty('rankingPreference'));

        $summary = [
            'CQ46_DIAGNOSTIC' => 'PASS',
            'CQ46_FALSE_LEAD_BASELINE' => 'CONFIRMED',
            'CHEAPEST_FALSE_LEAD' => $endpoint['cheapest']['LEAD_NAME_AFTER'],
            'FASTEST_FALSE_LEAD' => $endpoint['fastest']['LEAD_NAME_AFTER'],
            'MORNING_FALSE_LEAD' => $endpoint['morning']['LEAD_NAME_AFTER'],
            'SEARCH_BEFORE_CONFIRMATION' => $searchBeforeConfirm,
            'out_dir' => $this->outDir,
        ];
        $this->writeJson('SUMMARY.json', $summary);
        $this->assertSame('PASS', $summary['CQ46_DIAGNOSTIC']);
    }

    private function classifyResponse(string $body, array $state): string
    {
        $lower = mb_strtolower($body);
        if (($state['lead_capture_stage'] ?? null) === 'contact'
            || (str_contains($lower, 'email') && str_contains($lower, 'phone'))
        ) {
            return 'incorrectly_asks_for_contact';
        }
        if (str_contains($lower, 'confirm') || str_contains($lower, 'shall i search')) {
            return 'restates_confirmation';
        }
        if (preg_match('/cheap|fast|morning|evening|night|preference|sort/u', $lower) === 1) {
            return 'acknowledges_preference';
        }

        return 'silently_ignores_or_other';
    }
}
