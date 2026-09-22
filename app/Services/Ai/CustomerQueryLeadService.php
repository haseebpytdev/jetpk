<?php

namespace App\Services\Ai;

use App\Enums\CustomerQueryStatus;
use App\Models\AiConversation;
use App\Models\CustomerQuery;
use App\Models\User;
use Carbon\Carbon;

/**
 * Ask JetPakistan lead capture, visitor resume, and structured query updates.
 */
final class CustomerQueryLeadService
{
    public const OPEN_QUERY_MINUTES = 20;

    public const CONSENT_SOURCE = 'ask_jetpakistan';

    /** @var list<string> */
    private const CONTACT_FIELDS = ['name', 'email', 'phone'];

    public function __construct(
        private readonly AiCommercialIntentClassifier $intentClassifier,
    ) {}

    public function findRecentOpenQuery(?string $visitorHash, ?User $user = null): ?CustomerQuery
    {
        $cutoff = now()->subMinutes(self::OPEN_QUERY_MINUTES);
        $query = CustomerQuery::query()
            ->where('contact_consent', true)
            ->where('last_activity_at', '>=', $cutoff)
            ->whereIn('status', [
                CustomerQueryStatus::New,
                CustomerQueryStatus::Qualified,
                CustomerQueryStatus::CallbackRequired,
                CustomerQueryStatus::FollowUp,
            ]);

        if ($user !== null) {
            $query->where('user_id', $user->id);
        } elseif (is_string($visitorHash) && $visitorHash !== '') {
            $query->where('visitor_token_hash', $visitorHash);
        } else {
            return null;
        }

        return $query->orderByDesc('last_activity_at')->first();
    }

    public function hasValidLead(AiConversation $conversation, ?User $user = null): bool
    {
        return $this->findRecentOpenQuery($conversation->visitor_token_hash, $user) !== null;
    }

    public function needsLeadCapture(AiConversation $conversation, string $message, ?User $user = null): bool
    {
        if ($this->hasValidLead($conversation, $user)) {
            return false;
        }

        return $this->intentClassifier->isCommercialTravelIntent($message)
            || $this->intentClassifier->isSupportAssistanceIntent($message);
    }

    /**
     * @return list<string>
     */
    public function requiredLeadFields(?User $user = null): array
    {
        if ($user === null) {
            return ['name', 'email', 'phone', 'contact_consent'];
        }

        $contact = $this->resolveProfileContact($user);
        $missing = [];

        if (! $this->isValidName($contact['name'])) {
            $missing[] = 'name';
        }
        if (! filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'email';
        }
        if (! $this->isValidPhone($contact['phone'])) {
            $missing[] = 'phone';
        }

        $missing[] = 'contact_consent';

        return $missing;
    }

    /**
     * @return array{name: bool, email: bool, phone: bool, contact_reusable: bool}
     */
    public function profileContactAvailability(?User $user): array
    {
        if ($user === null) {
            return [
                'name' => false,
                'email' => false,
                'phone' => false,
                'contact_reusable' => false,
            ];
        }

        $contact = $this->resolveProfileContact($user);

        return [
            'name' => $this->isValidName($contact['name']),
            'email' => filter_var($contact['email'], FILTER_VALIDATE_EMAIL) !== false,
            'phone' => $this->isValidPhone($contact['phone']),
            'contact_reusable' => $this->profileHasContact($user),
        ];
    }

