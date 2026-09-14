<?php

namespace App\Services\Ai;

use App\Enums\CustomerQueryStatus;
use App\Models\AiConversation;
use App\Models\CustomerQuery;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Ask JetPakistan lead capture, visitor resume, and structured query updates.
 */
final class CustomerQueryLeadService
{
    public const OPEN_QUERY_MINUTES = 20;

    public const CONSENT_SOURCE = 'ask_jetpakistan';

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
        if ($this->findRecentOpenQuery($conversation->visitor_token_hash, $user) !== null) {
            return true;
        }

        if ($user !== null) {
            return $this->profileHasContact($user);
        }

        return false;
    }

    public function needsLeadCapture(AiConversation $conversation, string $message, ?User $user = null): bool
    {
        if (! $this->intentClassifier->isCommercialTravelIntent($message)) {
            return false;
        }

        return ! $this->hasValidLead($conversation, $user);
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
        $errors = $this->validatePayload($payload);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $email = mb_strtolower(trim((string) $payload['email']));
        $phone = $this->normalizePhone((string) $payload['phone'], (string) ($payload['phone_country'] ?? $ipCountryHint ?? 'PK'));

        $existing = $this->findRecentOpenQuery($visitorHash, $user);
        if ($existing !== null) {
            $existing->fill([
                'name' => trim((string) $payload['name']),
                'email' => $email,
                'phone_raw' => trim((string) $payload['phone']),
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
            'name' => trim((string) $payload['name']),
            'email' => $email,
            'phone_raw' => trim((string) $payload['phone']),
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
    public function leadCapturePromptPayload(AiConversation $conversation, string $message): ?array
    {
        if (! $this->needsLeadCapture($conversation, $message, $conversation->user)) {
            return null;
        }

        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $state['lead_capture_pending'] = true;
        $state['lead_pending_message'] = $message;
        $conversation->shopping_state = $state;
        $conversation->save();

        return [
            'ok' => true,
            'status' => 'lead_capture_required',
            'mode' => 'LEAD_CAPTURE',
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => 'Sure. Before we continue, may I have your name, email address and contact number so our team can follow up on your inquiry if needed?',
            'lead_capture' => [
                'required' => true,
                'fields' => ['name', 'email', 'phone', 'contact_consent'],
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

    public function profileContactPayload(?User $user): ?array
    {
        if ($user === null || ! $this->profileHasContact($user)) {
            return null;
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'contact_consent' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function validatePayload(array $payload): array
    {
        $errors = [];
        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['name'] = 'Please enter a valid name.';
        } elseif (! preg_match('/^[\p{L}\p{M}\s\'\-\.]+$/u', $name)) {
            $errors['name'] = 'Name contains invalid characters.';
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        $phone = trim((string) ($payload['phone'] ?? ''));
        if ($phone === '' || mb_strlen($phone) < 7 || mb_strlen($phone) > 40) {
            $errors['phone'] = 'Please enter a valid contact number.';
        }

        if (! ($payload['contact_consent'] ?? false)) {
            $errors['contact_consent'] = 'Consent is required so JetPakistan may contact you about this inquiry.';
        }

        return $errors;
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
        return filled($user->name)
            && filled($user->email)
            && filter_var($user->email, FILTER_VALIDATE_EMAIL)
            && filled($user->phone);
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
