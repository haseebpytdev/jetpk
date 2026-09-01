<?php

namespace App\Services\Ai\Hybrid;

use App\Data\Ai\HybridParseResult;
use App\Data\Ai\TravelIntent;
use Carbon\Carbon;

/**
 * Model-free hybrid language pipeline for Ask JetPakistan V1 core.
 */
final class HybridTravelPipeline
{
    public function __construct(
        private readonly LanguageNormalizer $normalizer,
        private readonly LocationResolver $locations,
        private readonly AirlineResolver $airlines,
        private readonly DateExpressionResolver $dates,
        private readonly BudgetNormalizer $budget,
        private readonly PassengerExpressionResolver $passengers,
        private readonly TravelConstraintResolver $constraints,
        private readonly ClarificationBuilder $clarifications,
        private readonly IntentConfidenceGate $confidenceGate,
    ) {}

    /**
     * @param  array<string, mixed>|null  $prior
     */
    public function parse(string $message, ?array $prior = null, ?Carbon $now = null): HybridParseResult
    {
        $now ??= Carbon::now();
        $prior = is_array($prior) ? $prior : [];
        $norm = $this->normalizer->normalize($message);
        $normalized = $norm['normalized'];
        $original = $norm['original'];
        $language = $norm['language'];
        $provenance = [];

        // Security: refuse executable payloads as travel commands
        if ($this->looksHostile($original)) {
            return $this->unknownClarify(
                'Please share a travel question in plain language (for example Lahore to Dubai on 18 Sep).',
                $language,
                $prior
            );
        }

        if ($this->isGreeting($normalized)) {
            return new HybridParseResult(
                intent: TravelIntent::fromArray(['intent' => 'unknown'], 'STRUCTURED_FALLBACK'),
                clarificationRequired: true,
                clarificationMessage: 'Assalamualaikum — I can help search flights or Groups, answer booking FAQs, or connect you to support.',
                language: $language,
                state: $prior,
                llmBypassed: true,
            );
        }

        if ($this->wantsHandoff($normalized, $original)) {
            return new HybridParseResult(
                intent: TravelIntent::fromArray(['intent' => 'handoff'], 'STRUCTURED_FALLBACK'),
                language: $language,
                provenance: ['intent' => 'EXPLICIT_USER'],
                state: $prior,
            );
        }

        if ($this->wantsKnowledge($normalized, $original)) {
            return new HybridParseResult(
                intent: TravelIntent::fromArray(['intent' => 'knowledge'], 'STRUCTURED_FALLBACK'),
                language: $language,
                provenance: ['intent' => 'EXPLICIT_USER'],
                state: $prior,
            );
        }

        // Transactional words → knowledge/handoff, never supplier mutation
        if (preg_match('/\b(book now|cancel (my )?booking|refund my|pay now|ticket (this|now)|void)\b/u', $normalized) === 1) {
            return new HybridParseResult(
                intent: TravelIntent::fromArray(['intent' => 'knowledge'], 'STRUCTURED_FALLBACK'),
                clarificationRequired: false,
                clarificationMessage: null,
                language: $language,
                provenance: ['intent' => 'EXPLICIT_USER'],
                state: $prior,
            );
        }

        $intentName = 'flight_search';
        if (preg_match('/\bgroup(s)?\b|group fare|\bگروپ\b/u', $normalized.$original) === 1) {
            $intentName = 'group_search';
        }
        $isRefine = $prior !== [] && (
            preg_match('/\b(one day later|aik din baad|only direct|under |wapas|return |Emirates|airline)\b/u', $normalized) === 1
            || preg_match('/ایک دن بعد|براہ راست/u', $original) === 1
        );
        if ($isRefine) {
            $intentName = $intentName === 'group_search' ? 'group_search' : 'flight_search';
            $provenance['intent'] = 'INHERITED_CONVERSATION_STATE';
        } else {
            $provenance['intent'] = 'EXPLICIT_USER';
        }

        [$origin, $destination, $oAmb, $dAmb, $oOpts, $dOpts] = $this->locations->extractRoute($normalized, $original);

        // Destination-led group phrases: "Dubai groups", "Jeddah group chahiye", "دبئی کے گروپ"
        if ($intentName === 'group_search' && $destination === null && $origin === null && ! $oAmb && ! $dAmb) {
            foreach (['dubai' => 'DXB', 'jeddah' => 'JED', 'lahore' => 'LHE', 'islamabad' => 'ISB', 'karachi' => 'KHI', 'abu dhabi' => 'AUH', 'sharjah' => 'SHJ', 'دبی' => 'DXB', 'دبئی' => 'DXB', 'جدہ' => 'JED', 'ابوظہبی' => 'AUH'] as $token => $code) {
                if (str_contains($normalized, $token) || str_contains($original, $token)) {
                    $destination = $code;
                    $provenance['destination'] = 'RESOLVED_MASTER_DATA';
                    break;
                }
            }
        }

        if ($origin === null && isset($prior['origin'])) {
            $origin = is_string($prior['origin']) ? $prior['origin'] : null;
            if ($origin) {
                $provenance['origin'] = 'INHERITED_CONVERSATION_STATE';
            }
        } elseif ($origin) {
            $provenance['origin'] = $provenance['origin'] ?? 'RESOLVED_MASTER_DATA';
        }
        if ($destination === null && isset($prior['destination'])) {
            $destination = is_string($prior['destination']) ? $prior['destination'] : null;
            if ($destination) {
                $provenance['destination'] = 'INHERITED_CONVERSATION_STATE';
            }
        } elseif ($destination) {
            $provenance['destination'] = $provenance['destination'] ?? 'RESOLVED_MASTER_DATA';
        }

        if ($oAmb) {
            $c = $this->clarifications->location('London', $oOpts);

            return $this->clarify($c['message'], $c['options'], $language, $prior, $provenance);
        }
        if ($dAmb) {
            $city = str_contains($normalized, 'new york') || str_contains($normalized, 'nyc') ? 'New York' : 'London';
            $c = $this->clarifications->location($city, $dOpts);

            return $this->clarify($c['message'], $c['options'], $language, $prior, $provenance);
        }

        $priorDepart = isset($prior['depart_date']) && is_string($prior['depart_date']) ? $prior['depart_date'] : null;
        $departInfo = $this->dates->resolveDepart($normalized, $original, $now, $priorDepart);
        $returnInfo = $this->dates->resolveReturn($normalized, $original, $now);

        if ($departInfo['clarify']) {
            return $this->clarify((string) $departInfo['clarify_message'], [], $language, $prior, $provenance);
        }
        if ($returnInfo['clarify']) {
            return $this->clarify((string) $returnInfo['clarify_message'], [], $language, $prior, $provenance);
        }

        $depart = $departInfo['date'] ?? $priorDepart;
        if ($departInfo['date'] && $departInfo['provenance']) {
            $provenance['depart_date'] = $departInfo['provenance'];
        } elseif ($depart && ! isset($provenance['depart_date'])) {
            $provenance['depart_date'] = 'INHERITED_CONVERSATION_STATE';
        }

        $returnDate = $returnInfo['date'] ?? ($prior['return_date'] ?? null);
        if (is_string($returnDate) && $returnInfo['provenance']) {
            $provenance['return_date'] = $returnInfo['provenance'];
        }

        $pax = $this->passengers->resolve($normalized, $original);
        $adults = $pax['adults'] ?? (isset($prior['adults']) ? (int) $prior['adults'] : 1);
        $children = $pax['children'] ?? (isset($prior['children']) ? (int) $prior['children'] : 0);
        $infants = $pax['infants'] ?? (isset($prior['infants']) ? (int) $prior['infants'] : 0);
        $provenance = array_merge($provenance, $pax['provenance']);

        $airline = $this->airlines->resolveFromMessage($normalized, $original);
        $airlineCode = $airline['code'] ?? ($prior['airline'] ?? null);
        if ($airline['code'] && $airline['provenance']) {
            $provenance['airline'] = $airline['provenance'];
        }

        $budget = $this->budget->resolve($normalized, $original);
        $budgetAmt = $budget['amount'] ?? ($prior['budget'] ?? null);
        if ($budget['amount'] !== null && $budget['provenance']) {
            $provenance['budget'] = $budget['provenance'];
        }

        $cons = $this->constraints->resolve($normalized, $original);
        $maxStops = $cons['max_stops'];
        if ($maxStops === null && array_key_exists('max_stops', $prior)) {
            $maxStops = $prior['max_stops'];
        }
        $provenance = array_merge($provenance, $cons['provenance']);
        $timePref = $cons['time_preference'] ?? ($prior['time_preference'] ?? null);
        $ranking = $cons['ranking'];

        // Confidence gate — never invent route for flight search
        $clarifyRequired = false;
        $clarifyMessage = null;
        $clarifyOptions = [];

        if ($intentName === 'flight_search') {
            if ($origin === null || $destination === null) {
                $c = $this->clarifications->route();
                $clarifyRequired = true;
                $clarifyMessage = $c['message'];
                $intentName = 'unknown';
            }
        } elseif ($intentName === 'group_search') {
            // Groups may be destination-led (e.g. "Dubai groups").
            if ($origin === null && $destination === null) {
                $c = $this->clarifications->route();
                $clarifyRequired = true;
                $clarifyMessage = 'Which city or destination groups should I show?';
                $intentName = 'unknown';
            }
        }

        // "2 plus baby" without classification
        if (preg_match('/\bplus\s+baby\b/u', $normalized) === 1 && $infants === 0 && $children === 0) {
            $c = $this->clarifications->passengers();
            $clarifyRequired = true;
            $clarifyMessage = $c['message'];
            $intentName = 'unknown';
        }

        if ($intentName === 'flight_search') {
            $gate = $this->confidenceGate->validateSearchable(
                $origin,
                $destination,
                is_string($depart) ? $depart : null,
                $clarifyRequired,
                $clarifyMessage,
                $provenance
            );
            if (! $gate['ok']) {
                $clarifyRequired = true;
                $clarifyMessage = $gate['message'] ?? $clarifyMessage;
                $intentName = 'unknown';
            }
        }

        if (
            is_string($depart)
            && is_string($returnDate)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $depart) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnDate) === 1
            && $returnDate < $depart
        ) {
            $clarifyRequired = true;
            $clarifyMessage = 'Your return date is before departure. Please confirm both dates.';
            $intentName = 'unknown';
        }

        $payload = [
            'intent' => $clarifyRequired ? 'unknown' : $intentName,
            'origin' => $origin,
            'destination' => $destination,
            'depart_date' => $depart,
            'return_date' => is_string($returnDate) ? $returnDate : null,
            'adults' => max(1, min(9, (int) $adults)),
            'children' => max(0, min(9, (int) $children)),
            'infants' => max(0, min(9, (int) $infants)),
            'airline' => is_string($airlineCode) ? $airlineCode : null,
            'max_stops' => $maxStops === null ? null : (int) $maxStops,
            'budget' => $budgetAmt === null ? null : (float) $budgetAmt,
            'time_preference' => is_string($timePref) ? $timePref : null,
            'currency' => 'PKR',
        ];

        // Final gate: flight searchable only with resolved O/D
        $intent = TravelIntent::fromArray($payload, 'STRUCTURED_FALLBACK');
        if ($intentName === 'flight_search' && $intent->isSearchable() === false && ! $clarifyRequired) {
            $c = $this->clarifications->route();
            $clarifyRequired = true;
            $clarifyMessage = $c['message'];
            $intent = TravelIntent::fromArray(array_merge($payload, ['intent' => 'unknown']), 'STRUCTURED_FALLBACK');
        }
        // Group search may proceed with destination only.
        if ($intentName === 'group_search' && ! $clarifyRequired) {
            $intent = TravelIntent::fromArray(array_merge($payload, ['intent' => 'group_search']), 'STRUCTURED_FALLBACK');
        }

        $state = array_merge($prior, $intent->toArray());
        if ($ranking) {
            $state['ranking_preference'] = $ranking;
        }

        return new HybridParseResult(
            intent: $intent,
            clarificationRequired: $clarifyRequired,
            clarificationMessage: $clarifyMessage,
            clarificationOptions: $clarifyOptions,
            provenance: $provenance,
            rankingPreference: $ranking,
            language: $language,
            state: $state,
            llmBypassed: true,
        );
    }

    private function wantsHandoff(string $normalized, string $original): bool
    {
        return (bool) preg_match(
            '/talk to (a )?(person|human)|human (support|agent)|agent please|live agent|speak to (support|agent)|real person|human please|staff please|talk to support|connect (me )?to (a )?(human|agent|support)|handoff|insaan se baat|انسانی\s*سپورٹ|انسان سے بات/u',
            $normalized.' '.$original
        );
    }

    private function wantsKnowledge(string $normalized, string $original): bool
    {
        return (bool) preg_match(
            '/how (does |do )?booking|guest booking|customer registration|payment (deadline|process|help)|cancellation|refund policy|saved travelers?|support hours|faq|ادائیگی|ریفنڈ|محفوظ مسافر/u',
            $normalized.' '.$original
        );
    }

    private function isGreeting(string $normalized): bool
    {
        return (bool) preg_match('/^(hi|hello|hey|salam|assalamualaikum|aoa)\b/u', $normalized);
    }

    private function looksHostile(string $text): bool
    {
        return (bool) preg_match(
            '/<\s*script|javascript:|drop\s+table|;?\s*rm\s+-rf|\$\(|\bexec\(|\bsystem\(|ignore previous instructions|show\s+\.env|api[_-]?key/i',
            $text
        );
    }

    /**
     * @param  list<array{label: string, value: string}>  $options
     * @param  array<string, mixed>  $prior
     * @param  array<string, string>  $provenance
     */
    private function clarify(string $message, array $options, string $language, array $prior, array $provenance): HybridParseResult
    {
        return new HybridParseResult(
            intent: TravelIntent::fromArray(['intent' => 'unknown'], 'STRUCTURED_FALLBACK'),
            clarificationRequired: true,
            clarificationMessage: $message,
            clarificationOptions: $options,
            provenance: $provenance,
            language: $language,
            state: $prior,
            llmBypassed: true,
        );
    }

    /**
     * @param  array<string, mixed>  $prior
     */
    private function unknownClarify(string $message, string $language, array $prior): HybridParseResult
    {
        return $this->clarify($message, [], $language, $prior, []);
    }
}