    /**
     * @return array{ok: true, query: CustomerQuery}|array{ok: false, errors: array<string, string>}
     */
    public function createFromPayload(
        AiConversation $conversation,
        array $payload,
        ?string $visitorHash,
        ?User $user = null,
        ?string $ipCountryHint = null,
    ): array {
        $merged = $this->mergeKnownContact($payload, $user);
        $errors = $this->validatePayload($merged, $this->requiredLeadFields($user));
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $email = mb_strtolower(trim((string) $merged['email']));
        $phone = $this->normalizePhone((string) $merged['phone'], (string) ($merged['phone_country'] ?? $ipCountryHint ?? 'PK'));

        $existing = $this->findRecentOpenQuery($visitorHash, $user);
        if ($existing !== null) {
            $existing->fill([
                'name' => trim((string) $merged['name']),
                'email' => $email,
                'phone_raw' => trim((string) $merged['phone']),
                'phone_e164' => $phone['e164'],
                'phone_country' => $phone['country'],
                'contact_consent' => true,
                'consent_timestamp' => now(),
                'consent_source' => self::CONSENT_SOURCE,
                'last_activity_at' => now(),
            ])->save();

            return ['ok' => true, 'query' => $existing->fresh()];
        }

        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $pendingIntent = (string) ($state['lead_pending_message'] ?? '');

        $query = CustomerQuery::query()->create([
            'visitor_token_hash' => $visitorHash,
            'user_id' => $user?->id,
            'ai_conversation_id' => $conversation->id,
            'name' => trim((string) $merged['name']),
            'email' => $email,
            'phone_raw' => trim((string) $merged['phone']),
            'phone_e164' => $phone['e164'],
            'phone_country' => $phone['country'],
            'email_verified' => false,
            'phone_verified' => false,
            'contact_consent' => true,
            'consent_timestamp' => now(),
            'consent_source' => self::CONSENT_SOURCE,
            'source' => self::CONSENT_SOURCE,
            'intent' => $pendingIntent !== '' ? 'flight_search' : null,
            'status' => CustomerQueryStatus::New,
            'callback_required' => true,
            'priority' => 'normal',
            'ip_country_hint' => $ipCountryHint,
            'ai_summary' => $pendingIntent !== '' ? $this->summarizeIntentMessage($pendingIntent) : null,
            'last_activity_at' => now(),
        ]);

        return ['ok' => true, 'query' => $query];
    }

    public function syncFromTravelState(CustomerQuery $query, array $travelState): void
    {
        $updates = array_filter([
            'origin' => $travelState['origin'] ?? null,
            'destination' => $travelState['destination'] ?? null,
            'departure_date' => $this->parseDate($travelState['departure_date'] ?? $travelState['depart_date'] ?? null),
            'return_date' => $this->parseDate($travelState['return_date'] ?? null),
            'trip_type' => $travelState['trip_type'] ?? null,
            'adult_count' => isset($travelState['adults']) ? (int) $travelState['adults'] : null,
            'child_count' => isset($travelState['children']) ? (int) $travelState['children'] : null,
            'infant_count' => isset($travelState['infants']) ? (int) $travelState['infants'] : null,
            'travel_state' => $travelState,
            'last_activity_at' => now(),
        ], static fn ($v) => $v !== null && $v !== '');

        if ($updates !== []) {
            $query->fill($updates);
            $query->ai_summary = $this->buildSummary($query);
            if ($query->origin && $query->destination) {
                $query->status = CustomerQueryStatus::Qualified;
            }
            $query->save();
        }
    }

