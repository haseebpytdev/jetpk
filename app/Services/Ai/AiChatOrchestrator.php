<?php

namespace App\Services\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Models\AiHandoffAudit;
use App\Models\AiMessage;
use App\Models\CustomerQuery;
use App\Models\User;
use App\Services\Ai\Embed\EmbedRuntimeContext;
use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Public AI chat orchestration: visitor cookie, rate limits, handoff, tools, soft degrade.
 */
final class AiChatOrchestrator
{
    public const COOKIE = 'jp_ai_vid';

    public function __construct(
        private readonly InferenceProvider $provider,
        private readonly TravelIntentExtractor $extractor,
        private readonly KnowledgeSearchService $knowledge,
        private readonly AiShoppingTools $tools,
        private readonly AiConversationalAgent $conversational,
        private readonly AiAssistantBookingLookupTool $bookingLookupTool,
        private readonly CustomerQueryLeadService $leadService,
        private readonly AiCommercialIntentClassifier $intentClassifier,
        private readonly ConversationIntentRouter $intentRouter,
        private readonly OpenDomainResponseService $openDomain,
        private readonly ?\App\Services\Ai\Lab\AiLabAdapter $labAdapter = null,
    ) {}

    /**
     * @return array{conversation: AiConversation, visitor_raw: string, set_cookie: bool}
     */
    public function resolveConversation(Request $request, ?string $publicId = null, bool $createIfMissing = true): array
    {
        [$visitorRaw, $setCookie] = $this->resolveVisitorToken($request);
        $hash = $this->hashVisitor($visitorRaw);

        $conversation = null;
        if (is_string($publicId) && $publicId !== '') {
            $conversation = AiConversation::query()
                ->where('public_id', $publicId)
                ->where('visitor_token_hash', $hash)
                ->whereNull('ai_embed_tenant_id')
                ->first();
        }

        if ($conversation === null && $createIfMissing && ($publicId === null || $publicId === '')) {
            $openQuery = $this->leadService->findRecentOpenQuery($hash, $request->user());
            if ($openQuery?->conversation !== null) {
                $linked = $openQuery->conversation;
                if ($linked->state === AiConversation::STATE_CLOSED) {
                    $conversation = AiConversation::query()->create([
                        'channel' => 'web',
                        'visitor_token_hash' => $hash,
                        'user_id' => $request->user()?->id,
                        'state' => AiConversation::STATE_AI_ACTIVE,
                        'shopping_state' => [],
                    ]);
                    $openQuery->ai_conversation_id = $conversation->id;
                    $openQuery->save();
                    $setCookie = true;
                } else {
                    $conversation = $linked;
                }
            } else {
                $conversation = AiConversation::query()->create([
                    'channel' => 'web',
                    'visitor_token_hash' => $hash,
                    'user_id' => $request->user()?->id,
                    'state' => AiConversation::STATE_AI_ACTIVE,
                    'shopping_state' => [],
                ]);
                $setCookie = true;
            }
        } elseif ($conversation === null && $createIfMissing) {
            // Unknown/foreign public_id: start a fresh owned conversation (do not leak existence).
            $conversation = AiConversation::query()->create([
                'channel' => 'web',
                'visitor_token_hash' => $hash,
                'user_id' => $request->user()?->id,
                'state' => AiConversation::STATE_AI_ACTIVE,
                'shopping_state' => [],
            ]);
            $setCookie = true;
        }

        return [
            'conversation' => $conversation,
            'visitor_raw' => $visitorRaw,
            'set_cookie' => $setCookie,
            'visitor_hash' => $hash,
        ];
    }

    public function findOwnedConversation(Request $request, string $publicId): ?AiConversation
    {
        [$visitorRaw] = $this->resolveVisitorToken($request);

        return $this->findOwnedConversationByVisitor($visitorRaw, $publicId);
    }

    public function findOwnedConversationByVisitor(string $visitorRaw, string $publicId, ?int $embedTenantId = null): ?AiConversation
    {
        $query = AiConversation::query()
            ->where('public_id', $publicId)
            ->where('visitor_token_hash', $this->hashVisitor($visitorRaw));

        if ($embedTenantId !== null) {
            $query->where('ai_embed_tenant_id', $embedTenantId);
        } else {
            $query->whereNull('ai_embed_tenant_id');
        }

        return $query->first();
    }

