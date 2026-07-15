<?php

namespace App\Console\Commands;

use App\Support\Emails\JetpkEmailSampleData;
use App\Support\Emails\JetpkEmailViewResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Renders JetPK email templates to static HTML using safe sample data.
 *
 *   php artisan ota:jetpk-email-preview --type=otp
 *   php artisan ota:jetpk-email-preview --type=booking_created
 *   php artisan ota:jetpk-email-preview --type=payment_success
 *   php artisan ota:jetpk-email-preview --type=invoice
 *   php artisan ota:jetpk-email-preview --all
 *
 * Does NOT send email, write DB, call suppliers, or expose real data.
 */
class OtaJetpkEmailTemplatePreviewCommand extends Command
{
    use JetpkEmailSampleData;

    protected $signature = 'ota:jetpk-email-preview
                            {--type= : Email type key (e.g. otp, booking_created, invoice)}
                            {--all : Render every JetPK email type}';

    protected $description = 'Render JetPakistan email templates to storage/app/email-previews/jetpk as static HTML (sample data only).';

    protected string $outputDir = 'email-previews/jetpk';

    public function handle(): int
    {
        $types = $this->resolveTypes();

        if (empty($types)) {
            $this->error('No type specified. Use --type=<key> or --all.');
            $this->line('Available types: ' . implode(', ', array_keys(JetpkEmailViewResolver::all())));
            return self::FAILURE;
        }

        $dir = storage_path('app/' . $this->outputDir);
        File::ensureDirectoryExists($dir);

        $ok = 0;
        $fail = 0;

        foreach ($types as $type) {
            $view = JetpkEmailViewResolver::resolve($type, 'jetpk');

            if (! $view || ! View::exists($view)) {
                $this->error("  [skip] {$type} — view not found (" . ($view ?: 'unmapped') . ')');
                $fail++;
                continue;
            }

            try {
                $html = View::make($view, $this->sampleData($type))->render();
                $path = $dir . '/' . $type . '.html';
                File::put($path, $html);
                $this->info("  [ok]   {$type} -> " . $path);
                $ok++;
            } catch (\Throwable $e) {
                $this->error("  [fail] {$type} — " . $e->getMessage());
                $fail++;
            }
        }

        $this->newLine();
        $this->line("Rendered: {$ok}  Failed: {$fail}");
        $this->line('Output directory: ' . $dir);

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveTypes(): array
    {
        if ($this->option('all')) {
            return array_keys(JetpkEmailViewResolver::all());
        }

        $type = $this->option('type');

        return $type ? [str_replace(['-', ' '], '_', strtolower(trim($type)))] : [];
    }
}
