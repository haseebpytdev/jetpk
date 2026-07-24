<?php

namespace App\Support\Client\Homepage;

use App\Support\Client\Homepage\HomepageCanonicalSchema as Schema;
use Illuminate\Support\Arr;

/**
 * Backward-compatible normalizer for JetPK homepage content_json payloads.
 *
 * JETPK-HOMEPAGE-CMS Task 7. Applies HomepageCanonicalSchema::migrationAliases()
 * to migrate stale keys (groups.* -> group_cards.*, support_cta legacy names)
 * to their canonical location, strips fields the schema has marked
 * deprecated_remove, and leaves everything else — including keys this
 * schema doesn't know about at all — completely untouched.
 *
 * Hard rules this class follows (see docs/JETPK_HOMEPAGE_DATA_MIGRATION_PLAN.md
 * for the reasoning behind each one):
 *   1. Never invents a value. A migration only happens when the OLD key is
 *      actually present (Arr::has, not just non-empty) AND the NEW key is
 *      NOT already present. If the new key already has a value — even an
 *      empty string, false, or null — that value wins and the old key is
 *      just dropped (it's redundant, not conflicting-and-unresolved).
 *   2. Presence-aware: an old key present with value `''`, `false`, `null`,
 *      or `[]` migrates that exact value, not a "cleaned up" version of it.
 *      This mirrors ClientPageContentResolver::resolveField()'s own
 *      presence-aware contract exactly.
 *   3. Never reintroduces a fallback. If neither the old nor the new key is
 *      present, nothing is written — an intentionally-cleared field stays
 *      cleared, or an untouched field stays untouched, either way silent.
 *   4. Unknown keys (not in the canonical schema, not an alias, not marked
 *      deprecated_remove) pass through completely unmodified. This class
 *      only ever touches keys it has an explicit instruction for.
 *   5. Idempotent. Running normalize() twice on its own output produces the
 *      same result the second time (old keys are gone after the first pass,
 *      so the second pass has nothing left to migrate).
 *   6. No DB access, no tenant lookups — pure function of one content array
 *      in, one content array + one report out. Tenant isolation is the
 *      caller's responsibility (it already is, in ClientPageContentResolver),
 *      not this class's.
 */
final class HomepageContentNormalizer
{
    /**
     * Bump this if migrationAliases() or the deprecated-field list ever
     * changes shape in a way that needs a fresh backfill pass. Stored in the
     * report only — this class does not persist anything itself.
     */
    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<string, mixed>  $content
     * @return array{content: array<string, mixed>, report: array<string, mixed>}
     */
    public function normalize(array $content): array
    {
        $report = [
            'schema_version' => self::SCHEMA_VERSION,
            'aliases_migrated' => [],   // old => new, value moved
            'aliases_dropped' => [],    // old => new, new already had a value, old just discarded
            'aliases_absent' => [],     // old => new, neither present, nothing to do
            'deprecated_stripped' => [], // path => value that was removed
            'unknown_top_level_keys' => [],
        ];

        $working = $content;

        foreach (Schema::migrationAliases() as $old => $new) {
            $oldPresent = Arr::has($working, $old);
            $newPresent = Arr::has($working, $new);

            if (! $oldPresent) {
                $report['aliases_absent'][] = ['old' => $old, 'new' => $new];
                continue;
            }

            if ($newPresent) {
                // Canonical key already has a value (even if empty/false/null) — it wins.
                // The old key is redundant, not a conflict to resolve destructively; just
                // drop it from the working copy so it stops shadowing the canonical field
                // in any code that still reads the old path directly during the transition.
                $working = $this->forget($working, $old);
                $report['aliases_dropped'][] = ['old' => $old, 'new' => $new, 'reason' => 'canonical_key_already_present'];
                continue;
            }

            // Old present, new absent: migrate the exact value, preserving its exact type.
            $value = Arr::get($working, $old);
            $working = $this->set($working, $new, $value);
            $working = $this->forget($working, $old);
            $report['aliases_migrated'][] = ['old' => $old, 'new' => $new];
        }

        [$working, $strippedReport] = $this->stripDeprecatedFields($working);
        $report['deprecated_stripped'] = $strippedReport;

        $knownTopLevel = $this->knownTopLevelKeys();
        $report['unknown_top_level_keys'] = array_values(array_diff(array_keys($working), $knownTopLevel));

        return ['content' => $working, 'report' => $report];
    }

    /**
     * Derives the set of legitimate top-level content_json keys directly
     * from every field path declared in the canonical schema (e.g.
     * 'hero.eyebrow' contributes 'hero', 'trust_chips' contributes itself),
     * plus a small fixed list of system keys that are never part of the
     * editorial schema by design (fare cache, media-removal markers).
     *
     * @return list<string>
     */
    private function knownTopLevelKeys(): array
    {
        $keys = ['_fare_cache', '_media_removed'];
        foreach (Schema::fields() as $sectionFields) {
            foreach ($sectionFields as $field) {
                $top = explode('.', $field['path'])[0];
                if (! in_array($top, $keys, true)) {
                    $keys[] = $top;
                }
            }
        }

        return $keys;
    }

    /**
     * Fields the canonical schema marks `deprecated_remove` (see
     * HomepageCanonicalSchema::fields() field_status entries). Currently:
     * group_cards.items.{route,alt}. Only strips the field from each
     * repeating item if present — never removes the item itself, and never
     * touches items where the field is already absent.
     *
     * @param  array<string, mixed>  $content
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function stripDeprecatedFields(array $content): array
    {
        $stripped = [];
        $deprecatedScalarPaths = [
            'hero.cta_primary_text',
            'hero.cta_primary_url',
            'hero.cta_secondary_text',
            'hero.cta_secondary_url',
        ];

        foreach ($deprecatedScalarPaths as $path) {
            if (Arr::has($content, $path)) {
                $stripped[] = ['path' => $path, 'value' => Arr::get($content, $path)];
                $content = $this->forget($content, $path);
            }
        }

        $deprecatedItemFields = [
            'group_cards.items' => ['route', 'alt'],
        ];

        foreach ($deprecatedItemFields as $listPath => $fieldsToStrip) {
            $items = Arr::get($content, $listPath);
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach ($fieldsToStrip as $field) {
                    if (array_key_exists($field, $item)) {
                        $stripped[] = ['path' => "{$listPath}.{$index}.{$field}", 'value' => $item[$field]];
                        unset($item[$field]);
                        $items[$index] = $item;
                    }
                }
            }

            $content = $this->set($content, $listPath, $items);
        }

        return [$content, $stripped];
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private function set(array $array, string $path, mixed $value): array
    {
        Arr::set($array, $path, $value);

        return $array;
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private function forget(array $array, string $path): array
    {
        Arr::forget($array, $path);

        return $array;
    }
}
