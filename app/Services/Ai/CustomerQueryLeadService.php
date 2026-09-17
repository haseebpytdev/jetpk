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
        if (! $this->intentClassifier->isCommercialTravelIntent($message)) {
            return false;
        }

        return ! $this->hasValidLead($conversation, $user);
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

        $fields = $this->requiredLeadFields($user);
        $known = $this->knownFieldLabels($user);

        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $state['lead_capture_pending'] = true;
        $state['lead_pending_message'] = $message;
        $state['lead_capture_fields'] = $fields;
        $conversation->shopping_state = $state;
        $conversation->save();

        return $this->buildLeadCaptureResponse($conversation, $fields, $known);
    }

    /**
     * Resume an in-progress lead capture gate with the correct field set.
     *
     * @return array<string, mixed>
     */
    public function resumeLeadCapturePayload(AiConversation $conversation): array
    {
        $user = $this->resolveConversationUser($conversation);
        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $fields = is_array($state['lead_capture_fields'] ?? null) && $state['lead_capture_fields'] !== []
            ? array_values($state['lead_capture_fields'])
            : $this->requiredLeadFields($user);

        return $this->buildLeadCaptureResponse($conversation, $fields, $this->knownFieldLabels($user));
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
        $contactFields = array_values(array_intersect($fields, self::CONTACT_FIELDS));

        return [
            'ok' => true,
            'status' => 'lead_capture_required',
            'mode' => 'LEAD_CAPTURE',
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $this->buildLeadCaptureMessage($contactFields),
            'lead_capture' => [
                'required' => true,
                'fields' => $fields,
                'known_fields' => array_keys($known),
            ],
            'recommendations' => [],
            'actions' => [],
            'meta' => [
                'lead_capture_pending' => true,
                'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
                'AI_GROUP_SEARCH_READ_CALLS' => 0,
            ],
        ];
    }

    /**
     * @param  list<string>  $contactFields
     */
    private function buildLeadCaptureMessage(array $contactFields): string
    {
        if ($contactFields === []) {
            return 'Before we continue with your travel request, may I have your permission to contact you about this inquiry if our team needs to follow up?';
        }

        if (count($contactFields) === 1) {
            return match ($contactFields[0]) {
                'name' => 'Before we continue, please share your name so our team can follow up on this inquiry if needed.',
                'email' => 'Before we continue, please share your email address so our team can follow up on this inquiry if needed.',
                'phone' => 'Before we continue, please share your contact number so our team can follow up on this inquiry if needed.',
                default => 'Before we continue, please share the missing contact detail below.',
            };
        }

        return 'Sure. Before we continue, may I have your name, email address and contact number so our team can follow up on your inquiry if needed?';
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