    /**
     * Embed transport: resolve conversation without jp_ai_vid cookie access.
     *
     * @return array{conversation: ?AiConversation, visitor_raw: string, visitor_hash: string}
     */
    public function resolveConversationForVisitor(
        string $visitorRaw,
        ?string $publicId = null,
        bool $createIfMissing = true,
        string $channel = 'embed',
        ?int $userId = null,
        ?int $embedTenantId = null,
    ): array {
        $hash = $this->hashVisitor($visitorRaw);

        $conversation = null;
        if (is_string($publicId) && $publicId !== '') {
            $conversation = $this->findOwnedConversationByVisitor($visitorRaw, $publicId, $embedTenantId);
        }

        if ($conversation === null && $createIfMissing && ($publicId === null || $publicId === '')) {
            $openQuery = $this->leadService->findRecentOpenQuery($hash, null, $embedTenantId);
            if ($openQuery?->conversation !== null) {
                $conversation = $openQuery->conversation;
            } else {
                $conversation = AiConversation::query()->create([
                    'channel' => $channel,
                    'ai_embed_tenant_id' => $embedTenantId,
                    'visitor_token_hash' => $hash,
                    'user_id' => $userId,
                    'state' => AiConversation::STATE_AI_ACTIVE,
                    'shopping_state' => [],
                ]);
            }
        } elseif ($conversation === null && $createIfMissing) {
            $conversation = AiConversation::query()->create([
                'channel' => $channel,
                'ai_embed_tenant_id' => $embedTenantId,
                'visitor_token_hash' => $hash,
                'user_id' => $userId,
                'state' => AiConversation::STATE_AI_ACTIVE,
                'shopping_state' => [],
            ]);
        }

        return [
            'conversation' => $conversation,
            'visitor_raw' => $visitorRaw,
            'visitor_hash' => $hash,
        ];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    public function resolveVisitorToken(Request $request): array
    {
        $existing = (string) $request->cookie(self::COOKIE, '');
        if (strlen($existing) >= 32 && preg_match('/^[A-Za-z0-9]+$/', $existing) === 1) {
            return [$existing, false];
        }

        return [Str::random(40), true];
    }

    public function hashVisitor(string $raw): string
    {
        return hash('sha256', $raw);
    }

    public function assertRateLimit(string $visitorRaw): ?array
    {
        // Chat-send budget only. Polling /messages must never hit this key.
        $max = (int) config('ota.ai_assistant.anonymous_per_minute', 30);
        $key = 'ai-chat-send:'.hash('sha256', $visitorRaw);
        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retryAfter = max(1, RateLimiter::availableIn($key));

            return [
                'ok' => false,
                'status' => 'rate_limited',
                'message' => "You're sending messages pretty quickly. Please try again in a few seconds.",
                'retry_after' => $retryAfter,
                'actions' => $this->defaultActions(),
            ];
        }
        RateLimiter::hit($key, 60);

        return null;
    }

    /**
     * Sanitize + injection gate. Returns error payload or cleaned message.
     *
     * @return array{ok: true, message: string}|array{ok: false, payload: array<string, mixed>}
     */
    public function sanitizeUserMessage(string $raw): array
    {
        $max = (int) config('ota.ai_assistant.max_message_chars', 2000);
        $clean = strip_tags($raw);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = trim(preg_replace("/\0/", '', $clean) ?? $clean);
        // Strip client coercion artifacts (undefined/null prefixes) and exact doubled paste.
        $clean = preg_replace('/^(?:undefined|null)+/iu', '', $clean) ?? $clean;
        $clean = trim($clean);
        if (mb_strlen($clean) >= 16) {
            $half = (int) floor(mb_strlen($clean) / 2);
            $left = mb_substr($clean, 0, $half);
            $right = mb_substr($clean, $half);
            if ($left !== '' && $left === $right) {
                $clean = $left;
            }
        }
        if (mb_strlen($clean) > $max) {
            $clean = mb_substr($clean, 0, $max);
        }

        if ($clean === '') {
            return [
                'ok' => false,
                'payload' => [
                    'ok' => false,
                    'status' => 'invalid',
                    'message' => 'Please enter a message.',
                    'actions' => $this->defaultActions(),
                ],
            ];
        }

        if ($this->looksLikeInjection($clean)) {
            return [
                'ok' => false,
                'payload' => [
                    'ok' => true,
                    'status' => 'refused',
                    'mode' => 'STRUCTURED_FALLBACK',
                    'message' => 'I can help with flights, groups, bookings, and payments. Please ask a travel question without special instructions.',
                    'actions' => $this->defaultActions(),
                ],
            ];
        }

        return ['ok' => true, 'message' => $clean];
    }

    /**
     * @return array<string, mixed>
     */
    public function handleChat(AiConversation $conversation, string $cleanMessage, bool $userMessageAlreadyStored = false): array
    {
        if (! $userMessageAlreadyStored) {
            $this->storeMessage($conversation, 'user', $cleanMessage);
        }

        if (in_array($conversation->state, [
            AiConversation::STATE_WAITING_FOR_HUMAN,
            AiConversation::STATE_HUMAN_ACTIVE,
        ], true)) {
            return [
                'ok' => true,
                'status' => 'waiting_for_human',
                'mode' => 'HUMAN_QUEUE',
                'conversation_id' => $conversation->public_id,
                'state' => $conversation->state,
                'message' => 'Your message was sent to our support team. An agent will reply here shortly.',
                'recommendations' => [],
                'actions' => [
                    ['label' => 'Contact Support', 'href' => '/support'],
                    ['label' => 'Lookup Booking', 'href' => '/lookup-booking'],
                ],
                'meta' => [
                    'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
                    'AI_GROUP_SEARCH_READ_CALLS' => 0,
                ],
            ];
        }

        if ($conversation->state === AiConversation::STATE_CLOSED) {
            $conversation->state = AiConversation::STATE_AI_ACTIVE;
            $conversation->save();
        }

        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];

        // SECURITY / write guard before lead extraction.
        $lockedWrite = $this->lockedWriteActionRequest($cleanMessage);
        if ($lockedWrite !== null) {
            return $this->replyLockedWriteRefusal($conversation, $lockedWrite);
        }

        // HELP-FIRST lead precedence:
        // SECURITY → STRONG INTENT / KNOWLEDGE / OPEN-DOMAIN → LEAD EXTRACTION
        if (($state['lead_capture_pending'] ?? false) && ! $userMessageAlreadyStored) {
            $this->leadService->extractOpportunisticLeadFields($conversation, $cleanMessage);
            $conversation->refresh();
            $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];

