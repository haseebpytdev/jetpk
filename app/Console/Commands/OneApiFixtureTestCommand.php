<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OneApiFixtureTestCommand extends Command
{
    protected $signature = 'ota:one-api-fixture-test {--fixture= : Fixture file under tests/Fixtures/Suppliers/OneApi}';

    protected $description = 'Validate One API fixture files exist and are readable (no network).';

    public function handle(): int
    {
        $base = base_path('tests/Fixtures/Suppliers/OneApi');
        $fixture = (string) $this->option('fixture');
        $files = $fixture !== ''
            ? [$base.'/'.ltrim($fixture, '/')]
            : File::files($base);

        $ok = 0;
        foreach ($files as $file) {
            $path = is_string($file) ? $file : $file->getPathname();
            if (! is_file($path)) {
                $this->error('Missing: '.$path);

                continue;
            }
            $contents = file_get_contents($path);
            if ($contents === false || trim($contents) === '') {
                $this->error('Empty: '.$path);

                continue;
            }
            $this->line('OK '.basename($path).' ('.strlen($contents).' bytes)');
            $ok++;
        }

        return $ok > 0 ? self::SUCCESS : self::FAILURE;
    }
}
