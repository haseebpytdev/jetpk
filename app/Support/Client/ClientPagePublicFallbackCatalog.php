<?php

namespace App\Support\Client;

/**
 * JetPK admin/bootstrap field catalog for managed pages.
 *
 * Public runtime must not use this as a content source — only bootstrap import and admin form seeding.
 */
final class ClientPagePublicFallbackCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function contentFor(string $pageKey): array
    {
        return ClientPageBootstrapTemplate::contentFor($pageKey);
    }

    /**
     * @return list<string>
     */
    public static function fieldPathsFor(string $pageKey): array
    {
        $paths = [];
        foreach (ClientPageSectionSchema::sectionsFor($pageKey) as $section) {
            $prefix = $section['key'];
            foreach ($section['fields'] as $field) {
                if ($field === 'items' || $field === 'cards' || $field === 'links') {
                    $paths[] = $prefix.'.'.$field;

                    continue;
                }
                $paths[] = $prefix.'.'.$field;
            }
        }

        $content = self::contentFor($pageKey);
        foreach (self::flattenPaths($content) as $path) {
            if (! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function flattenPaths(array $data, string $prefix = ''): array
    {
        $paths = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $paths[] = $path;

                    continue;
                }
                $paths = array_merge($paths, self::flattenPaths($value, $path));

                continue;
            }
            $paths[] = $path;
        }

        return $paths;
    }
}
