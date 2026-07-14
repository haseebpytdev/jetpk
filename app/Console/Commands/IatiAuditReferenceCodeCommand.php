<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IatiAuditReferenceCodeCommand extends Command
{
    protected $signature = 'iati:audit-reference-code';

    protected $description = 'Scan local Binham IATI reference paths and print endpoint usage';

    /** @var list<string> */
    private array $paths = [
        'Binham/Iati_new',
        'Binham/public_html',
        'Binham/iati',
        'Binham/ota.binham.pk',
    ];

    /** @var list<string> */
    private array $needles = [
        'testapi.iati.com',
        'api.iati.com',
        'rest/flight/v2',
        'IATI_ISSUE_BOOKING',
        'IATI_AUTH_TOKEN',
        'dd(',
        'dump(',
        'var_dump(',
    ];

    public function handle(): int
    {
        $root = base_path();
        foreach ($this->paths as $relative) {
            $full = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $this->line('--- path='.$relative.' exists='.(is_dir($full) ? 'yes' : 'no'));
            if (! is_dir($full)) {
                continue;
            }

            $files = File::allFiles($full);
            $hits = 0;
            foreach ($files as $file) {
                if (! in_array($file->getExtension(), ['php', 'js', 'json'], true)) {
                    continue;
                }
                $content = @file_get_contents($file->getPathname());
                if ($content === false) {
                    continue;
                }
                foreach ($this->needles as $needle) {
                    if (str_contains($content, $needle)) {
                        $this->line('  '.$file->getRelativePathname().' → '.$needle);
                        $hits++;
                        break;
                    }
                }
            }
            $this->line('  files_with_hits='.$hits);
        }

        $this->warn('Unsafe patterns to avoid: uploads/*_debug.txt, ?debug=1 search logs, CORS *');

        return self::SUCCESS;
    }
}
