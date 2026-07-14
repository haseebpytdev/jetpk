<?php

namespace App\Console\Commands;

use App\Models\ClientPageAsset;
use App\Models\ClientProfile;
use App\Services\Client\ClientPageAdminContentResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPagePublicFallbackCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Read-only parity audit between Admin Page Settings and effective JetPK public content.
 */
class JetpkPageSettingsParityAuditCommand extends Command
{
    protected $signature = 'jetpk:page-settings-parity-audit';

    protected $description = 'Audit JetPK page settings admin/public parity per page (read-only)';

    public function handle(ClientPageAdminContentResolver $resolver): int
    {
        $this->line('Classification: READ-ONLY JetPK page-settings parity audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        if (! Schema::hasTable('client_page_settings')) {
            $this->error('client_page_settings table missing');

            return self::FAILURE;
        }

        $profile = ClientProfile::query()
            ->where('slug', 'jetpk')
            ->where('is_master_profile', false)
            ->first();

        if ($profile === null) {
            $this->error('JetPK client profile not found');

            return self::FAILURE;
        }

        $fail = 0;
        $rows = [];

        foreach (ClientPageKeys::all() as $pageKey) {
            $meta = $resolver->editorMeta($profile, $pageKey);
            $form = $resolver->formContentFor($profile, $pageKey);
            $effective = $resolver->effectivePublicContent($profile, $pageKey);
            $fieldCount = count(ClientPagePublicFallbackCatalog::fieldPathsFor($pageKey));
            $emptyIntentional = $resolver->intentionalEmptyFieldCount($form, $pageKey);
            $legacyFallback = $this->legacyFallbackCount($form, $pageKey);
            $missingMedia = ClientPageAsset::query()
                ->where('client_profile_id', $profile->id)
                ->where('page_key', $pageKey)
                ->whereNull('path')
                ->count();
            $masterLeakage = $this->masterLeakageCount($form);
            $parity = $resolver->parityStatus($form, $effective, $pageKey);
            $pageFail = 0;

            if ($meta['form_source'] === ClientPageAdminContentResolver::SOURCE_PUBLISHED && $parity === 'mismatch') {
                $pageFail++;
            }
            if ($meta['form_source'] === ClientPageAdminContentResolver::SOURCE_PUBLIC_FALLBACK && $form === []) {
                $pageFail++;
            }
            if (in_array($pageKey, [ClientPageKeys::ABOUT, ClientPageKeys::SUPPORT], true)) {
                if (trim((string) data_get($form, 'hero.title')) === '') {
                    $pageFail++;
                }
            }
            if ($masterLeakage > 0) {
                $pageFail++;
            }

            $fail += $pageFail;

            $rows[] = [
                $pageKey,
                $meta['draft'] ? 'yes' : 'no',
                $meta['published'] ? 'yes' : 'no',
                $meta['effective_source'],
                (string) $fieldCount,
                (string) $emptyIntentional,
                (string) $legacyFallback,
                (string) $missingMedia,
                (string) $masterLeakage,
                $parity,
                $pageFail > 0 ? 'fail' : 'pass',
            ];
        }

        $this->table([
            'page_key',
            'draft',
            'published',
            'effective_source',
            'field_count',
            'empty_intentional',
            'legacy_fallback',
            'missing_media',
            'master_leakage',
            'parity',
            'status',
        ], $rows);

        $this->newLine();
        $this->line('fail_count='.$fail);

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function legacyFallbackCount(array $form, string $pageKey): int
    {
        $fallback = ClientPagePublicFallbackCatalog::contentFor($pageKey);
        $count = 0;
        foreach (ClientPagePublicFallbackCatalog::fieldPathsFor($pageKey) as $path) {
            if (! Arr::has($form, $path) && Arr::has($fallback, $path)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function masterLeakageCount(array $form): int
    {
        $encoded = json_encode($form) ?: '';

        return collect(['parwaaz', 'master-client', 'yd-travel', 'haseebasif'])
            ->filter(fn (string $needle) => Str::contains(strtolower($encoded), $needle))
            ->count();
    }
}
