<?php

namespace App\Console\Commands;

use App\Support\Emails\JetpkEmailBrandingResolver;
use Illuminate\Console\Command;

/**
 * Read-only audit for canonical JetPakistan public business email defaults.
 */
class JetpkCanonicalBusinessEmailAuditCommand extends Command
{
    private const CANONICAL = 'ota@jetpakistan.pk';

    /** @var list<string> */
    private const FORBIDDEN_FALLBACKS = [
        'support@haseebasif.com',
        'ticketingjp@jetpakistan.com',
        'support@jetpakistan.com',
    ];

    /** @var list<string> */
    private const JETPK_SCOPED_PATHS = [
        'resources/views/themes/frontend/jetpakistan/frontend/support.blade.php',
        'resources/views/themes/frontend/jetpakistan/frontend/about.blade.php',
        'resources/views/themes/frontend/jetpakistan/layouts/auth.blade.php',
        'app/Support/Client/ClientPagePublicFallbackCatalog.php',
        'app/Services/Client/JetPakistanClientProfileProvisioner.php',
        'app/Support/Branding/ClientMailBrandingResolver.php',
        'app/Support/Branding/SafeBrandingResolver.php',
        '.env.example',
    ];

    protected $signature = 'jetpk:canonical-business-email-audit';

    protected $description = 'Verify JetPK-scoped config and templates resolve to ota@jetpakistan.pk without legacy fallback addresses';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY canonical business email audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $fail = 0;

        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');
        if ($fromAddress !== self::CANONICAL) {
            $this->warn('mail.from.address='.$fromAddress.' (production should be '.self::CANONICAL.')');
            $fail++;
        } else {
            $this->line('mail.from.address='.self::CANONICAL);
        }

        if ($fromName !== 'JetPakistan') {
            $this->warn('mail.from.name='.$fromName.' (production should be JetPakistan)');
            $fail++;
        } else {
            $this->line('mail.from.name=JetPakistan');
        }

        $brand = JetpkEmailBrandingResolver::resolve('jetpk');
        if (($brand['support_email'] ?? '') !== self::CANONICAL) {
            $this->error('JetpkEmailBrandingResolver support_email='.($brand['support_email'] ?? ''));
            $fail++;
        } else {
            $this->line('JetpkEmailBrandingResolver support_email='.self::CANONICAL);
        }

        $jetpkFallback = (string) file_get_contents(base_path('app/Support/Branding/SafeBrandingResolver.php'));
        if (! str_contains($jetpkFallback, "'support_email' => 'ota@jetpakistan.pk'")) {
            $this->error('SafeBrandingResolver missing JetPK support_email branch');
            $fail++;
        } else {
            $this->line('SafeBrandingResolver JetPK branch=ota@jetpakistan.pk');
        }

        foreach (self::JETPK_SCOPED_PATHS as $path) {
            $full = base_path($path);
            if (! is_file($full)) {
                $this->error('Missing file: '.$path);
                $fail++;

                continue;
            }
            $contents = (string) file_get_contents($full);
            foreach (self::FORBIDDEN_FALLBACKS as $forbidden) {
                if (str_contains($contents, $forbidden)) {
                    $this->error($path.' contains forbidden fallback '.$forbidden);
                    $fail++;
                }
            }
        }

        $this->newLine();
        $this->line('fail_count='.$fail);

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
