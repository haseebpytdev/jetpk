<?php

namespace App\Console\Commands;

use App\Models\ClientProfile;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Symfony\Component\Finder\Finder;

/**
 * JetPK dedicated-fork local smoke checks — single-client root mode, routes, theme assets, DB isolation.
 */
class JetpkLocalSmokeCommand extends Command
{
    protected $signature = 'jetpk:local-smoke';

    protected $description = 'JetPK local smoke — single-client root mode, routes, theme assets, DB path, brand placeholders';

    /** @var list<string> */
    private array $failures = [];

    /** @var list<string> */
    private array $passes = [];

    public function handle(ClientProfileResolver $profileResolver, CurrentClientContext $clientContext): int
    {
        $this->line('JetPK local smoke (read-only)');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $this->checkSingleClientEnv();
        $this->checkDatabasePath();
        $this->checkClientProfile($profileResolver, $clientContext);
        $this->checkThemeFiles();
        $this->checkRoutes();
        $this->checkBrandPlaceholders();

        $this->newLine();
        $this->info(sprintf('pass=%d fail=%d', count($this->passes), count($this->failures)));

        if ($this->failures !== []) {
            $this->newLine();
            $this->error('Failures:');
            foreach ($this->failures as $failure) {
                $this->line('  - '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('JetPK local smoke passed.');

        return self::SUCCESS;
    }

    private function checkSingleClientEnv(): void
    {
        $checks = [
            'OTA_SINGLE_CLIENT_MODE' => filter_var(config('ota_client.single_client_mode'), FILTER_VALIDATE_BOOL),
            'OTA_SINGLE_CLIENT_ROOT' => filter_var(config('ota_client.single_client_root'), FILTER_VALIDATE_BOOL),
            'OTA_CLIENT_SLUG=jetpk' => (string) config('ota_client.slug', '') === 'jetpk',
            'CLIENT_ROUTE_PARITY_ENABLED=false' => config('client_route_parity.enabled', true) === false,
            'APP_NAME' => (string) config('app.name', '') !== '',
        ];

        foreach ($checks as $label => $ok) {
            $ok ? $this->recordPass('env:'.$label) : $this->recordFail('env:'.$label);
        }
    }

    private function checkDatabasePath(): void
    {
        $connection = (string) config('database.default', '');
        $database = (string) config("database.connections.{$connection}.database", '');

        if ($connection !== 'sqlite') {
            $this->recordFail('db:expected sqlite, got '.$connection);

            return;
        }

        $expected = realpath(base_path('database/database.sqlite'));
        $actual = $database !== '' ? realpath($database) : false;

        if ($expected === false || ! is_file($expected)) {
            $this->recordFail('db:JetPK sqlite file missing at database/database.sqlite');

            return;
        }

        if ($actual === false || $actual !== $expected) {
            $this->recordFail('db:connection does not point to JetPK copied sqlite ('.$database.')');

            return;
        }

        $this->recordPass('db:sqlite path isolated to JetPK fork');
    }

    private function checkClientProfile(ClientProfileResolver $profileResolver, CurrentClientContext $clientContext): void
    {
        $slug = (string) config('ota_client.slug', 'jetpk');
        $profile = $profileResolver->resolveBySlug($slug);

        if ($profile === null) {
            $this->recordFail('client:profile missing for slug '.$slug);

            return;
        }

        $clientContext->set($profile);
        $this->recordPass('client:slug resolves to jetpk profile #'.$profile->id);

        $theme = (string) ($profile->active_frontend_theme ?: 'jetpakistan');
        if (! is_dir(resource_path('views/themes/frontend/'.$theme))) {
            $this->recordFail('client:frontend theme missing on disk ('.$theme.')');
        } else {
            $this->recordPass('client:frontend theme '.$theme);
        }
    }

    private function checkThemeFiles(): void
    {
        $paths = [
            'public frontend css' => public_path('themes/frontend/jetpakistan/css'),
            'public frontend js' => public_path('themes/frontend/jetpakistan/js'),
            'public admin css' => public_path('themes/admin/jetpakistan/css'),
            'frontend home view' => resource_path('views/themes/frontend/jetpakistan/frontend/home.blade.php'),
            'frontend login view' => resource_path('views/themes/frontend/jetpakistan/auth/login.blade.php'),
            'storage symlink' => public_path('storage'),
        ];

        foreach ($paths as $label => $path) {
            if (is_dir($path) || is_file($path) || (is_link($path) || file_exists($path))) {
                $this->recordPass('asset:'.$label);
            } else {
                $this->recordFail('asset:missing '.$label.' ('.$path.')');
            }
        }
    }

    private function checkRoutes(): void
    {
        $routeChecks = [
            'home' => '/',
            'login' => '/login',
            'lookup-booking' => '/lookup-booking',
            'devcp-alias' => '/devcp',
        ];

        foreach ($routeChecks as $label => $uri) {
            $status = $this->dispatchGuestGet($uri);
            if ($label === 'devcp-alias' && ($status === 302 || $status === 301)) {
                $this->recordPass('route:'.$label.' redirect HTTP '.$status);

                continue;
            }
            if ($status >= 200 && $status < 400) {
                $this->recordPass('route:'.$label.' HTTP '.$status);
            } else {
                $this->recordFail('route:'.$label.' HTTP '.$status);
            }
        }

        if (! Route::has('admin.dashboard') && ! Route::has('admin.index')) {
            $this->recordFail('route:admin dashboard route missing');
        } else {
            $this->recordPass('route:admin dashboard registered');
        }

        $adminStatus = $this->dispatchGuestGet('/admin');
        if ($adminStatus === 302 || $adminStatus === 301) {
            $this->recordPass('route:admin guest redirect HTTP '.$adminStatus);
        } else {
            $this->recordFail('route:admin guest expected redirect, got HTTP '.$adminStatus);
        }
    }

    private function checkBrandPlaceholders(): void
    {
        $patterns = [
            '/\{\{\s*brand_name\s*\}\}/i',
            '/\{\{\s*client_name\s*\}\}/i',
            '/placeholder\s+123/i',
        ];

        $roots = [
            resource_path('views/themes/frontend/jetpakistan'),
            resource_path('views/themes/admin/jetpakistan'),
            resource_path('views/emails/jetpk'),
        ];

        $hits = [];
        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $finder = (new Finder)->files()->in($root)->name('*.blade.php');
            foreach ($finder as $file) {
                $contents = $file->getContents();
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $contents) === 1) {
                        $hits[] = str_replace('\\', '/', substr($file->getRealPath() ?: '', strlen(base_path()) + 1));
                        break;
                    }
                }
            }
        }

        if ($hits === []) {
            $this->recordPass('brand:no unresolved placeholders in JetPK views/emails');
        } else {
            $this->recordFail('brand:unresolved placeholders in '.implode(', ', array_slice($hits, 0, 5)));
        }
    }

    private function dispatchGuestGet(string $uri): int
    {
        Auth::logout();
        Session::flush();
        Session::start();

        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);
        $request = Request::create($uri, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response->getStatusCode();
    }

    private function recordPass(string $message): void
    {
        $this->passes[] = $message;
        $this->line('  OK  '.$message);
    }

    private function recordFail(string $message): void
    {
        $this->failures[] = $message;
        $this->line('  FAIL '.$message);
    }
}
