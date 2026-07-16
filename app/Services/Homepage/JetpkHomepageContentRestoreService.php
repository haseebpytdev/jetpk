<?php

namespace App\Services\Homepage;

use App\Services\Client\ClientPageContentResolver;

/**
 * Section-scoped homepage CMS repair for blanked JetPK Page Settings fields.
 */
final class JetpkHomepageContentRestoreService
{
    public const CANONICAL_SUPPORT_EMAIL = 'ota@jetpakistan.pk';

    public const CANONICAL_SUPPORT_PHONE = '0311 1222427';

    public const CANONICAL_CALL_URL = 'tel:+923111222427';

    public const CANONICAL_CHAT_URL = '/support';

    /** @var list<string> */
    private const PRESERVE_TOP_LEVEL = [
        'hero',
        'trust_chips',
        'groups',
        'why_book',
        '_fare_cache',
        '_media_removed',
    ];

    /** @var list<array{icon: string, title: string, text: string, enabled: string}> */
    private const AUTHORITATIVE_TRUST_CARD_DEFAULTS = [
        ['icon' => 'check-square', 'title' => 'Transparent PKR pricing', 'text' => 'No FX shock between search and checkout.', 'enabled' => '1'],
        ['icon' => 'check-square', 'title' => 'Licensed operations', 'text' => 'IATA accredited and PCAA licensed.', 'enabled' => '1'],
        ['icon' => 'check-square', 'title' => 'Human support', 'text' => 'Pakistan-based desk in Urdu and English.', 'enabled' => '1'],
    ];

