<?php

namespace App\Console\Commands;

use App\Services\Client\ClientPageContentResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientPageMediaSchema;
use App\Support\Client\ClientPageSectionSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only audit of JetPK page settings coverage and editor completeness.
 */
class JetpkPageSettingsAuditCommand extends Command
{
    protected $signature = 'jetpk:page-settings-audit';

    protected $description = 'Audit JetPK page settings keys, section schema coverage, and editor fields (read-only)';

    public function handle(ClientPageContentResolver $contentResolver): int
    {
        $this->line('Classification: READ-ONLY JetPK page settings audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $fail = 0;
        $warn = 0;
        $rows = [];

        if (! Schema::hasTable('client_page_settings')) {
            $this->error('client_page_settings table missing');
            $fail++;

            return self::FAILURE;
        }

        $editView = resource_path('views/themes/admin/jetpakistan/page-settings/edit.blade.php');
        $editSource = File::exists($editView) ? File::get($editView) : '';
        $mediaSchemaPath = app_path('Support/Client/ClientPageMediaSchema.php');
        $hasMediaSchema = File::exists($mediaSchemaPath);

        foreach (ClientPageKeys::all() as $key) {
            $sections = ClientPageSectionSchema::sectionsFor($key);
            $mediaFields = ClientPageMediaSchema::fieldsFor($key);
            $defaults = match ($key) {
                ClientPageKeys::HOME => $contentResolver->defaultHomeContent(),
                default => [],
            };
            $hasEditor = match ($key) {
                ClientPageKeys::HOME, ClientPageKeys::ABOUT, ClientPageKeys::SUPPORT => str_contains($editSource, "'{$key}'") || str_contains($editSource, "\"{$key}\""),
                ClientPageKeys::FOOTER, ClientPageKeys::GLOBAL => str_contains($editSource, $key),
                default => str_contains($editSource, $key) || str_contains($editSource, 'page-settings/partials'),
            };

            $severity = 'pass';
            if ($sections === [] && $mediaFields === []) {
                $severity = 'warn';
                $warn++;
            } elseif (! $hasEditor) {
                $severity = 'warn';
                $warn++;
            } elseif ($mediaFields !== [] && ! str_contains($editSource, 'media-sections')) {
                $severity = 'warn';
                $warn++;
            }

            $rows[] = [
                $key,
                ClientPageKeys::labels()[$key] ?? $key,
                count($sections),
                count($mediaFields),
                $hasEditor ? 'yes' : 'no',
                count($defaults) > 0 ? 'yes' : 'partial',
                $severity,
            ];
        }

        $this->table(['key', 'label', 'sections', 'media', 'editor', 'defaults', 'status'], $rows);
        $this->newLine();
        $this->line('pass='.(count($rows) - $fail - $warn)." warn={$warn} fail={$fail}");

        if (! $hasMediaSchema) {
            $this->error('ClientPageMediaSchema missing');
            $fail++;

            return self::FAILURE;
        }

        if (! str_contains($editSource, 'media-sections')) {
            $this->warn('Page editor missing structured media partial');
        }

        $assetService = File::get(app_path('Services/Client/ClientPageAssetService.php'));
        if (! str_contains($assetService, 'mimes:') && ! str_contains(File::get(app_path('Http/Controllers/Admin/ClientPageSettingsController.php')), 'mimes:')) {
            $this->warn('Image MIME validation should be confirmed on asset upload');
            $warn++;
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
