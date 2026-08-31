<?php

namespace App\Services\Ai;

use App\Contracts\Ai\InferenceProvider;
use App\Models\AiConversation;
use App\Models\AiHandoffAudit;
use App\Models\AiMessage;
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
                ->first();
        }

        if ($conversation === null && $createIfMissing && ($publicId === null || $publicId === '')) {
            $conversation = AiConversation::query()->create([
                'channel' => 'web',
                'visitor_token_hash' => $hash,
                'user_id' => $request->user()?->id,
                'state' => AiConversation::STATE_AI_ACTIVE,
                'shopping_state' => [],
            ]);
            $setCookie = true;
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

        return AiConversation::query()
            ->where('public_id', $publicId)
            ->where('visitor_token_hash', $this->hashVisitor($visitorRaw))
            ->first();
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
        $max = (int) config('ota.ai_assistant.anonymous_per_minute', 8);
        $key = 'ai-chat:'.hash('sha256', $visitorRaw);
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return [
                'ok' => false,
                'status' => 'rate_limited',
                'message' => 'Too many messages. Please wait a moment and try again.',
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
    public function handleChat(AiConversation $conversation, string $cleanMessage): array
    {
        $this->storeMessage($conversation, 'user', $cleanMessage);

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

        $mode = $this->resolveMode();
        $prior = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $intent = $this->extractor->extract(
            $cleanMessage,
            $prior,
            preferStructured: $mode !== 'AI_FULL'
        );

        $conversation->shopping_state = array_merge($prior, $intent->toArray());
        $conversation->save();

        $meta = [
            'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
            'AI_GROUP_SEARCH_READ_CALLS' => 0,
            'intent' => $intent->toArray(),
        ];

        if ($intent->intent === 'handoff') {
            return $this->beginHandoff($conversation, 'user_requested', $mode, $meta);
        }

        if ($intent->intent === 'knowledge') {
            return $this->replyKnowledge($conversation, $cleanMessage, $mode, $meta);
        }

        if ($intent->intent === 'flight_search' || ($intent->isSearchable() && $intent->intent !== 'group_search')) {
            if ($intent->origin && $intent->destination) {
                return $this->replyFlightSearch($conversation, $intent, $mode, $meta);
            }
        }

        if ($intent->intent === 'group_search') {
            return $this->replyGroupSearch($conversation, $intent, $mode, $meta);
        }

        $body = $this->clarifyMessage($intent, $mode);
        $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'intent' => $intent->toArray(),
        ]);

        return [
            'ok' => true,
            'status' => 'ok',
            'mode' => $mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'recommendations' => [],
            'actions' => $this->defaultActions(),
            'meta' => $meta,
        ];
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

    public function clearConversation(AiConversation $old, string $visitorHash): AiConversation
    {
        $old->state = AiConversation::STATE_CLOSED;
        $old->save();

        return AiConversation::query()->create([
            'channel' => 'web',
            'visitor_token_hash' => $visitorHash,
            'user_id' => $old->user_id,
            'state' => AiConversation::STATE_AI_ACTIVE,
            'shopping_state' => [],
        ]);
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
        if (! (bool) config('ota.ai_assistant.enabled', false)) {
            return 'AI_UNAVAILABLE';
        }
        if ($this->memoryPressure() || ! $this->provider->isHealthy()) {
            return 'STRUCTURED_FALLBACK';
        }

        return 'AI_FULL';
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
            $this->storeMessage($conversation, 'assistant', $body, ['mode' => $mode]);

            return [
                'ok' => true,
                'status' => 'ok',
                'mode' => $mode,
                'conversation_id' => $conversation->public_id,
                'state' => $conversation->state,
                'message' => $body,
                'recommendations' => [],
                'actions' => [['label' => 'Contact Support', 'href' => '/support']],
                'meta' => $meta,
            ];
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
        $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'handoff' => true,
        ]);

        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function replyKnowledge(AiConversation $conversation, string $message, string $mode, array $meta): array
    {
        $hits = $this->knowledge->search($message, 3);
        if ($hits === []) {
            $body = 'I could not find an approved answer for that. Would you like to talk to support?';
            $this->storeMessage($conversation, 'assistant', $body, ['mode' => $mode, 'knowledge_hits' => 0]);

            return [
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
                'meta' => $meta,
            ];
        }

        $parts = [];
        foreach ($hits as $hit) {
            $parts[] = '**'.$hit['title']."**\n".$hit['excerpt'];
        }
        $body = "Here is what I found in JetPakistan help:\n\n".implode("\n\n", $parts);
        $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'knowledge' => array_map(static fn ($h) => $h['slug'], $hits),
        ]);

        return [
            'ok' => true,
            'status' => 'ok',
            'mode' => $mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $body,
            'knowledge' => $hits,
            'recommendations' => [],
            'actions' => $this->defaultActions(),
            'meta' => $meta,
        ];
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

        $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'recommendations' => $recs,
        ]);

        return [
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
        ];
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

        $this->storeMessage($conversation, 'assistant', $body, [
            'mode' => $mode,
            'recommendations' => $recs,
        ]);

        return [
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
        ];
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
}