    public function __construct(
        private readonly ClientPageContentResolver $contentResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $current
     * @return list<array{path: string, current: mixed, proposed: mixed, source: string, action: string, risk: string}>
     */
    public function buildChangePlan(array $current): array
    {
        $changes = [];
        $defaults = $this->contentResolver->defaultHomeContent();

        $this->planFeatureBoard($changes, $current, $defaults);
        $this->planTrust($changes, $current);
        $this->planGroupCards($changes, $current, $defaults);
        $this->planFeaturedDeals($changes, $current);
        $this->planRoutesHeadings($changes, $current, $defaults);
        $this->planDestinationsHeadings($changes, $current, $defaults);
        $this->planSupportCta($changes, $current, $defaults);

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  list<array{path: string, proposed: mixed}>  $changes
     * @return array<string, mixed>
     */
    public function applyChangePlan(array $current, array $changes): array
    {
        $repaired = $current;
        foreach ($changes as $change) {
            if (($change['action'] ?? '') !== 'CHANGED') {
                continue;
            }
            data_set($repaired, $change['path'], $change['proposed']);
        }

        return $repaired;
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $defaults
     */
    private function planFeatureBoard(array &$changes, array $current, array $defaults): void
    {
        $items = data_get($current, 'feature_board.items', []);
        if (! is_array($items) || ! $this->featureBoardIsFullyBlank($items)) {
            return;
        }

        $defaultItems = data_get($defaults, 'feature_board.items', []);
        if (! is_array($defaultItems) || $defaultItems === []) {
            return;
        }

        $this->propose($changes, 'feature_board.items', $items, $defaultItems, 'ClientPageContentResolver::defaultHomeContent()');
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array<string, mixed>  $current
     */
    private function planTrust(array &$changes, array $current): void
    {
        $scalarDefaults = [
            'trust.eyebrow' => 'Why travellers stay',
            'trust.title' => 'Booking that respects your time and money.',
            'trust.subtitle' => 'No hidden markups, no chasing call centres. Every part of the journey is built to be clear and quick.',
        ];

        foreach ($scalarDefaults as $path => $default) {
            $currentValue = data_get($current, $path);
            if (! $this->isBlank($currentValue)) {
                $this->propose($changes, $path, $currentValue, $currentValue, 'JetpkHomepageSectionData / trust.blade.php', 'PRESERVED');
                continue;
            }
            $this->propose($changes, $path, $currentValue, $default, 'themes/frontend/jetpakistan/sections/trust.blade.php');
        }

        $cards = data_get($current, 'trust.cards', []);
        if (! is_array($cards) || ! $this->trustCardsAreFullyBlank($cards)) {
            return;
        }

        $authoritativeDefaults = self::AUTHORITATIVE_TRUST_CARD_DEFAULTS;
        if ($authoritativeDefaults === []) {
            $this->proposeManualContentGap($changes, 'trust.cards', $cards);

            return;
        }

        $proposedCards = $this->mergeTrustCardsPreservingStructure($cards, $authoritativeDefaults);
        $this->propose(
            $changes,
            'trust.cards',
            $cards,
            $proposedCards,
            'JetpkHomepageSectionData::authoritativeTrustCardDefaults()',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $defaults
     */
    private function planGroupCards(array &$changes, array $current, array $defaults): void
    {
        foreach (['eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url'] as $field) {
            $path = 'group_cards.'.$field;
            $currentValue = data_get($current, $path);
            $defaultValue = data_get($defaults, $path, '');
            if ($field === 'subtitle' || $field === 'cta_text' || $field === 'cta_url') {
                $defaultValue = $defaultValue !== '' ? $defaultValue : '';
            }
            if ($this->isBlank($currentValue) && ! $this->isBlank($defaultValue)) {
                $this->propose($changes, $path, $currentValue, $defaultValue, 'ClientPageContentResolver::defaultHomeContent()');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array<string, mixed>  $current
     */
    private function planFeaturedDeals(array &$changes, array $current): void
    {
        $defaults = [
            'featured_deals.eyebrow' => 'Live fares',
            'featured_deals.title' => 'Featured deals, updated hourly.',
            'featured_deals.subtitle' => 'Real round-trip prices pulled live from our airline partners.',
            'featured_deals.cta_text' => '',
            'featured_deals.cta_url' => '',
        ];

        foreach ($defaults as $path => $default) {
            $currentValue = data_get($current, $path);
            if ($this->isBlank($currentValue) && ! $this->isBlank($default)) {
                $this->propose($changes, $path, $currentValue, $default, 'themes/frontend/jetpakistan/sections/fares.blade.php');
            } elseif ($this->isBlank($currentValue) && $this->isBlank($default)) {
                $this->propose($changes, $path, $currentValue, $currentValue, 'themes/frontend/jetpakistan/sections/fares.blade.php', 'PRESERVED');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $defaults
     */
    private function planRoutesHeadings(array &$changes, array $current, array $defaults): void
    {
        foreach (['eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url'] as $field) {
            $path = 'routes.'.$field;
            $currentValue = data_get($current, $path);
            $defaultValue = data_get($defaults, $path, '');
            if ($this->isBlank($currentValue) && ! $this->isBlank($defaultValue)) {
                $this->propose($changes, $path, $currentValue, $defaultValue, 'ClientPageContentResolver::defaultHomeContent()');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $defaults
     */
    private function planDestinationsHeadings(array &$changes, array $current, array $defaults): void
    {
        foreach (['eyebrow', 'title', 'subtitle', 'cta_text', 'cta_url'] as $field) {
            $path = 'destinations.'.$field;
            $currentValue = data_get($current, $path);
            $defaultValue = data_get($defaults, $path, '');
            if ($this->isBlank($currentValue) && ! $this->isBlank($defaultValue)) {
                $this->propose($changes, $path, $currentValue, $defaultValue, 'ClientPageContentResolver::defaultHomeContent()');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $defaults
     */
    private function planSupportCta(array &$changes, array $current, array $defaults): void
    {
        $scalarDefaults = [
            'support_cta.eyebrow' => data_get($defaults, 'support_cta.eyebrow', 'We pick up'),
            'support_cta.title' => data_get($defaults, 'support_cta.title', 'Stuck mid-booking? Talk to a human.'),
            'support_cta.subtitle' => data_get($defaults, 'support_cta.subtitle', ''),
            'support_cta.call_label' => data_get($defaults, 'support_cta.call_label', 'Call support'),
            'support_cta.chat_label' => data_get($defaults, 'support_cta.chat_label', 'Live chat'),
            'support_cta.chat_url' => self::CANONICAL_CHAT_URL,
            'support_cta.cta_link' => self::CANONICAL_CHAT_URL,
            'support_cta.phone_value' => self::CANONICAL_SUPPORT_PHONE,
            'support_cta.call_url' => self::CANONICAL_CALL_URL,
        ];

        foreach ($scalarDefaults as $path => $default) {
            $currentValue = data_get($current, $path);
            if ($this->isBlank($currentValue) && ! $this->isBlank($default)) {
                $this->propose($changes, $path, $currentValue, $default, 'ClientPageContentResolver::defaultHomeContent()');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    private function propose(
        array &$changes,
        string $path,
        mixed $current,
        mixed $proposed,
        string $source,
        string $action = 'CHANGED',
    ): void {
        if ($action === 'CHANGED' && $path === 'trust.cards' && is_array($current) && is_array($proposed)) {
            if ($this->trustCardsSemanticallyEqual($current, $proposed)) {
                $action = 'PRESERVED';
                $proposed = $current;
            }
        } elseif ($action === 'CHANGED' && $current === $proposed) {
            $action = 'PRESERVED';
        }

        $risk = 'low';
        if (str_starts_with($path, 'routes.items') || str_starts_with($path, 'destinations.items')) {
            $risk = 'high';
        }
        if (in_array(explode('.', $path)[0], self::PRESERVE_TOP_LEVEL, true)) {
            $risk = 'blocked';
            $action = 'PRESERVED';
            $proposed = $current;
        }

        $changes[] = [
            'path' => $path,
            'current' => $current,
            'proposed' => $proposed,
            'source' => $source,
            'action' => $action,
            'risk' => $risk,
        ];
    }

    /**
     * @param  list<mixed>  $items
     */
    private function featureBoardIsFullyBlank(array $items): bool
    {
        if ($items === []) {
            return true;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (! $this->isBlank($item['value'] ?? '') || ! $this->isBlank($item['label'] ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $cards
     */
    private function trustCardsAreFullyBlank(array $cards): bool
    {
        if ($cards === []) {
            return true;
        }

        foreach ($cards as $card) {
            if (! is_array($card)) {
                continue;
            }
            if (
                ! $this->isBlank($card['icon'] ?? '')
                || ! $this->isBlank($card['title'] ?? '')
                || ! $this->isBlank($card['text'] ?? '')
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $cards
     * @param  list<array<string, mixed>>  $defaults
     * @return list<array<string, mixed>>
     */
    private function mergeTrustCardsPreservingStructure(array $cards, array $defaults): array
    {
        $normalizedCards = array_values(array_filter($cards, static fn ($card): bool => is_array($card)));
        $cardCount = $normalizedCards !== [] ? count($normalizedCards) : count($defaults);
        $merged = [];

        for ($index = 0; $index < $cardCount; $index++) {
            $current = is_array($normalizedCards[$index] ?? null) ? $normalizedCards[$index] : [];
            $default = is_array($defaults[$index] ?? null) ? $defaults[$index] : [];
            $next = $current;

            foreach (['icon', 'title', 'text'] as $field) {
                if ($this->isBlank($current[$field] ?? '') && ! $this->isBlank($default[$field] ?? '')) {
                    $next[$field] = $default[$field];
                }
            }

            if (! array_key_exists('enabled', $next)) {
                $next['enabled'] = $default['enabled'] ?? '1';
            }

            $merged[] = $next;
        }

        return $merged;
    }

    /**
     * @param  list<mixed>  $left
     * @param  list<mixed>  $right
     */
    private function trustCardsSemanticallyEqual(array $left, array $right): bool
    {
        $leftCards = array_values(array_filter($left, static fn ($card): bool => is_array($card)));
        $rightCards = array_values(array_filter($right, static fn ($card): bool => is_array($card)));

        if (count($leftCards) !== count($rightCards)) {
            return false;
        }

        foreach ($leftCards as $index => $leftCard) {
            $rightCard = $rightCards[$index];

            foreach (['icon', 'title', 'text'] as $field) {
                if (trim((string) ($leftCard[$field] ?? '')) !== trim((string) ($rightCard[$field] ?? ''))) {
                    return false;
                }
            }

            if ((string) ($leftCard['enabled'] ?? '1') !== (string) ($rightCard['enabled'] ?? '1')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    private function proposeManualContentGap(array &$changes, string $path, mixed $current): void
    {
        $changes[] = [
            'path' => $path,
            'current' => $current,
            'proposed' => $current,
            'source' => 'MANUAL CONTENT GAP',
            'action' => 'MANUAL CONTENT GAP',
            'risk' => 'blocked',
        ];
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_array($value) && $value === [];
    }
}
