<?php

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Support\Client\ClientPageKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ensures JetPK homepage drafts/published rows have at least four trending routes and destinations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_page_settings') || ! Schema::hasTable('client_profiles')) {
            return;
        }

        $resolver = app(\App\Services\Client\ClientPageContentResolver::class);
        $defaults = $resolver->defaultHomeContent();

        ClientProfile::query()->where('is_active', true)->each(function (ClientProfile $profile) use ($defaults): void {
            foreach ([ClientPageSettingStatus::Draft, ClientPageSettingStatus::Published] as $status) {
                $row = ClientPageSetting::query()
                    ->where('client_profile_id', $profile->id)
                    ->where('page_key', ClientPageKeys::HOME)
                    ->where('status', $status)
                    ->first();

                if ($row === null) {
                    continue;
                }

                $content = is_array($row->content_json) ? $row->content_json : [];
                $changed = false;

                $content = $this->ensureItems($content, 'routes', $defaults['routes']['items'] ?? [], $changed);
                $content = $this->ensureItems($content, 'destinations', $defaults['destinations']['items'] ?? [], $changed);

                if ($changed) {
                    $row->update(['content_json' => $content]);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  list<array<string, mixed>>  $seedItems
     * @return array<string, mixed>
     */
    private function ensureItems(array $content, string $section, array $seedItems, bool &$changed): array
    {
        $items = is_array($content[$section]['items'] ?? null) ? $content[$section]['items'] : [];
        $active = array_values(array_filter($items, static fn ($item) => is_array($item) && ($item['enabled'] ?? '1') !== '0' && trim((string) ($item['from'] ?? $item['code'] ?? $item['title'] ?? '')) !== ''));

        if (count($active) >= 4) {
            return $content;
        }

        $existingSignatures = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if ($section === 'routes') {
                $existingSignatures[] = strtoupper((string) ($item['from'] ?? '')).'|'.strtoupper((string) ($item['to'] ?? ''));
            } else {
                $existingSignatures[] = strtoupper((string) ($item['code'] ?? ''));
            }
        }

        foreach ($seedItems as $seed) {
            if (count($active) >= 4) {
                break;
            }

            $signature = $section === 'routes'
                ? strtoupper((string) ($seed['from'] ?? '')).'|'.strtoupper((string) ($seed['to'] ?? ''))
                : strtoupper((string) ($seed['code'] ?? ''));

            if ($signature === '|' || in_array($signature, $existingSignatures, true)) {
                continue;
            }

            $seed['id'] = $seed['id'] ?? (string) Str::uuid();
            $items[] = $seed;
            $existingSignatures[] = $signature;
            $active[] = $seed;
            $changed = true;
        }

        data_set($content, "{$section}.items", array_values($items));

        return $content;
    }

    public function down(): void
    {
        // Non-destructive data backfill — no rollback.
    }
};
