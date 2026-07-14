<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\AgentApplication;
use App\Models\Booking;
use App\Models\DeveloperUser;
use App\Models\SupplierConnection;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\RoutePageHealthAuditCatalog;
use App\Support\Suppliers\LegacySupplierProviderDataRepair;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Symfony\Component\Finder\Finder;
use Throwable;

class OtaRoutePageHealthAuditCommand extends Command
{
    protected $signature = 'ota:route-page-health-audit
                            {--guest-only : Dispatch guest/public GET routes only (safe on production)}
                            {--admin : Platform admin critical GET pages}
                            {--staff : Staff critical GET pages}
                            {--all : Guest + admin + staff + agent + customer + devcp critical pages}
                            {--seed : Run OtaFoundationSeeder when demo users are missing (local/testing only)}
                            {--fail-fast : Stop on first failure}
                            {--skip-source-scan : Skip static mojibake/syntax pattern scan}';

    protected $description = 'Read-only route/page health audit: static Blade safety scan + safe GET dispatch (no supplier mutations).';

    private int $passed = 0;

    private int $warned = 0;

    private int $failed = 0;

    private int $skipped = 0;

    private int $serverErrors = 0;

    /** @var list<string> */
    private array $failures = [];

    public function handle(): int
    {
        if (! $this->hasScopeOption()) {
            $this->error('Specify a scope: --guest-only, --admin, --staff, or --all');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('supplier_mutation_attempted=false');
        $this->line('ticketing_attempted=false');
        $this->line('cancellation_attempted=false');
        $this->line('emails_sent=false');
        $this->newLine();

        if (! $this->option('skip-source-scan')) {
            $this->runSourceScan();
        }

        if ($this->shouldRunGuest()) {
            $this->runGuestDispatchPass();
        }

        if ($this->shouldRunAuthenticated()) {
            $this->runAuthenticatedDispatchPass();
        }

        $this->newLine();
        $this->info(sprintf(
            'Route page health summary: pass=%d warn=%d fail=%d skipped=%d server_errors=%d',
            $this->passed,
            $this->warned,
            $this->failed,
            $this->skipped,
            $this->serverErrors,
        ));
        $this->line('supplier_mutation_attempted=false');
        $this->line('ticketing_attempted=false');
        $this->line('cancellation_attempted=false');
        $this->line('emails_sent=false');

        if ($this->failures !== []) {
            $this->newLine();
            $this->error('Failures:');
            foreach ($this->failures as $failure) {
                $this->line('  '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('Route page health audit passed.');

        return self::SUCCESS;
    }

    private function hasScopeOption(): bool
    {
        return $this->option('guest-only')
            || $this->option('admin')
            || $this->option('staff')
            || $this->option('all');
    }

    private function shouldRunGuest(): bool
    {
        return $this->option('guest-only') || $this->option('all');
    }

    private function shouldRunAuthenticated(): bool
    {
        return $this->option('admin')
            || $this->option('staff')
            || $this->option('all');
    }

    private function runSourceScan(): void
    {
        $this->info('=== Static source scan (mojibake / Blade syntax hazards) ===');

        $hits = [];
        $skip = RoutePageHealthAuditCatalog::sourceScanSkipRelativePaths();

        foreach (RoutePageHealthAuditCatalog::mojibakeScanPaths() as $path) {
            if (is_dir($path)) {
                $finder = (new Finder)
                    ->files()
                    ->in($path)
                    ->name('*.php')
                    ->name('*.blade.php');

                foreach ($finder as $file) {
                    $this->scanSourceFile($file->getRealPath() ?: '', $skip, $hits);
                }

                continue;
            }

            if (is_file($path)) {
                $this->scanSourceFile($path, $skip, $hits);
            }
        }

        if ($hits === []) {
            $this->recordPass('source-scan');
            $this->line('  OK  no forbidden mojibake/syntax patterns in dashboard scan paths');
        } else {
            foreach ($hits as $hit) {
                $this->recordFailure('source-scan — '.$hit);
                $this->line('  FAIL '.$hit);
            }
        }

        $this->runAdminBladeRouteNameScan();

        $this->newLine();
    }

    private function runAdminBladeRouteNameScan(): void
    {
        $adminViewsPath = base_path('resources/views/dashboard/admin');
        if (! is_dir($adminViewsPath)) {
            return;
        }

        $missing = [];
        $finder = (new Finder)
            ->files()
            ->in($adminViewsPath)
            ->name('*.blade.php');

        foreach ($finder as $file) {
            $contents = File::get($file->getRealPath() ?: '');
            if (preg_match_all("/route\\(['\"]admin\\.[^'\"]+['\"]/", $contents, $matches) < 1) {
                continue;
            }

            foreach ($matches[0] as $match) {
                if (preg_match("/route\\(['\"](admin\\.[^'\"]+)['\"]/", $match, $nameMatch) !== 1) {
                    continue;
                }
                $routeName = $nameMatch[1];
                if (! Route::has($routeName)) {
                    $relative = str_replace('\\', '/', substr($file->getRealPath() ?: '', strlen(base_path()) + 1));
                    $missing[$routeName] = $relative;
                }
            }
        }

        if ($missing === []) {
            $this->recordPass('admin-blade-route-scan');
            $this->line('  OK  admin Blade route() names resolve');

            return;
        }

        foreach ($missing as $routeName => $file) {
            $this->recordFailure('admin-blade-route-scan — missing route '.$routeName.' in '.$file);
            $this->line('  FAIL missing route '.$routeName.' in '.$file);
        }
    }

    /**
     * @param  list<string>  $skip
     * @param  list<string>  $hits
     */
    private function scanSourceFile(string $path, array $skip, array &$hits): void
    {
        if ($path === '' || ! is_file($path)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($path, strlen(base_path()) + 1));
        if (in_array($relative, $skip, true)) {
            return;
        }

        $contents = File::get($path);
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];

        foreach (RoutePageHealthAuditCatalog::sourceSyntaxHazardRegexes() as $regex) {
            foreach ($lines as $index => $line) {
                if (preg_match($regex, $line) === 1) {
                    $hits[] = $relative.':'.($index + 1).' invalid-null-display-syntax';
                }
            }
        }

        foreach (RoutePageHealthAuditCatalog::mojibakeForbiddenSubstrings() as $pattern) {
            if ($pattern === '' || ! str_contains($contents, $pattern)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (str_contains($line, $pattern)) {
                    $hits[] = $relative.':'.($index + 1).' mojibake='.$pattern;

                    break;
                }
            }
        }

        foreach (RoutePageHealthAuditCatalog::mojibakeForbiddenFallbackPatterns() as $pattern) {
            if ($pattern === '' || ! str_contains($contents, $pattern)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (str_contains($line, $pattern)) {
                    $hits[] = $relative.':'.($index + 1).' mojibake-fallback='.$pattern;

                    break;
                }
            }
        }
    }

    private function runGuestDispatchPass(): void
    {
        $this->info('=== Guest/public GET dispatch ===');

        foreach (RoutePageHealthAuditCatalog::guestTargets() as $target) {
            $this->dispatchAndEvaluate(
                $target['label'],
                (string) $target['uri'],
                $target['accept'],
                null,
                $target['classification'],
                false,
            );
        }

        $this->newLine();
    }

    private function runAuthenticatedDispatchPass(): void
    {
        $this->info('=== Authenticated GET dispatch ===');

        $context = $this->buildAuthContext();
        if ($context === null) {
            $this->warn('Authenticated dispatch skipped — demo users unavailable. Use --seed locally or scope --guest-only on live.');
            $this->newLine();

            return;
        }

        Config::set('ota-developer.enabled', true);

        foreach (RoutePageHealthAuditCatalog::authenticatedTargets() as $target) {
            if (! $this->targetMatchesScope($target['classification'], $target['auth'])) {
                continue;
            }

            $params = $target['params'] ?? [];
            foreach ($params as $key => $value) {
                if ($value === '__booking__') {
                    if ($context['booking'] === null) {
                        $this->recordSkip($target['label'].' — no booking row for dynamic route');

                        continue 2;
                    }
                    $params[$key] = $context['booking'];
                }
                if ($value === '__supplier_connection__') {
                    if ($context['supplier_connection'] === null) {
                        $this->recordSkip($target['label'].' — no supplier connection row for dynamic route');

                        continue 2;
                    }
                    $params[$key] = $context['supplier_connection'];
                }
                if ($value === '__agent_application__') {
                    if ($context['agent_application'] === null) {
                        $this->recordSkip($target['label'].' — no agent application row for dynamic route');

                        continue 2;
                    }
                    $params[$key] = $context['agent_application'];
                }
                if ($value === '__agency__') {
                    if ($context['agency'] === null) {
                        $this->recordSkip($target['label'].' — no agency row for dynamic route');

                        continue 2;
                    }
                    $params[$key] = $context['agency'];
                }
                if ($value === '__user__') {
                    if ($context['user'] === null) {
                        $this->recordSkip($target['label'].' — no user row for dynamic route');

                        continue 2;
                    }
                    $params[$key] = $context['user'];
                }
                if ($value === '__support_ticket__') {
                    if ($context['support_ticket'] === null) {
                        $this->recordSkip($target['label'].' — no support ticket row for dynamic route');

                        continue 2;
                    }
                    $params[$key] = $context['support_ticket'];
                }
            }

            if (! Route::has($target['route'])) {
                $this->recordFailure($target['label'].' — route missing: '.$target['route']);
                $this->line('  FAIL '.$target['label'].' — route missing');

                continue;
            }

            $uri = route($target['route'], $params, false);
            $authSubject = match ($target['auth']) {
                'dev_cp' => $context['developer'],
                'platform_admin' => $context['platform_admin'],
                'staff' => $context['staff'],
                'agent' => $context['agent'],
                'customer' => $context['customer'],
                default => null,
            };

            $this->dispatchAndEvaluate(
                $target['label'],
                $uri,
                $target['accept'],
                $target['auth'] === 'guest' ? null : [$target['auth'], $authSubject],
                $target['classification'],
                true,
            );
        }

        $this->newLine();
    }

    private function targetMatchesScope(string $classification, string $auth): bool
    {
        if ($this->option('all')) {
            return true;
        }

        if ($this->option('admin')) {
            return in_array($classification, RoutePageHealthAuditCatalog::adminClassifications(), true)
                || in_array($auth, ['platform_admin', 'dev_cp'], true);
        }

        if ($this->option('staff')) {
            return $classification === 'staff' || $auth === 'staff';
        }

        return false;
    }

    /**
     * @return array{
     *     developer: DeveloperUser,
     *     platform_admin: User,
     *     staff: User,
     *     agent: User,
     *     customer: User,
     *     booking: Booking|null,
     *     supplier_connection: SupplierConnection|null,
     *     agent_application: AgentApplication|null,
     *     agency: Agency|null,
     *     user: User|null,
     *     support_ticket: SupportTicket|null
     * }|null
     */
    private function buildAuthContext(): ?array
    {
        if ($this->option('seed')) {
            if ($this->allowsRouteHealthFoundationSeed()) {
                $seeder = $this->laravel->make(OtaFoundationSeeder::class);
                $seeder->seedSupplierConnectionPlaceholders = false;
                $seeder->run();
            } else {
                $this->warn('Skipping OtaFoundationSeeder — supplier connections are admin-managed; audits seed users only in local/testing.');
            }

            // Safe legacy repair only (pia → pia_ndc); does not create rows.
            LegacySupplierProviderDataRepair::repairPiaProviderRows();
        }

        $platformAdmin = User::query()->where('email', 'admin@ota.demo')->first();
        $staff = User::query()->where('email', 'staff@ota.demo')->first();
        $agent = User::query()->where('email', 'agent@ota.demo')->first();

        if ($platformAdmin === null || $staff === null || $agent === null) {
            return null;
        }

        $platformAdmin->forceFill(['account_type' => AccountType::PlatformAdmin])->save();

        $customer = User::query()
            ->where('account_type', AccountType::Customer)
            ->first();

        if ($customer === null) {
            $customer = User::factory()->create([
                'account_type' => AccountType::Customer,
                'current_agency_id' => null,
            ]);
        }

        $developer = DeveloperUser::query()->first();
        if ($developer === null) {
            $developer = DeveloperUser::query()->create([
                'name' => 'Health Audit Dev',
                'email' => 'health-audit-dev@example.com',
                'password' => 'health-audit-dev-password',
                'is_active' => true,
            ]);
        }

        return [
            'developer' => $developer,
            'platform_admin' => $platformAdmin->fresh(),
            'staff' => $staff,
            'agent' => $agent,
            'customer' => $customer,
            'booking' => $this->resolveAuditBooking(),
            'supplier_connection' => SupplierConnection::query()->orderBy('id')->first(),
            'agent_application' => AgentApplication::query()->orderByDesc('id')->first(),
            'agency' => Agency::query()->orderBy('id')->first(),
            'user' => User::query()->orderBy('id')->first(),
            'support_ticket' => SupportTicket::query()->orderByDesc('id')->first(),
        ];
    }

    private function resolveAuditBooking(): ?Booking
    {
        $existing = Booking::query()->orderByDesc('id')->first();
        if ($existing !== null) {
            return $existing;
        }

        $agency = Agency::query()->where('slug', config('ota.default_agency_slug', 'asif-travels'))->first()
            ?? Agency::query()->first();

        if ($agency === null) {
            return null;
        }

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
        ]);
    }

    /**
     * @param  array{0: string, 1: mixed}|null  $auth
     */
    private function dispatchAndEvaluate(
        string $label,
        string $uri,
        string $accept,
        ?array $auth,
        string $classification,
        bool $authRequired,
    ): void {
        try {
            $result = $this->dispatchGet($uri, $accept, $auth);
        } catch (Throwable $e) {
            $this->serverErrors++;
            $this->recordFailure("{$label} [{$uri}] — exception: ".$e->getMessage());
            $this->line("  FAIL {$label} — exception ({$classification})");

            return;
        }

        $status = $result['status'];
        $authLabel = $authRequired ? 'yes' : 'no';

        if ($status >= 500) {
            $this->serverErrors++;
            $summary = $this->summarizeErrorBody($result['content']);
            $this->recordFailure("{$label} [{$uri}] — HTTP {$status} {$summary}");
            $this->line("  FAIL {$label} — HTTP {$status} ({$classification}, auth={$authLabel}) {$summary}");

            return;
        }

        if (! in_array($status, RoutePageHealthAuditCatalog::acceptableStatusCodes(), true)) {
            $this->recordWarn("{$label} [{$uri}] — HTTP {$status}");
            $this->line("  WARN {$label} — HTTP {$status} ({$classification}, auth={$authLabel})");

            return;
        }

        if ($label !== 'admin-api-settings-edit' && ($secretPattern = $this->detectForbiddenSecretPattern($result['content'])) !== null) {
            $this->recordFailure("{$label} [{$uri}] — forbidden secret pattern: {$secretPattern}");
            $this->line("  FAIL {$label} — forbidden secret pattern: {$secretPattern} ({$classification})");

            return;
        }

        $this->recordPass("dispatch:{$label}");
        $this->line("  OK  {$label} — HTTP {$status} ({$classification}, auth={$authLabel})");
    }

    private function summarizeErrorBody(string $content): string
    {
        $plain = trim(strip_tags($content));
        $plain = preg_replace('/\s+/', ' ', $plain) ?? '';

        if ($plain === '') {
            return '';
        }

        return '— '.substr($plain, 0, 120);
    }

    /**
     * @param  array{0: string, 1: mixed}|null  $auth
     * @return array{status: int, content: string}
     */
    private function dispatchGet(string $uri, string $accept, ?array $auth): array
    {
        Auth::logout();
        Session::flush();
        Session::start();

        if ($auth !== null) {
            [$mode, $subject] = $auth;
            if ($mode === 'dev_cp' && $subject instanceof DeveloperUser) {
                Session::put('dev_cp_user_id', $subject->id);
            } elseif ($subject instanceof User) {
                Auth::login($subject);
            }
        }

        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);

        $request = Request::create($uri, 'GET', [], [], [], [
            'HTTP_ACCEPT' => $accept,
            'HTTP_HOST' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ]);
        $request->setLaravelSession(app('session.store'));

        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $content = (string) $response->getContent();
        $kernel->terminate($request, $response);

        return ['status' => $status, 'content' => $content];
    }

    private function detectForbiddenSecretPattern(string $content): ?string
    {
        return RoutePageHealthAuditCatalog::detectForbiddenResponseSecret($content);
    }

    private function recordPass(string $detail): void
    {
        unset($detail);
        $this->passed++;
    }

    private function recordWarn(string $detail): void
    {
        unset($detail);
        $this->warned++;
    }

    private function recordFailure(string $detail): void
    {
        $this->failed++;
        $this->failures[] = $detail;

        if ($this->option('fail-fast')) {
            $this->error('Fail-fast: '.$detail);
            exit(self::FAILURE);
        }
    }

    private function recordSkip(string $detail): void
    {
        $this->skipped++;
        $this->warn('  SKIP '.$detail);
    }

    /**
     * Route health --seed may bootstrap demo users/agency in local/testing only.
     * Supplier connections are operational credentials and must never be audit-seeded.
     */
    private function allowsRouteHealthFoundationSeed(): bool
    {
        return app()->environment(['local', 'testing']);
    }
}
