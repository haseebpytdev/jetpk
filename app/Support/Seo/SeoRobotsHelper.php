<?php

namespace App\Support\Seo;

/**
 * Parses and normalizes robots meta directives for SEO Management.
 */
final class SeoRobotsHelper
{
    /**
     * @return array{index: bool, follow: bool, raw: string}
     */
    public static function parse(?string $robots): array
    {
        $raw = strtolower(trim(preg_replace('/\s+/', '', (string) $robots) ?? ''));
        if ($raw === '') {
            $raw = 'index,follow';
        }

        $parts = array_filter(explode(',', $raw));
        $index = ! in_array('noindex', $parts, true);
        $follow = ! in_array('nofollow', $parts, true);

        return [
            'index' => $index,
            'follow' => $follow,
            'raw' => $index && $follow ? 'index,follow' : ($index ? 'index,nofollow' : ($follow ? 'noindex,follow' : 'noindex,nofollow')),
        ];
    }

    public static function compose(bool $index, bool $follow): string
    {
        return self::parse(($index ? 'index' : 'noindex').','.($follow ? 'follow' : 'nofollow'))['raw'];
    }

    public static function isIndexable(?string $robots): bool
    {
        return self::parse($robots)['index'];
    }
}