    public function linkSearchEvent(CustomerQuery $query, array $searchMeta): void
    {
        $state = is_array($query->travel_state) ? $query->travel_state : [];
        $events = is_array($state['search_events'] ?? null) ? $state['search_events'] : [];
        $events[] = [
            'origin' => $searchMeta['origin'] ?? null,
            'destination' => $searchMeta['destination'] ?? null,
            'departure_date' => $searchMeta['departure_date'] ?? null,
            'return_date' => $searchMeta['return_date'] ?? null,
            'trip_type' => $searchMeta['trip_type'] ?? null,
            'passengers' => $searchMeta['passengers'] ?? null,
            'offer_count' => $searchMeta['offer_count'] ?? null,
            'recorded_at' => now()->toIso8601String(),
        ];
        $state['search_events'] = array_slice($events, -10);
        $query->travel_state = $state;
        $query->callback_required = true;
        $query->last_activity_at = now();
        $query->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function leadCapturePromptPayload(AiConversation $conversation, string $message, ?User $user = null): ?array
    {
        $user = $user ?? $this->resolveConversationUser($conversation);

        if (! $this->needsLeadCapture($conversation, $message, $user)) {
            return null;
        }

        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $state['lead_capture_pending'] = true;
        $state['lead_pending_message'] = $message;
        $state['lead_capture_fields'] = $this->requiredLeadFields($user);
        $state = $this->seedLeadContactFromProfile($state, $user);
        $state['lead_capture_stage'] = $this->resolveLeadCaptureStage($state, $user);
        $conversation->shopping_state = $state;
        $conversation->save();

        return $this->buildConversationalResponse(
            $conversation,
            $this->promptForStage($state, $user),
            true,
        );
    }

    /**
     * Advance conversational lead capture from the current user turn.
     *
     * @return array{
     *     action: 'prompt'|'complete'|'declined'|'replay',
     *     response: array<string, mixed>,
     *     pending_message?: string,
     *     query?: CustomerQuery
     * }
     */
    public function handleConversationalLeadTurn(
        AiConversation $conversation,
        string $message,
        ?User $user = null,
        ?string $visitorHash = null,
        ?string $ipCountryHint = null,
    ): array {
        $user = $user ?? $this->resolveConversationUser($conversation);
        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $stage = (string) ($state['lead_capture_stage'] ?? 'name');

        if ($stage === 'name') {
            return $this->handleNameStage($conversation, $message, $state, $user);
        }

        if ($stage === 'contact') {
            return $this->handleContactStage($conversation, $message, $state, $user);
        }

        if ($stage === 'consent') {
            return $this->handleConsentStage(
                $conversation,
                $message,
                $state,
                $user,
                $visitorHash ?? $conversation->visitor_token_hash,
                $ipCountryHint,
            );
        }

        $state['lead_capture_stage'] = $this->resolveLeadCaptureStage($state, $user);
        $conversation->shopping_state = $state;
        $conversation->save();

        return [
            'action' => 'prompt',
            'response' => $this->buildConversationalResponse(
                $conversation,
                $this->promptForStage($state, $user),
                true,
            ),
        ];
    }

    /**
     * @deprecated Structured form endpoint only; conversational flow uses handleConversationalLeadTurn.
     *
     * @return array<string, mixed>
     */
    public function resumeLeadCapturePayload(AiConversation $conversation): array
    {
        $user = $this->resolveConversationUser($conversation);
        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $state['lead_capture_stage'] = $this->resolveLeadCaptureStage($state, $user);
        $conversation->shopping_state = $state;
        $conversation->save();

        return $this->buildConversationalResponse(
            $conversation,
            $this->promptForStage($state, $user),
            true,
        );
    }

    public function profileContactPayload(?User $user): ?array
    {
        if ($user === null || ! $this->profileHasContact($user)) {
            return null;
        }

        $contact = $this->resolveProfileContact($user);

        return [
            'name' => $contact['name'],
            'email' => $contact['email'],
            'phone' => $contact['phone'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function validatePayload(array $payload, array $requiredFields): array
    {
        $errors = [];
        $name = trim((string) ($payload['name'] ?? ''));
        if (! $this->isValidName($name)) {
            $errors['name'] = 'Please enter a valid name.';
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        $phone = trim((string) ($payload['phone'] ?? ''));
        if (! $this->isValidPhone($phone)) {
            $errors['phone'] = 'Please enter a valid contact number.';
        }

        if (in_array('contact_consent', $requiredFields, true) && ! ($payload['contact_consent'] ?? false)) {
            $errors['contact_consent'] = 'Consent is required so JetPakistan may contact you about this inquiry.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeKnownContact(array $payload, ?User $user): array
    {
        if ($user === null) {
            return $payload;
        }

        $known = $this->resolveProfileContact($user);
        $merged = $payload;

        foreach (self::CONTACT_FIELDS as $field) {
            $submitted = trim((string) ($merged[$field] ?? ''));
            if ($submitted === '' && filled($known[$field])) {
                $merged[$field] = $known[$field];
            }
        }

        return $merged;
    }

    /**
     * @return array{name: string, email: string, phone: string}
     */
    private function resolveProfileContact(User $user): array
    {
        $profile = $user->relationLoaded('profile') ? $user->profile : $user->profile()->first();
        $phone = filled($profile?->phone)
            ? trim((string) $profile->phone)
            : (filled($profile?->whatsapp) ? trim((string) $profile->whatsapp) : '');

        return [
            'name' => trim((string) $user->name),
            'email' => mb_strtolower(trim((string) $user->email)),
            'phone' => $phone,
        ];
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, string>
     */
    private function knownFieldLabels(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $contact = $this->resolveProfileContact($user);
        $known = [];

        if ($this->isValidName($contact['name'])) {
            $known['name'] = $contact['name'];
        }
        if (filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            $known['email'] = $contact['email'];
        }
        if ($this->isValidPhone($contact['phone'])) {
            $known['phone'] = $contact['phone'];
        }

        return $known;
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, string>  $known
     * @return array<string, mixed>
     */
    private function buildLeadCaptureResponse(AiConversation $conversation, array $fields, array $known): array
    {
        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $user = $this->resolveConversationUser($conversation);

        return $this->buildConversationalResponse(
            $conversation,
            $this->promptForStage($state, $user),
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConversationalResponse(
        AiConversation $conversation,
        string $message,
        bool $pending = false,
    ): array {
        return [
            'ok' => true,
            'status' => 'ok',
            'mode' => 'STRUCTURED_FALLBACK',
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $message,
            'recommendations' => [],
            'actions' => [],
            'meta' => [
                'lead_capture_pending' => $pending,
                'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
                'AI_GROUP_SEARCH_READ_CALLS' => 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{
     *     action: 'prompt'|'complete'|'declined'|'replay',
     *     response: array<string, mixed>,
     *     pending_message?: string,
     *     query?: CustomerQuery
     * }
     */
    private function handleNameStage(
        AiConversation $conversation,
        string $message,
        array $state,
        ?User $user,
    ): array {
        $name = trim($message);
        if (! $this->isValidName($name)) {
            return [
                'action' => 'prompt',
                'response' => $this->buildConversationalResponse(
                    $conversation,
                    "I didn't quite catch the name. What should I call you?",
                    true,
                ),
            ];
        }

        $state['lead_name'] = $name;
        $state['lead_capture_stage'] = $this->resolveLeadCaptureStage($state, $user);
        $conversation->shopping_state = $state;
        $conversation->save();

        return [
            'action' => 'prompt',
            'response' => $this->buildConversationalResponse(
                $conversation,
                $this->promptForStage($state, $user),
                true,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{
     *     action: 'prompt'|'complete'|'declined'|'replay',
     *     response: array<string, mixed>,
     *     pending_message?: string,
     *     query?: CustomerQuery
     * }
     */
    private function handleContactStage(
        AiConversation $conversation,
        string $message,
        array $state,
        ?User $user,
    ): array {
        $parsed = $this->parseContactFromMessage($message);
        $email = $parsed['email'] ?? (string) ($state['lead_email'] ?? '');
        $phone = $parsed['phone'] ?? (string) ($state['lead_phone'] ?? '');
        $errors = [];

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email';
            $email = (string) ($state['lead_email'] ?? '');
        }

        if ($phone !== '' && ! $this->isValidPhone($phone)) {
            $errors[] = 'phone';
            $phone = (string) ($state['lead_phone'] ?? '');
        }

        if ($email !== '') {
            $state['lead_email'] = mb_strtolower($email);
        }
        if ($phone !== '') {
            $state['lead_phone'] = trim($phone);
        }

        $needsEmail = ! filter_var((string) ($state['lead_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $needsPhone = ! $this->isValidPhone((string) ($state['lead_phone'] ?? ''));

        if ($needsEmail && $needsPhone && $errors !== []) {
            $conversation->shopping_state = $state;
            $conversation->save();

            return [
                'action' => 'prompt',
                'response' => $this->buildConversationalResponse(
                    $conversation,
                    'I could not read a valid email address and contact number from that. Could you share both again?',
                    true,
                ),
            ];
        }

        if ($needsEmail && ! $needsPhone) {
            $conversation->shopping_state = $state;
            $conversation->save();

            return [
                'action' => 'prompt',
                'response' => $this->buildConversationalResponse(
                    $conversation,
                    $errors !== [] && in_array('email', $errors, true)
                        ? 'That email address does not look valid. What is the best email for you?'
                        : "Thanks. What's the best email address for you?",
                    true,
                ),
            ];
        }

        if ($needsPhone && ! $needsEmail) {
            $conversation->shopping_state = $state;
            $conversation->save();

            return [
                'action' => 'prompt',
                'response' => $this->buildConversationalResponse(
                    $conversation,
                    $errors !== [] && in_array('phone', $errors, true)
                        ? 'That contact number does not look valid. What is the best contact number for you?'
                        : "Thanks. What's the best contact number for you?",
                    true,
                ),
            ];
        }

        if ($needsEmail || $needsPhone) {
            $conversation->shopping_state = $state;
            $conversation->save();

            return [
                'action' => 'prompt',
                'response' => $this->buildConversationalResponse(
                    $conversation,
                    $this->promptForStage($state, $user),
                    true,
                ),
            ];
        }

        $state['lead_capture_stage'] = 'consent';
        $conversation->shopping_state = $state;
        $conversation->save();

        return [
            'action' => 'prompt',
            'response' => $this->buildConversationalResponse(
                $conversation,
                $this->promptForStage($state, $user),
                true,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{
     *     action: 'prompt'|'complete'|'declined'|'replay',
     *     response: array<string, mixed>,
     *     pending_message?: string,
     *     query?: CustomerQuery
     * }
     */
    private function handleConsentStage(
        AiConversation $conversation,
        string $message,
        array $state,
        ?User $user,
        ?string $visitorHash,
        ?string $ipCountryHint,
    ): array {
        $consent = $this->parseConsent($message);
        if ($consent === null) {
            return [
                'action' => 'prompt',
                'response' => $this->buildConversationalResponse(
                    $conversation,
                    'Please let me know with yes or no — is it okay for JetPakistan to contact you about this inquiry if our team needs to follow up?',
                    true,
                ),
            ];
        }

        if ($consent === false) {
            $pendingMessage = (string) ($state['lead_pending_message'] ?? '');
            $this->clearLeadCaptureState($conversation);

            return [
                'action' => 'declined',
                'response' => $this->buildConversationalResponse(
                    $conversation,
                    "No problem — I won't save this as a contactable inquiry. I can still help with general travel questions.",
                    false,
                ),
                'pending_message' => $pendingMessage,
            ];
        }

        $payload = [
            'name' => (string) ($state['lead_name'] ?? ''),
            'email' => (string) ($state['lead_email'] ?? ''),
            'phone' => (string) ($state['lead_phone'] ?? ''),
            'contact_consent' => true,
        ];
        $result = $this->createFromPayload(
            $conversation,
            $payload,
            $visitorHash,
            $user,
            $ipCountryHint,
        );

        if (! ($result['ok'] ?? false)) {
            return [
                'action' => 'prompt',
                'response' => $this->buildConversationalResponse(
                    $conversation,
                    'I still need valid contact details before we continue. Could you share your email address and contact number again?',
                    true,
                ),
            ];
        }

        $name = trim((string) ($state['lead_name'] ?? ''));
        $pendingMessage = (string) ($state['lead_pending_message'] ?? '');
        $this->clearLeadCaptureState($conversation);

        $ack = $pendingMessage !== ''
            ? "Perfect, {$name}. Let me help with that."
            : "Perfect, {$name}. How can I help you?";

        $response = $this->buildConversationalResponse($conversation, $ack, false);
        $response['query_reference'] = $result['query']->query_reference;

        if ($pendingMessage !== '') {
            return [
                'action' => 'replay',
                'response' => $response,
                'pending_message' => $pendingMessage,
                'query' => $result['query'],
            ];
        }

        return [
            'action' => 'complete',
            'response' => $response,
            'query' => $result['query'],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function seedLeadContactFromProfile(array $state, ?User $user): array
    {
        if ($user === null) {
            return $state;
        }

        $contact = $this->resolveProfileContact($user);
        if ($this->isValidName($contact['name']) && ! filled($state['lead_name'] ?? null)) {
            $state['lead_name'] = $contact['name'];
        }
        if (filter_var($contact['email'], FILTER_VALIDATE_EMAIL) && ! filled($state['lead_email'] ?? null)) {
            $state['lead_email'] = $contact['email'];
        }
        if ($this->isValidPhone($contact['phone']) && ! filled($state['lead_phone'] ?? null)) {
            $state['lead_phone'] = $contact['phone'];
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function resolveLeadCaptureStage(array $state, ?User $user): string
    {
        $state = $this->seedLeadContactFromProfile($state, $user);
        $name = (string) ($state['lead_name'] ?? '');
        $email = (string) ($state['lead_email'] ?? '');
        $phone = (string) ($state['lead_phone'] ?? '');

        if (! $this->isValidName($name)) {
            return 'name';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $this->isValidPhone($phone)) {
            return 'contact';
        }

        return 'consent';
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function promptForStage(array $state, ?User $user): string
    {
        $stage = $this->resolveLeadCaptureStage($state, $user);
        $name = trim((string) ($state['lead_name'] ?? ''));
        $pending = trim((string) ($state['lead_pending_message'] ?? ''));
        $isSupport = $pending !== '' && $this->intentClassifier->isSupportAssistanceIntent($pending);

        return match ($stage) {
            'name' => $isSupport || $pending === ''
                ? 'Of course. What should I call you?'
                : 'Sure. What should I call you?',
            'contact' => $this->buildContactPrompt($state),
            'consent' => 'Thanks. Is it okay for JetPakistan to contact you about this inquiry if our team needs to follow up?',
            default => 'What should I call you?',
        };
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function buildContactPrompt(array $state): string
    {
        $name = trim((string) ($state['lead_name'] ?? ''));
        $needsEmail = ! filter_var((string) ($state['lead_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $needsPhone = ! $this->isValidPhone((string) ($state['lead_phone'] ?? ''));
        $prefix = $name !== '' ? "Thanks, {$name}. " : 'Thanks. ';

        if ($needsEmail && $needsPhone) {
            return $prefix.'Could you share your email address and contact number as well?';
        }
        if ($needsEmail) {
            return $prefix."What's the best email address for you?";
        }
        if ($needsPhone) {
            return $prefix."What's the best contact number for you?";
        }

        return $prefix.'Could you share your email address and contact number as well?';
    }

    /**
     * @return array{email: ?string, phone: ?string}
     */
    private function parseContactFromMessage(string $message): array
    {
        $email = null;
        $phone = null;

        if (preg_match('/\b[\w.+-]+@[\w.-]+\.\w{2,}\b/u', $message, $matches) === 1) {
            $email = mb_strtolower($matches[0]);
        }

        $compact = preg_replace('/\s+/', '', $message) ?? $message;
        if (preg_match('/(?:\+92|0)?3\d{9}/', $compact, $matches) === 1) {
            $phone = $matches[0];
        } elseif (preg_match('/\b\d{7,15}\b/', $message, $matches) === 1) {
            $candidate = $matches[0];
            if ($email === null || ! str_contains($candidate, '@')) {
                $phone = $candidate;
            }
        }

        return ['email' => $email, 'phone' => $phone];
    }

    private function parseConsent(string $message): ?bool
    {
        $lower = mb_strtolower(trim($message));
        $lower = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $lower) ?? $lower);

        if ($lower === 'no') {
            return false;
        }

        $negative = ['no thanks', 'not now', 'nope', 'nah'];
        $affirmative = ['yes please', 'yes', 'sure', 'okay', 'ok', 'yep', 'yeah', 'jee', 'ji', 'haan', 'han'];

        foreach ($negative as $phrase) {
            if ($lower === $phrase || str_starts_with($lower, $phrase.' ')) {
                return false;
            }
        }
        foreach ($affirmative as $phrase) {
            if ($lower === $phrase || str_starts_with($lower, $phrase.' ')) {
                return true;
            }
        }

        return null;
    }

    private function clearLeadCaptureState(AiConversation $conversation): void
    {
        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        unset(
            $state['lead_capture_pending'],
            $state['lead_capture_stage'],
            $state['lead_name'],
            $state['lead_email'],
            $state['lead_phone'],
            $state['lead_pending_message'],
            $state['lead_capture_fields'],
        );
        $conversation->shopping_state = $state;
        $conversation->save();
    }

    /**
     * @return array{e164: ?string, country: ?string}
     */
    private function normalizePhone(string $raw, string $countryHint): array
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $country = strtoupper(substr($countryHint, 0, 2));

        if (str_starts_with($digits, '92') && strlen($digits) === 12) {
            return ['e164' => '+'.$digits, 'country' => 'PK'];
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11 && $country === 'PK') {
            return ['e164' => '+92'.substr($digits, 1), 'country' => 'PK'];
        }
        if (str_starts_with($raw, '+') && strlen($digits) >= 10) {
            return ['e164' => '+'.$digits, 'country' => $country !== '' ? $country : null];
        }

        return ['e164' => null, 'country' => $country !== '' ? $country : null];
    }

    private function profileHasContact(User $user): bool
    {
        $contact = $this->resolveProfileContact($user);

        return $this->isValidName($contact['name'])
            && filter_var($contact['email'], FILTER_VALIDATE_EMAIL)
            && $this->isValidPhone($contact['phone']);
    }

    private function resolveConversationUser(AiConversation $conversation): ?User
    {
        if ($conversation->relationLoaded('user')) {
            return $conversation->user;
        }

        if ($conversation->user_id) {
            return $conversation->user()->first();
        }

        return null;
    }

    private function isValidName(string $name): bool
    {
        $name = trim($name);

        return mb_strlen($name) >= 2
            && mb_strlen($name) <= 120
            && preg_match('/^[\p{L}\p{M}\s\'\-\.]+$/u', $name) === 1;
    }

    private function isValidPhone(string $phone): bool
    {
        $phone = trim($phone);

        return $phone !== '' && mb_strlen($phone) >= 7 && mb_strlen($phone) <= 40;
    }

    private function summarizeIntentMessage(string $message): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($message)) ?? $message);

        return mb_substr($clean, 0, 500);
    }

    private function buildSummary(CustomerQuery $query): string
    {
        $parts = [];
        if ($query->origin && $query->destination) {
            $parts[] = "Customer is looking for {$query->origin} to {$query->destination} travel";
        }
        if ($query->adult_count) {
            $parts[] = 'for '.$query->adult_count.' adult(s)';
        }
        if ($query->departure_date) {
            $parts[] = 'departing around '.$query->departure_date->format('j F');
        }

        return $parts !== [] ? implode(', ', $parts).'.' : (string) $query->ai_summary;
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
