<?php

namespace App\Services\Ai;

use App\Services\Ai\Hybrid\ServerTravelSignals;

/**
 * Conversation-level intent signals for HELP-FIRST lead capture and open-domain routing.
 * Advisory for conversation flow — server policy remains authoritative for tools/writes.
 */
final class ConversationIntentRouter
{
    public function __construct(
        private readonly AiCommercialIntentClassifier $commercial,
        private readonly ServerTravelSignals $travelSignals,
    ) {}

    public function shouldOverrideLeadCapture(string $message): bool
    {
        $message = trim($message);
        if ($message === '') {
            return false;
        }

        if ($this->hasStrongActionableIntent($message)) {
            return true;
        }

        if ($this->isJetPakistanKnowledgeQuestion($message)) {
            return true;
        }

        if ($this->classifyOpenDomain($message) !== null) {
            return true;
        }

        // Informational / how-what questions that are not bare name answers.
        if ($this->commercial->isInformationalPublic($message) && ! $this->looksLikeBareName($message)) {
            return true;
        }

        return false;
    }

    public function hasStrongActionableIntent(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        if ($this->commercial->isBookingHelpIntent($lower)) {
            return true;
        }

        if (preg_match('/\b(look\s*up|lookup|check)\b.*\b(booking|pnr|reservation)\b|\bbooking\s+reference\b|\bpnr\b/u', $lower) === 1) {
            return true;
        }

        // "look up ABC123" / "check my PNR XYZ" style without explicit booking word.
        if (preg_match('/\b(look\s*up|lookup|check)\b/u', $lower) === 1
            && preg_match('/\b[A-Z0-9]{5,12}\b/i', $message) === 1) {
            return true;
        }

        // Route-like travel requests — master-data aliases via shared server signal (dubay/lahor/…).
        $route = $this->travelSignals->explicitTravelRoute($message);
        if ($route['explicit']) {
            return true;
        }
        if (
            preg_match('/\b(lahore|lahor|karachi|islamabad|dubai|dubay|jeddah|london|doha|lhe|dxb|khi|isb|jed)\b/u', $lower) === 1
            && preg_match('/\b(to|from|se|flight|flights|ticket|tomorrow|today|adults?|passengers?|people|wapis|wapas)\b/u', $lower) === 1
        ) {
            return true;
        }

        if (preg_match('/\b([a-z]{3})\s*(?:to|se|→|->)\s*([a-z]{3})\b/u', $lower) === 1) {
            return true;
        }

        if (preg_match('/\bgroup(s)?\b.*\b(travel|ticket|fare|dubai|jeddah)\b|\bgroup\s+ticket/u', $lower) === 1) {
            return true;
        }

        // Cabin / passenger / trip-shape refinements while shopping.
        if (preg_match(
            '/\b(business|economy|premium\s*economy|first)\s*class\b|\bcabin\b|\b(make (it|that) )?(business|economy)\b|\b\d+\s*adults?\b|\bcome back from\b|\bchange (it|that) to\b|\buse \w+ instead\b|\bis (it|that) (business|return|economy)\b|\bwhat date\b/u',
            $lower
        ) === 1) {
            return true;
        }

        // Date-only answers completing an in-progress flight search (do not treat as lead name).
        if (preg_match('/\b\d{4}-\d{2}-\d{2}\b/u', $lower) === 1) {
            return true;
        }
        if (preg_match('/\b(\d{1,2})(st|nd|rd|th)?\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t|tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\b/u', $lower) === 1) {
            return true;
        }
        if (preg_match('/\b(today|tomorrow|next\s+friday|kal|parso)\b/u', $lower) === 1) {
            return true;
        }

        return false;
    }

    public function isJetPakistanKnowledgeQuestion(string $message): bool
    {
        $lower = mb_strtolower(trim($message));

        return preg_match(
            '/what is jetpakistan|jetpakistan (is|about)|how (can|do) i contact|contact (jetpakistan )?support|baggage|refund policy|how does (a )?refund|payment (help|process)|guest booking|group ticket|support hours/u',
            $lower
        ) === 1;
    }

