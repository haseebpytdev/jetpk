<?php

namespace App\Support\Client\Homepage;

/**
 * Resolves JetPK homepage section render order from published CMS content.
 */
final class HomepageSectionOrderResolver
{
    /**
     * @param  array<string, mixed>  $content
     * @return list<array{key: string, view: string, order: int, default_order: int}>
     */
    public function orderedSections(array $content): array
    {
        return collect(HomepageCanonicalSchema::sections())
            ->reject(fn (array $section): bool => $section['key'] === 'hero')
            ->map(function (array $section) use ($content): array {
                $defaultOrder = (int) $section['render_order'];
                $configured = data_get($content, $section['key'].'.order');
                $order = ($configured !== null && $configured !== '')
                    ? (int) $configured
                    : $defaultOrder;

                return [
                    'key' => $section['key'],
                    'view' => str_replace(['sections/', '.blade.php'], '', $section['blade_view']),
                    'order' => $order,
                    'default_order' => $defaultOrder,
                ];
            })
            ->sort(function (array $a, array $b): int {
                return $a['order'] <=> $b['order'] ?: $a['default_order'] <=> $b['default_order'];
            })
            ->values()
            ->all();
    }
}
