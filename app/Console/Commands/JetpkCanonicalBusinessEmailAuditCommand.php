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

    protected $signature = 'jetpk:canonical-business-email-audit';

    protected $description = 'Verify JetPK config and templates resolve to ota@jetpakistan.pk without legacy fallback addresses';

    public function handle(): int
    {
        $this->line('Classification: READ-ONLY canonical business email audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $fail = 0;

        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');
        if ($fromAddress !== self::CANONICAL) {
            $this->error('mail.from.address='.$fromAddress.' (expected '.self::CANONICAL.')');
            $fail++;
        } else {
            $this->line('mail.from.address='.self::CANONICAL);
        }

        if ($fromName !== 'JetPakistan') {
            $this->error('mail.from.name='.$fromName.' (expected JetPakistan)');
            $fail++;
        } else {
            $this->line('mail.from.name=JetPakistan');
        }

        foreach (['ota-brand.support_email', 'ota-client.support_email'] as $key) {
            [$file, $field] = explode('.', $key);
            $value = (string) config($file.'.'.$field);
            if ($value !== self::CANONICAL) {
                $this->error($key.'='.$value);
                $fail++;
            } else {
                $this->line($key.'='.self::CANONICAL);
            }
        }

        $brand = JetpkEmailBrandingResolver::resolve('jetpk');
        if (($brand['support_email'] ?? '') !== self::CANONICAL) {
            $this->error('JetpkEmailBrandingResolver support_email='.($brand['support_email'] ?? ''));
            $fail++;
        } else {
            $this->line('JetpkEmailBrandingResolver support_email='.self::CANONICAL);
        }

        $paths = [
            'resources/views/errors/layout.blade.php',
            'resources/views/themes/frontend/jetpakistan/frontend/support.blade.php',
            'resources/views/themes/frontend/jetpakistan/frontend/about.blade.php',
            'config/ota-brand.php',
            'config/ota-client.php',
            '.env.example',
        ];

        foreach ($paths as $path) {
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
