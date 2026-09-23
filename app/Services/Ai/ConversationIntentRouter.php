<?php

namespace App\Services\Ai;

/**
 * Conversation-level intent signals for HELP-FIRST lead capture and open-domain routing.
 * Advisory for conversation flow — server policy remains authoritative for tools/writes.
 */
final class ConversationIntentRouter
{
    public function __construct(
        private readonly AiCommercialIntentClassifier $commercial,
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

        // Route-like travel requests (cities/airports + motion words or dates/pax).
        if (
            preg_match('/\b(lahore|karachi|islamabad|dubai|jeddah|london|doha|lhe|dxb|khi|isb|jed)\b/u', $lower) === 1
            && preg_match('/\b(to|from|se|flight|flights|ticket|tomorrow|today|adults?|passengers?|people)\b/u', $lower) === 1
        ) {
            return true;
        }

        if (preg_match('/\b([a-z]{3})\s*(?:to|se|→|->)\s*([a-z]{3})\b/u', $lower) === 1) {
            return true;
        }

        if (preg_match('/\bgroup(s)?\b.*\b(travel|ticket|fare|dubai|jeddah)\b|\bgroup\s+ticket/u', $lower) === 1) {
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

        if ($this->isJetPakistanKnowledgeQuestion($lower) || $this->hasStrongActionableIntent($lower)) {
            return null;
        }

        // JetPakistan support/contact/FAQ phrasing — leave for knowledge pipeline.
        if (preg_match('/\b(jetpakistan|support|contact|refund|baggage|payment|booking help|faq)\b/u', $lower) === 1
            && preg_match('/^(how|what|where|when|can)\b/u', $lower) === 1) {
            return null;
        }

        if (preg_match('/\b(diagnos|prescription|lawsuit|invest(ment)? advice|how to make a bomb|suicide|self[- ]harm)\b/u', $lower) === 1) {
            return 'HIGH_RISK';
        }

        if (preg_match('/\b(stock price|share price|breaking news|live score|who won|weather (today|now|tomorrow)|temperature in|current (president|prime minister))\b/u', $lower) === 1) {
            return 'CURRENT_UNVERIFIED';
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

        // Harmless what/where/who educational questions without travel markers.
        if (
            preg_match('/^(what|where|who|why|how)\b/u', $lower) === 1
            && ! preg_match('/\b(flight|ticket|fare|booking|pnr|lahore|dubai|travel|umrah)\b/u', $lower)
            && ! $this->isJetPakistanKnowledgeQuestion($lower)
        ) {
            return 'GENERAL_KNOWLEDGE';
        }

        return null;
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