    /**
     * @return null|'GENERAL_KNOWLEDGE'|'OUT_OF_DOMAIN_SAFE'|'CURRENT_UNVERIFIED'|'CASUAL_CONVERSATION'|'HIGH_RISK'
     */
    public function classifyOpenDomain(string $message): ?string
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '' || $this->commercial->isSimpleGreeting($lower)) {
            return null;
        }

        // Safety / live-data gates before travel-intent short-circuit (e.g. "weather today in London").
        if (preg_match('/\b(diagnos|prescription|lawsuit|invest(ment)? advice|how to make a bomb|suicide|self[- ]harm)\b/u', $lower) === 1) {
            return 'HIGH_RISK';
        }

        if (preg_match(
            '/\b(stock price|share price|breaking news|live score|live match|who won|who is winning)\b'.
            '|\b(today\'?s|current)\s+weather\b'.
            '|\bweather\s+(today|now|tomorrow|in|situation|right\s+now)\b'.
            '|\bweather situation\b'.
            '|\b(right\s+now|currently).{0,40}\bweather\b'.
            '|\btemperature in\b|\bcurrent (president|prime minister)\b'.
            '|\b(bitcoin|btc|crypto).{0,24}\b(price|today|right\s+now|now)\b'.
            '|\b(price of|how much is)\s+(bitcoin|btc|aapl|apple|ethereum|eth)\b'.
            '|\b(aapl|apple).{0,24}\b(stock|share)?\s*(price|right\s+now|now)\b'.
            '|\bcurrent stock price\b'.
            '|\b(latest|breaking|today\'?s)\s+news\b'.
            '|\bnews\s+(today|right\s+now|now)\b'.
            '|\bhappened in the news\b'.
            '|\bwho won the match\b'.
            '|\blive\s+score\b'.
            '|\bdubai\s+weather\b'.
            '|\bweather\s+in\s+\w+/u',
            $lower
        ) === 1) {
            return 'CURRENT_UNVERIFIED';
        }

        if ($this->isJetPakistanKnowledgeQuestion($lower) || $this->hasStrongActionableIntent($lower)) {
            return null;
        }

        // JetPakistan support/contact/FAQ phrasing — leave for knowledge pipeline.
        if (preg_match('/\b(jetpakistan|support|contact|refund|baggage|payment|booking help|faq)\b/u', $lower) === 1
            && preg_match('/^(how|what|where|when|can)\b/u', $lower) === 1) {
            return null;
        }

        if (preg_match('/\b(tell me a joke|i(\'m| am) bored|how are you|long day|what\'?s up)\b/u', $lower) === 1) {
            return 'CASUAL_CONVERSATION';
        }

        if (preg_match('/\b(buy a jet|private jet|order pizza|buy a car|cryptocurrency wallet)\b/u', $lower) === 1) {
            return 'OUT_OF_DOMAIN_SAFE';
        }

        if (preg_match('/\be\s*=\s*mc\s*\^?\s*2\b|\bmass[- ]energy\b|\bcapital of\b|\bwhat is (gravity|photosynthesis|dna|pi)\b/u', $lower) === 1) {
            return 'GENERAL_KNOWLEDGE';
        }

        // Harmless what/where/who/why/how/explain educational questions without travel markers.
        if (
            preg_match('/^(what|where|who|why|how|explain|describe|define)\b/u', $lower) === 1
            && ! preg_match('/\b(flight|ticket|fare|booking|pnr|lahore|dubai|travel|umrah)\b/u', $lower)
            && ! $this->isJetPakistanKnowledgeQuestion($lower)
        ) {
            return 'GENERAL_KNOWLEDGE';
        }

        // Soft human/support asks must not be treated as GENERAL_KNOWLEDGE (planner/handoff path).
        if (preg_match(
            '/\b(someone on your (team|staff)|talk to (a )?(person|human|support|agent)|'.
            'speak to (a )?(person|human|support|agent)|live agent|real person|handoff|'.
            'customer service|get me (a )?(human|agent))\b/u',
            $lower
        ) === 1) {
            return null;
        }

        // Terse educational topic asks (e.g. "Undefined behavior in C?", "Null hypothesis in statistics?").
        if (
            str_ends_with($lower, '?')
            && ! preg_match('/\b(flight|ticket|fare|booking|pnr|lahore|dubai|travel|umrah|jetpakistan)\b/u', $lower)
            && ! $this->isJetPakistanKnowledgeQuestion($lower)
            && ! $this->hasStrongActionableIntent($lower)
        ) {
            return 'GENERAL_KNOWLEDGE';
        }

        return null;
    }

    /**
     * Topic hint for CURRENT_UNVERIFIED limitation wording.
     *
     * @return 'weather'|'news'|'market'|'sports'|'generic'
     */
    public function classifyCurrentTopic(string $message): string
    {
        $lower = mb_strtolower(trim($message));

        if (preg_match('/\b(weather|temperature|forecast|raining|humidity)\b/u', $lower) === 1) {
            return 'weather';
        }
        if (preg_match('/\b(news|headline|breaking)\b/u', $lower) === 1) {
            return 'news';
        }
        if (preg_match('/\b(stock|share price|bitcoin|btc|crypto|aapl|market|ethereum|eth|price right now|how much is)\b/u', $lower) === 1) {
            return 'market';
        }
        if (preg_match('/\b(score|match|who won|sports?|game)\b/u', $lower) === 1) {
            return 'sports';
        }

        return 'generic';
    }

    public function looksLikeBareName(string $message): bool
    {
        $name = trim($message);

        return mb_strlen($name) >= 2
            && mb_strlen($name) <= 80
            && preg_match('/^[\p{L}\p{M}\s\'\-\.]+$/u', $name) === 1
            && preg_match('/\b(to|from|flight|need|email|phone|tomorrow|help|what|where|how)\b/ui', $name) !== 1
            && str_word_count($name) <= 4;
    }
}