            if ($this->intentRouter->shouldOverrideLeadCapture($cleanMessage)) {
                // Keep lead pending; continue assisting on this turn.
            } else {
                return $this->handleConversationalLeadTurn($conversation, $cleanMessage);
            }
        } elseif (! ($state['lead_capture_pending'] ?? false) && $this->embedCapabilityAllows(EmbedTenantCapability::LEAD_CAPTURE)) {
            $leadPrompt = $this->leadService->leadCapturePromptPayload(
                $conversation,
                $cleanMessage,
                $this->resolveAuthenticatedUser($conversation),
            );
            if (is_array($leadPrompt)) {
                $assistant = $this->storeMessage($conversation, 'assistant', (string) $leadPrompt['message'], [
                    'mode' => 'STRUCTURED_FALLBACK',
                ]);

                return $this->withMessageId($assistant, $leadPrompt);
            }
            $conversation->refresh();
        }

        $priorState = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];

        $baseMeta = [
            'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
            'AI_GROUP_SEARCH_READ_CALLS' => 0,
            'LOCAL_LLM_REQUIRED_FOR_CORE' => false,
        ];

        // Open-domain before lab/LLM so harmless general questions are never swallowed by travel lab.
        $openDomain = $this->openDomain->tryRespond(
            $cleanMessage,
            $this->tenantCapabilityLabels(),
            $this->assistantBrandName(),
        );
        if (is_array($openDomain)) {
            $assistant = $this->storeMessage($conversation, 'assistant', (string) $openDomain['message'], [
                'mode' => 'STRUCTURED_FALLBACK',
                'open_domain' => $openDomain['category'],
            ]);

            return $this->withMessageId($assistant, [
                'ok' => true,
                'status' => 'ok',
                'mode' => 'STRUCTURED_FALLBACK',
                'conversation_id' => $conversation->public_id,
                'state' => $conversation->state,
                'message' => $openDomain['message'],
                'recommendations' => [],
                'actions' => $this->defaultActions(),
                'meta' => array_merge($baseMeta, $openDomain['meta']),
            ]);
        }

        if (
            ! $this->shouldUseStructuredCorePath($cleanMessage, $priorState)
            && $this->shouldUseLabAdapter($conversation)
            && $this->labAdapter !== null
        ) {
            try {
                $labResponse = $this->labAdapter->handleTurn($conversation, $cleanMessage);
                $searchRecord = is_array($labResponse['meta'] ?? null)
                    ? ($labResponse['meta']['search_record'] ?? null)
                    : null;
                $this->syncLeadFromConversation($conversation, is_array($searchRecord) ? $searchRecord : null);

                return $labResponse;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ai.lab.adapter_turn_failed', [
                    'conversation_id' => $conversation->public_id,
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
                if (! (bool) config('ai_lab.fallback_to_legacy', true)) {
                    $assistant = $this->storeMessage($conversation, 'assistant', 'AI assistant is temporarily unavailable. Please try again shortly.', [
                        'mode' => 'AI_UNAVAILABLE',
                    ]);

                    return $this->withMessageId($assistant, [
                        'ok' => false,
                        'status' => 'unavailable',
                        'mode' => 'AI_UNAVAILABLE',
                        'conversation_id' => $conversation->public_id,
                        'state' => $conversation->state,
                        'message' => 'AI assistant is temporarily unavailable. Please try again shortly.',
                        'recommendations' => [],
                        'actions' => $this->defaultActions(),
                        'meta' => [
                            'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
                            'AI_GROUP_SEARCH_READ_CALLS' => 0,
                        ],
                    ]);
                }
            }
        }

        $baseMeta = [
            'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
            'AI_GROUP_SEARCH_READ_CALLS' => 0,
            'LOCAL_LLM_REQUIRED_FOR_CORE' => false,
        ];

        $conversational = $this->conversational->tryHandle($conversation, $cleanMessage, $baseMeta);
        if (is_array($conversational)) {
            if (! empty($conversational['handoff'])) {
                return $this->beginHandoff($conversation, 'llm_requested', 'LLM_ASSISTED', $baseMeta);
            }

            $mode = (string) ($conversational['mode'] ?? 'LLM_ASSISTED');
            $body = (string) ($conversational['message'] ?? '');
            if ($body !== '') {
                $assistant = $this->storeMessage($conversation, 'assistant', $body, [
                    'mode' => $mode,
                    'tool' => $conversational['tool'] ?? null,
                ]);

                return $this->withMessageId($assistant, [
                    'ok' => true,
                    'status' => 'ok',
                    'mode' => $mode,
                    'conversation_id' => $conversation->public_id,
                    'state' => $conversation->state,
                    'message' => $body,
                    'recommendations' => $conversational['recommendations'] ?? [],
                    'knowledge' => $conversational['knowledge'] ?? [],
                    'actions' => $conversational['actions'] ?? $this->defaultActions(),
                    'meta' => $conversational['meta'] ?? $baseMeta,
                ]);
            }
        }

        // Hybrid model-free core when LLM unavailable or did not produce a reply.
        $mode = 'STRUCTURED_FALLBACK';
        $prior = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];

        $hybrid = $this->extractor->extractHybrid($cleanMessage, $prior);
        $intent = $hybrid->intent;

        $conversation->shopping_state = $this->extractor->patchState($prior, $intent);
        if ($hybrid->rankingPreference) {
            $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
            $state['ranking_preference'] = $hybrid->rankingPreference;
            $conversation->shopping_state = $state;
        }
        $conversation->save();
        $this->syncLeadFromConversation($conversation);
        $this->syncLeadFromConversation($conversation);

        $meta = array_merge([
            'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
            'AI_GROUP_SEARCH_READ_CALLS' => 0,
            'LOCAL_LLM_REQUIRED_FOR_CORE' => false,
            'llm_bypassed' => $hybrid->llmBypassed,
            'provenance' => $hybrid->provenance,
        ], $hybrid->toMeta());

        if ($intent->intent === 'handoff') {
            if (! $this->embedCapabilityAllows(EmbedTenantCapability::SUPPORT_HANDOFF)) {
                return $this->replyCapabilityUnavailable($conversation, $mode, $meta);
            }

            return $this->beginHandoff($conversation, 'user_requested', $mode, $meta);
        }

        if ($intent->intent === 'knowledge') {
            if (! $this->embedCapabilityAllows(EmbedTenantCapability::KNOWLEDGE)) {
                return $this->replyCapabilityUnavailable($conversation, $mode, $meta);
            }

            return $this->replyKnowledge($conversation, $cleanMessage, $mode, $meta);
        }

        if ($intent->intent === 'booking_lookup' || $this->shouldContinueBookingLookup($prior, $cleanMessage)) {
            if (! $this->embedCapabilityAllows(EmbedTenantCapability::BOOKING_LOOKUP)) {
                return $this->replyCapabilityUnavailable($conversation, $mode, $meta);
            }

            return $this->replyBookingLookup($conversation, $cleanMessage, $mode, $meta);
        }

        if ($hybrid->clarificationRequired) {
            $body = $hybrid->clarificationMessage ?: $this->clarifyMessage($intent, $mode);
            $assistant = $this->storeMessage($conversation, 'assistant', $body, [
                'mode' => $mode,
                'intent' => $intent->toArray(),
                'clarification_options' => $hybrid->clarificationOptions,
            ]);

            return $this->withMessageId($assistant, [
                'ok' => true,
                'status' => 'clarify',
                'mode' => $mode,
                'conversation_id' => $conversation->public_id,
                'state' => $conversation->state,
                'message' => $body,
                'clarification_options' => $hybrid->clarificationOptions,
                'recommendations' => [],
                'actions' => $this->defaultActions(),
                'meta' => $meta,
            ]);
        }

        if ($intent->intent === 'flight_search' || ($intent->isSearchable() && $intent->intent !== 'group_search')) {
            if ($intent->origin && $intent->destination) {
                if (! $this->embedCapabilityAllows(EmbedTenantCapability::FLIGHT_SEARCH)) {
                    return $this->replyCapabilityUnavailable($conversation, $mode, $meta);
                }

                return $this->replyFlightSearch($conversation, $intent, $mode, $meta);
            }
        }

        if ($intent->intent === 'group_search') {
            return $this->replyGroupSearch($conversation, $intent, $mode, $meta);
        }

        $body = $hybrid->clarificationMessage ?: $this->clarifyMessage($intent, $mode);
        $assistant = $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'intent' => $intent->toArray(),
        ]);

        return $this->withMessageId($assistant, [
            'ok' => true,
            'status' => 'ok',
            'mode' => $mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'recommendations' => [],
            'actions' => $this->defaultActions(),
            'meta' => $meta,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function requestHandoff(AiConversation $conversation, ?string $reason = null): array
    {
        if (! (bool) config('ota.ai_assistant.human_handoff_enabled', true)) {
            return [
                'ok' => false,
                'status' => 'unavailable',
                'message' => 'Human handoff is temporarily unavailable. Please use Contact Support.',
                'actions' => [['label' => 'Contact Support', 'href' => '/support']],
            ];
        }

        return $this->beginHandoff(
            $conversation,
            $reason ?: 'user_requested',
            $this->resolveMode(),
            ['AI_FLIGHT_SEARCH_READ_CALLS' => 0, 'AI_GROUP_SEARCH_READ_CALLS' => 0]
        );
    }

    public function clearConversation(AiConversation $old, string $visitorHash, string $channel = 'web'): AiConversation
    {
        $old->state = AiConversation::STATE_CLOSED;
        $old->save();

        $conversation = AiConversation::query()->create([
            'channel' => $channel,
            'visitor_token_hash' => $visitorHash,
            'user_id' => $old->user_id,
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [],
        ]);

        foreach (
            CustomerQuery::query()
                ->where('ai_conversation_id', $old->id)
                ->get() as $linkedQuery
        ) {
            $linkedQuery->ai_conversation_id = $conversation->id;
            $linkedQuery->save();
        }

        return $conversation;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesSince(AiConversation $conversation, ?int $sinceId = null): array
    {
        $q = $conversation->messages()->orderBy('id');
        if ($sinceId !== null && $sinceId > 0) {
            $q->where('id', '>', $sinceId);
        }

        return $q->limit(100)->get()->map(static function (AiMessage $m): array {
            return [
                'id' => $m->id,
                'role' => $m->role,
                'body' => $m->body,
                'meta' => $m->meta,
                'created_at' => $m->created_at?->toIso8601String(),
            ];
        })->all();
    }

    public function healthPayload(): array
    {
        $enabled = (bool) config('ota.ai_assistant.enabled', false);
        $healthy = $enabled && $this->provider->isHealthy() && ! $this->memoryPressure();

        return [
            'ok' => true,
            'enabled' => $enabled,
            'gateway' => $healthy ? 'healthy' : ($enabled ? 'degraded' : 'disabled'),
            'provider' => $this->provider->name(),
            'mode' => $this->resolveMode(),
        ];
    }

    public function resolveMode(): string
    {
        $eligibility = app(\App\Services\Ai\AiAssistantEligibility::class);
        if (! $eligibility->isRuntimeOn()) {
            return 'AI_UNAVAILABLE';
        }
        if ($this->memoryPressure()) {
            return 'STRUCTURED_FALLBACK';
        }
        if ($this->conversational->isEnabled() && $this->provider->isHealthy()) {
            return 'LLM_ASSISTED';
        }
        if (! $this->provider->isHealthy()) {
            return 'STRUCTURED_FALLBACK';
        }

        return 'STRUCTURED_FALLBACK';
    }

    private function memoryPressure(): bool
    {
        $minMb = (int) config('ota.ai_assistant.load_shed_available_mb_min', 2000);
        $available = $this->availableMemoryMb();
        if ($available === null) {
            return false;
        }

        return $available < $minMb;
    }

    private function availableMemoryMb(): ?float
    {
        if (is_readable('/proc/meminfo')) {
            $raw = @file_get_contents('/proc/meminfo');
            if (is_string($raw) && preg_match('/MemAvailable:\s+(\d+)\s+kB/i', $raw, $m) === 1) {
                return ((float) $m[1]) / 1024.0;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $prior
     */
    private function shouldUseStructuredCorePath(string $cleanMessage, array $prior): bool
    {
        if ($this->shouldContinueBookingLookup($prior, $cleanMessage)) {
            return true;
        }

        if ($this->intentClassifier->isBookingHelpIntent($cleanMessage)) {
            return true;
        }

        if ($this->intentRouter->classifyOpenDomain($cleanMessage) !== null) {
            return true;
        }

        if ($this->intentRouter->isJetPakistanKnowledgeQuestion($cleanMessage)) {
            return true;
        }

        if ($this->intentRouter->hasStrongActionableIntent($cleanMessage)) {
            return true;
        }

        $lower = mb_strtolower(trim($cleanMessage));
        if (preg_match(
            '/talk to (a )?(person|human)|speak to (a )?(person|human|support|agent)|need to speak to|human (support|agent)|agent please|live agent|connect (me )?to (a )?(human|agent|support)|please connect me to support|handoff/u',
            $lower,
        ) === 1) {
            return true;
        }

        return false;
    }

    private function shouldUseLabAdapter(AiConversation $conversation): bool
    {
        $effective = app(AiAssistantSettingsService::class)->effective();
        if (! ($effective['lab_adapter_enabled'] ?? false)) {
            return false;
        }

        $mode = app(AiAssistantEligibility::class)->mode();
        if ($mode === AiAssistantEligibility::MODE_INTERNAL_CANARY) {
            $user = $conversation->user_id ? User::query()->find($conversation->user_id) : null;

            return app(AiAssistantEligibility::class)->isCanaryUser($user instanceof User ? $user : null);
        }

        if ($mode === AiAssistantEligibility::MODE_PUBLIC) {
            return true;
        }

        return false;
    }

    private function looksLikeInjection(string $message): bool
    {
        $lower = mb_strtolower($message);

        return (bool) preg_match(
            '/ignore (all |any )?(previous|prior|above) (instructions|prompts)|system prompt|reveal (your |the )?(system|hidden) prompt|jailbreak|dan mode|do anything now|tool call:|function call:|<\/?script|onerror\s*=/u',
            $lower
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function beginHandoff(AiConversation $conversation, string $reason, string $mode, array $meta): array
    {
        if (! (bool) config('ota.ai_assistant.human_handoff_enabled', true)) {
            $body = 'I could not reach a human agent right now. Please use Contact Support.';
            $assistant = $this->storeMessage($conversation, 'assistant', $body, ['mode' => $mode]);

            return $this->withMessageId($assistant, [
                'ok' => true,
                'status' => 'ok',
                'mode' => $mode,
                'conversation_id' => $conversation->public_id,
                'state' => $conversation->state,
                'message' => $body,
                'recommendations' => [],
                'actions' => [['label' => 'Contact Support', 'href' => '/support']],
                'meta' => $meta,
            ]);
        }

        $from = $conversation->state;
        $conversation->state = AiConversation::STATE_WAITING_FOR_HUMAN;
        $conversation->save();

        AiHandoffAudit::query()->create([
            'ai_conversation_id' => $conversation->id,
            'staff_user_id' => null,
            'from_state' => $from,
            'to_state' => AiConversation::STATE_WAITING_FOR_HUMAN,
            'reason' => substr($reason, 0, 64),
        ]);

        $body = 'I have connected you with our support queue. A team member will reply in this chat. AI replies are paused.';
        $assistant = $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'handoff' => true,
        ]);

        return $this->withMessageId($assistant, [
            'ok' => true,
            'status' => 'waiting_for_human',
            'mode' => $mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'recommendations' => [],
            'actions' => [
                ['label' => 'Contact Support', 'href' => '/support'],
                ['label' => 'Lookup Booking', 'href' => '/lookup-booking'],
            ],
            'meta' => $meta,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function replyKnowledge(AiConversation $conversation, string $message, string $mode, array $meta): array
    {
        $hits = $this->knowledge->search($message, 3);
        if ($hits === []) {
            $body = 'I could not find an approved JetPakistan answer for that yet. I can still help with flights, group travel, booking lookup, or connect you to support.';
            $assistant = $this->storeMessage($conversation, 'assistant', $body, ['mode' => $mode, 'knowledge_hits' => 0]);

            return $this->withMessageId($assistant, [
                'ok' => true,
                'status' => 'ok',
                'mode' => $mode,
                'conversation_id' => $conversation->public_id,
                'state' => $conversation->state,
                'message' => $body,
                'recommendations' => [],
                'knowledge' => [],
                'actions' => [
                    ['label' => 'Talk to Support', 'action' => 'handoff'],
                    ['label' => 'Contact Support', 'href' => '/support'],
                    ['label' => 'FAQ', 'href' => '/faq'],
                ],
                'meta' => array_merge($meta, [
                    'KNOWLEDGE_HITS' => 0,
                    'ANSWER_GROUNDED' => 'NO',
                    'LLM_SYNTHESIS' => 'NO',
                ]),
            ]);
        }

        $body = $this->synthesizeGroundedKnowledgeReply($message, $hits);
        $slugs = array_values(array_map(static fn ($h) => (string) $h['slug'], $hits));
        $primary = $slugs[0] ?? 'unknown';
        $assistant = $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'knowledge' => $slugs,
        ]);

        return $this->withMessageId($assistant, [
            'ok' => true,
            'status' => 'ok',
            'mode' => $mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'knowledge' => $hits,
            'recommendations' => [],
            'actions' => $this->defaultActions(),
            'meta' => array_merge($meta, [
                'KNOWLEDGE_SOURCE' => $primary,
                'KNOWLEDGE_HITS' => count($hits),
                'ANSWER_GROUNDED' => 'YES',
                'LLM_SYNTHESIS' => $mode === 'LLM_ASSISTED' ? 'YES' : 'STRUCTURED',
            ]),
        ]);
    }

    /**
     * Natural grounded synthesis from approved knowledge hits (not a hardcoded company blurb).
     *
     * @param  list<array{slug: string, title: string, excerpt: string, score?: float}>  $hits
     */
    private function synthesizeGroundedKnowledgeReply(string $message, array $hits): string
    {
        $primary = $hits[0];
        $excerpt = trim((string) ($primary['excerpt'] ?? ''));
        $title = trim((string) ($primary['title'] ?? ''));
        $slug = (string) ($primary['slug'] ?? '');

        if ($slug === 'what-is-jetpakistan' || str_contains(mb_strtolower($message), 'what is jetpakistan')) {
            // Compose from approved excerpt — do not invent beyond the source.
            if ($excerpt !== '') {
                return $excerpt.(str_ends_with($excerpt, '.') ? '' : '.').' I can also help search flights, explore group travel options, look up a booking, or connect you with support.';
            }
        }

        if ($excerpt !== '') {
            $prefix = $title !== '' ? $title.': ' : '';

            return $prefix.$excerpt;
        }

        $parts = [];
        foreach ($hits as $hit) {
            $parts[] = '**'.$hit['title']."**\n".$hit['excerpt'];
        }

        return "Here is what I found in JetPakistan help:\n\n".implode("\n\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function replyBookingLookup(AiConversation $conversation, string $message, string $mode, array $meta): array
    {
        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $state = $this->bookingLookupTool->patchState($state, $message);
        $state['intent'] = 'booking_lookup';
        $conversation->shopping_state = $state;
        $conversation->save();

        $reference = is_string($state['booking_reference'] ?? null) ? $state['booking_reference'] : null;
        $email = is_string($state['booking_email'] ?? null) ? $state['booking_email'] : null;
        $phone = is_string($state['booking_phone'] ?? null) ? $state['booking_phone'] : null;

        if ($reference === null && $email === null && $phone === null) {
            $body = 'Of course — I can help you check a booking. Please send your booking reference and the email or phone number used when you booked.';
            $status = 'clarify';
            $bookingPayload = null;
        } elseif ($reference === null) {
            $body = 'Thanks. What is your booking reference? I need it together with your email or phone to verify your booking securely.';
            $status = 'clarify';
            $bookingPayload = null;
        } elseif ($email === null && $phone === null) {
            $body = 'Thanks. What email or phone number was used for booking '.$reference.'? JetPakistan verifies your ownership before showing booking details.';
            $status = 'clarify';
            $bookingPayload = null;
        } else {
            $lookup = $this->bookingLookupTool->lookup($reference, $email, $phone);
            $body = (string) ($lookup['message'] ?? 'Lookup complete.');
            $status = ($lookup['found'] ?? false) ? 'ok' : 'not_found';
            $bookingPayload = $lookup['booking'] ?? null;
        }

        $assistant = $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'intent' => [
                'intent' => 'booking_lookup',
                'booking_reference' => $reference,
                'booking_email' => $email,
                'booking_phone' => $phone,
            ],
            'booking' => $bookingPayload,
        ]);

        $meta['intent'] = ['intent' => 'booking_lookup'];
        if (is_array($bookingPayload)) {
            $meta['booking_lookup'] = $bookingPayload;
        }

        return $this->withMessageId($assistant, [
            'ok' => true,
            'status' => $status,
            'mode' => $mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'recommendations' => [],
            'booking' => $bookingPayload,
            'actions' => [
                ['label' => 'Lookup Booking', 'href' => '/lookup-booking'],
                ['label' => 'Talk to Support', 'action' => 'handoff'],
            ],
            'meta' => $meta,
        ]);
    }

    /**
     * @param  array<string, mixed>  $prior
     */
    private function shouldContinueBookingLookup(array $prior, string $message): bool
    {
        if (($prior['intent'] ?? '') === 'booking_lookup') {
            return true;
        }

        if (! isset($prior['booking_reference']) && ! isset($prior['booking_email']) && ! isset($prior['booking_phone'])) {
            return false;
        }

        return preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $message) === 1
            || preg_match('/\b(?:\+?92|0)3[0-9]{9}\b/', $message) === 1
            || preg_match('/\b(reference|ref|pnr)\s*(is|:)?\s*[A-Z0-9]{5,12}\b/i', $message) === 1
            || preg_match('/\b[A-Z0-9]{5,12}\b/u', $message) === 1;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function replyFlightSearch(AiConversation $conversation, $intent, string $mode, array $meta): array
    {
        $result = $this->tools->searchFlights($intent);
        $meta['AI_FLIGHT_SEARCH_READ_CALLS'] = (int) ($result['meta']['AI_FLIGHT_SEARCH_READ_CALLS'] ?? 0);
        $recs = $result['recommendations'];
        $note = $result['freshness_note'];

        if ($recs === []) {
            $body = 'I could not build a flight search for that route yet. Try origin and destination like LHE to DXB.';
        } else {
            $body = 'I prepared a flight search for '.$intent->origin.' → '.$intent->destination.'. '.$note;
        }

        $assistant = $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'recommendations' => $recs,
        ]);

        return $this->withMessageId($assistant, [
            'ok' => true,
            'status' => 'ok',
            'mode' => $mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'recommendations' => $recs,
            'actions' => [
                ['label' => 'Search Flights', 'href' => '/#flight-search'],
                ['label' => 'Talk to Support', 'action' => 'handoff'],
            ],
            'meta' => $meta,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function replyGroupSearch(AiConversation $conversation, $intent, string $mode, array $meta): array
    {
        $result = $this->tools->searchGroups($intent);
        $meta['AI_GROUP_SEARCH_READ_CALLS'] = (int) ($result['meta']['AI_GROUP_SEARCH_READ_CALLS'] ?? 0);
        $recs = $result['recommendations'];
        $note = $result['freshness_note'];

        if ($recs === []) {
            $body = 'No published group packages matched. You can browse all groups or talk to support.';
        } else {
            $body = 'Here are up to '.count($recs).' group options. '.$note;
        }

        $assistant = $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'recommendations' => $recs,
        ]);

        return $this->withMessageId($assistant, [
            'ok' => true,
            'status' => 'ok',
            'mode' => $mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'recommendations' => $recs,
            'actions' => [
                ['label' => 'Browse Groups', 'href' => '/groups'],
                ['label' => 'Talk to Support', 'action' => 'handoff'],
            ],
            'meta' => $meta,
        ]);
    }

    private function clarifyMessage($intent, string $mode): string
    {
        if ($mode === 'AI_UNAVAILABLE') {
            return 'AI assistance is running in limited mode. Tell me a route like LHE to DXB tomorrow, ask about payments, or talk to support.';
        }

        if ($intent->intent === 'flight_search' || $intent->intent === 'group_search') {
            return 'Please share origin and destination (for example: Lahore to Dubai) and a travel date.';
        }

        return 'I can help search flights or groups, answer booking/payment FAQs, or connect you with support. What would you like to do?';
    }

    private function lockedWriteActionRequest(string $message): ?string
    {
        $lower = mb_strtolower(trim($message));
        if (preg_match('/\b(can you |please )?(book|buy|purchase|reserve)\b/u', $lower)
            && preg_match('/\b(for me|this|it|cheapest|that one|the cheapest|one for me)\b/u', $lower)) {
            return 'booking';
        }
        if (preg_match('/\b(cancel|refund|void)\b/u', $lower)
            && preg_match('/\b(booking|reservation|this|it|for me)\b/u', $lower)) {
            return 'cancel';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function replyLockedWriteRefusal(AiConversation $conversation, string $action): array
    {
        $body = match ($action) {
            'booking' => 'I cannot complete a booking directly in chat, but I can help you review options and guide you through the booking process on JetPakistan. Please use the View & Book link on your chosen flight, or talk to support if you need personal assistance.',
            'cancel' => 'I cannot cancel or change a booking directly in chat. Please contact JetPakistan support with your booking reference and verified contact details, or use the support options on our website.',
            default => 'That action is not available in chat. I can help with travel questions, search, and support guidance.',
        };
        $assistant = $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => 'STRUCTURED_FALLBACK',
            'locked_write_action' => $action,
        ]);

        return $this->withMessageId($assistant, [
            'ok' => true,
            'status' => 'ok',
            'mode' => 'STRUCTURED_FALLBACK',
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'recommendations' => [],
            'actions' => [
                ['label' => 'Contact Support', 'href' => '/support'],
                ['label' => 'Lookup Booking', 'href' => '/lookup-booking'],
            ],
            'meta' => [
                'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
                'AI_GROUP_SEARCH_READ_CALLS' => 0,
                'locked_write_action' => $action,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleConversationalLeadTurn(AiConversation $conversation, string $cleanMessage): array
    {
        $turn = $this->leadService->handleConversationalLeadTurn(
            $conversation,
            $cleanMessage,
            $this->resolveAuthenticatedUser($conversation),
            $conversation->visitor_token_hash,
        );

        if (($turn['action'] ?? '') === 'override') {
            // Topic overrides field collection — assist without re-storing the user turn.
            return $this->handleChat($conversation->fresh(), $cleanMessage, true);
        }

        $assistant = $this->storeMessage($conversation, 'assistant', (string) ($turn['response']['message'] ?? ''), [
            'mode' => 'STRUCTURED_FALLBACK',
        ]);
        $payload = $this->withMessageId($assistant, $turn['response']);

        if (($turn['action'] ?? '') === 'replay' && filled($turn['pending_message'] ?? null)) {
            $followUp = $this->handleChat($conversation->fresh(), (string) $turn['pending_message']);
            if (isset($turn['query'])) {
                $followUp['query_reference'] = $turn['query']->query_reference;
            }

            return $followUp;
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function tenantCapabilityLabels(): array
    {
        $ctx = app(EmbedRuntimeContext::class);
        if ($ctx->isActive()) {
            $labels = [];
            if ($this->embedCapabilityAllows(EmbedTenantCapability::FLIGHT_SEARCH)) {
                $labels[] = 'flights';
            }
            if ($this->embedCapabilityAllows(EmbedTenantCapability::BOOKING_LOOKUP)) {
                $labels[] = 'booking_lookup';
                $labels[] = 'booking_assistance';
            }
            if ($this->embedCapabilityAllows(EmbedTenantCapability::SUPPORT_HANDOFF)) {
                $labels[] = 'support';
            }
            // Never invent JetPakistan-only group redirects for generic tenants.
            return $labels;
        }

        return ['flights', 'group_travel', 'booking_assistance', 'support'];
    }

    private function assistantBrandName(): string
    {
        $ctx = app(EmbedRuntimeContext::class);
        if ($ctx->isActive()) {
            $tenant = $ctx->tenant();
            $name = trim((string) ($tenant?->display_name ?: $tenant?->assistant_name ?: ''));

            // Generic embed tenants must not be redirected as JetPakistan.
            return $name !== '' ? $name : 'this assistant';
        }

        return 'JetPakistan';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withMessageId(AiMessage $message, array $payload): array
    {
        $payload['message_id'] = $message->id;

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function storeMessage(AiConversation $conversation, string $role, string $body, ?array $meta = null): AiMessage
    {
        $max = (int) config('ota.ai_assistant.max_conversation_messages', 80);
        $count = $conversation->messages()->count();
        if ($count >= $max) {
            $conversation->messages()->orderBy('id')->limit(max(1, (int) floor($max / 4)))->delete();
        }

        $msg = $conversation->messages()->create([
            'role' => $role,
            'body' => $body,
            'meta' => $meta,
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        return $msg;
    }

    /**
     * @return list<array{label: string, href?: string, action?: string}>
     */
    private function defaultActions(): array
    {
        return [
            ['label' => 'Search Flights', 'href' => '/#flight-search'],
            ['label' => 'Browse Groups', 'href' => '/groups'],
            ['label' => 'Manage Booking', 'href' => '/lookup-booking'],
            ['label' => 'Talk to Support', 'action' => 'handoff'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $searchRecord
     */
    private function resolveAuthenticatedUser(AiConversation $conversation): ?User
    {
        if ($conversation->relationLoaded('user')) {
            return $conversation->user;
        }

        if ($conversation->user_id) {
            return $conversation->user()->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $searchRecord
     */
    private function syncLeadFromConversation(AiConversation $conversation, ?array $searchRecord = null): void
    {
        $query = $this->leadService->findRecentOpenQuery(
            $conversation->visitor_token_hash,
            $this->resolveAuthenticatedUser($conversation),
            $conversation->ai_embed_tenant_id,
        );
        if ($query === null) {
            return;
        }

        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        if ($state !== []) {
            $this->leadService->syncFromTravelState($query, $state);
        }

        if (is_array($searchRecord)) {
            $this->leadService->linkSearchEvent($query, $searchRecord);
        }
    }

    private function embedCapabilityAllows(string $capability): bool
    {
        if (! app()->bound(EmbedRuntimeContext::class)) {
            return true;
        }

        $ctx = app(EmbedRuntimeContext::class);
        if (! $ctx->isActive()) {
            return true;
        }

        return match ($capability) {
            EmbedTenantCapability::LEAD_CAPTURE => $ctx->leadCaptureEnabled(),
            EmbedTenantCapability::KNOWLEDGE => $ctx->knowledgeEnabled(),
            EmbedTenantCapability::FLIGHT_SEARCH => $ctx->flightSearchEnabled(),
            EmbedTenantCapability::BOOKING_LOOKUP => $ctx->bookingLookupEnabled(),
            EmbedTenantCapability::SUPPORT_HANDOFF => $ctx->handoffEnabled(),
            EmbedTenantCapability::GENERAL_AI => $ctx->tenant()?->hasCapability(EmbedTenantCapability::GENERAL_AI) ?? false,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function replyCapabilityUnavailable(AiConversation $conversation, string $mode, array $meta): array
    {
        $body = 'That capability is not enabled for this assistant.';
        $assistant = $this->storeMessage($conversation, 'assistant', $body, ['mode' => $mode]);

        return $this->withMessageId($assistant, [
            'ok' => true,
            'status' => 'ok',
            'mode' => $mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'recommendations' => [],
            'actions' => [],
            'meta' => $meta,
        ]);
    }
}
