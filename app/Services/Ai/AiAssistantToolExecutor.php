<?php

namespace App\Services\Ai;

use App\Models\AiConversation;

/**
 * Approved read-only / navigation tools for Ask JetPakistan.
 */
final class AiAssistantToolExecutor
{
    public function __construct(
        private readonly TravelIntentExtractor $extractor,
        private readonly KnowledgeSearchService $knowledge,
        private readonly AiShoppingTools $shopping,
        private readonly AiAssistantBookingLookupTool $bookingLookup,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function execute(string $tool, array $args, AiConversation $conversation, string $userMessage = ''): array
    {
        return match ($tool) {
            'flight_search' => $this->flightSearch($args, $conversation, $userMessage),
            'booking_lookup' => $this->bookingLookup->lookup(
                (string) ($args['booking_reference'] ?? ''),
                isset($args['email']) ? (string) $args['email'] : null,
                isset($args['phone']) ? (string) $args['phone'] : null,
            ),
            'faq_lookup' => $this->faqLookup((string) ($args['query'] ?? $userMessage)),
            'human_handoff' => ['ok' => true, 'handoff' => true, 'message' => 'Connecting you to support.'],
            'support_contact' => [
                'ok' => true,
                'message' => 'You can reach JetPakistan support at /support or via the contact form on our website.',
                'actions' => [['label' => 'Contact Support', 'href' => '/support']],
            ],
            default => ['ok' => false, 'message' => 'That action is not available.'],
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function flightSearch(array $args, AiConversation $conversation, string $userMessage): array
    {
        $text = trim((string) ($args['message'] ?? $userMessage));
        $prior = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $hybrid = $this->extractor->extractHybrid($text, $prior);
        $intent = $hybrid->intent;
        $conversation->shopping_state = $this->extractor->patchState($prior, $intent);
        $conversation->save();

        if ($intent->origin && $intent->destination) {
            $result = $this->shopping->searchFlights($intent);

            return [
                'ok' => true,
                'message' => 'I prepared a flight search for '.$intent->origin.' → '.$intent->destination.'. '.$result['freshness_note'],
                'recommendations' => $result['recommendations'],
                'meta' => $result['meta'],
            ];
        }

        return [
            'ok' => true,
            'message' => $hybrid->clarificationMessage ?: 'Please share origin, destination, and travel date.',
            'clarify' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function faqLookup(string $query): array
    {
        $hits = $this->knowledge->search($query, 3);
        if ($hits === []) {
            return [
                'ok' => true,
                'message' => 'I could not find an approved answer for that in JetPakistan help.',
                'knowledge' => [],
            ];
        }

        $parts = [];
        foreach ($hits as $hit) {
            $parts[] = '**'.$hit['title']."**\n".$hit['excerpt'];
        }

        return [
            'ok' => true,
            'message' => "Here is what I found in JetPakistan help:\n\n".implode("\n\n", $parts),
            'knowledge' => $hits,
        ];
    }
}
