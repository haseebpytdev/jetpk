<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\DeveloperUser;
use App\Models\User;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use App\Support\Audits\LiveRouteSmokeCatalog;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Throwable;

class OtaSmokeLiveRoutesCommand extends Command
{
    protected $signature = 'ota:smoke-live-routes
                            {--guest-only : Dispatch guest/public GET routes only (safe on production)}
                            {--fail-fast : Stop on first failure}
                            {--seed : Run OtaFoundationSeeder when demo users are missing}';

    protected $description = 'Read-only internal HTTP smoke for F6/F8 booking-flow routes (no supplier calls, no mutating POST).';

    private int $passed = 0;

    private int $failed = 0;

    private int $skipped = 0;

    /** @var list<string> */
    private array $failures = [];

    public function handle(): int
    {
        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('No supplier-booking, sync-pnr, ticketing, or cancellation POST attempted.');
        $this->newLine();

        $this->runRegistryPass();

        $this->runGuestDispatchPass();

        $bookingCountBeforeValidationPost = Booking::query()->count();
        $this->runValidationPostPass();
        $bookingCountAfterValidationPost = Booking::query()->count();
        if ($bookingCountAfterValidationPost !== $bookingCountBeforeValidationPost) {
            $this->recordFailure("booking count changed during validation POST (before={$bookingCountBeforeValidationPost}, after={$bookingCountAfterValidationPost})");
        }

        if (! $this->option('guest-only')) {
            $this->runAuthenticatedDispatchPass();
        } else {
            $this->warn('Skipping authenticated dispatch (--guest-only).');
        }

        $this->newLine();
        $this->info("Smoke summary: passed={$this->passed} failed={$this->failed} skipped={$this->skipped}");

        if ($this->failures !== []) {
            $this->newLine();
            $this->error('Failures:');
            foreach ($this->failures as $failure) {
                $this->line('  '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('Live route smoke check passed.');

        return self::SUCCESS;
    }

    private function runRegistryPass(): void
    {
        $this->info('=== Route registry ===');

        foreach (LiveRouteSmokeCatalog::registryRouteNames() as $routeName) {
            if (Route::has($routeName)) {
                $this->recordPass("registry:{$routeName}");
                $this->line("  OK  {$routeName}");
            } else {
                $this->recordFailure("registry:{$routeName} — route not registered");
                $this->line("  FAIL {$routeName} (missing)");
            }
        }

        $this->newLine();
    }

    private function runGuestDispatchPass(): void
    {
        $this->info('=== Guest/public GET dispatch ===');

        foreach (LiveRouteSmokeCatalog::guestDispatchTargets() as $target) {
            $this->dispatchAndEvaluate(
                $target['label'],
                $target['uri'],
                $target['accept'],
                null,
            );
        }

        $this->newLine();
    }

    private function runValidationPostPass(): void
    {
        $this->info('=== Validation-only POST dispatch (empty body) ===');

        foreach (LiveRouteSmokeCatalog::guestValidationPostTargets() as $target) {
            $this->dispatchAndEvaluate(
                $target['label'],
                $target['uri'],
                $target['accept'],
                null,
                'POST',
            );
        }

        $this->newLine();
    }

    private function runAuthenticatedDispatchPass(): void
    {
        $this->info('=== Authenticated GET dispatch ===');

        $context = $this->buildAuthContext();
        if ($context === null) {
            $this->warn('Authenticated dispatch skipped — demo users unavailable. Use --seed locally or --guest-only on live.');
            $this->newLine();

            return;
        }

        Config::set('ota-developer.enabled', true);

        foreach (LiveRouteSmokeCatalog::authenticatedDispatchTargets() as $target) {
            $params = $target['params'] ?? [];
            foreach ($params as $key => $value) {
                if ($value === '__booking__') {
                    if ($context['booking'] === null) {
                        $this->recordSkip($target['label'].' — no booking row for dynamic route');

                        continue 2;
                    }
                    $params[$key] = $context['booking'];
                }
            }

            if (! Route::has($target['route'])) {
                $this->recordFailure($target['label'].' — route missing: '.$target['route']);

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
            );
        }

        $this->newLine();
    }

    /**
     * @return array{
     *     developer: DeveloperUser,
     *     platform_admin: User,
     *     staff: User,
     *     agent: User,
     *     customer: User,
     *     booking: Booking|null
     * }|null
     */
    private function buildAuthContext(): ?array
    {
        if ($this->option('seed')) {
            $this->callSilent(OtaFoundationSeeder::class);
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
                'name' => 'Smoke Dev',
                'email' => 'smoke-dev@example.com',
                'password' => 'smoke-dev-password',
                'is_active' => true,
            ]);
        }

        $booking = $this->resolveSmokeBooking();

        return [
            'developer' => $developer,
            'platform_admin' => $platformAdmin->fresh(),
            'staff' => $staff,
            'agent' => $agent,
            'customer' => $customer,
            'booking' => $booking,
        ];
    }

    private function resolveSmokeBooking(): ?Booking
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
    private function dispatchAndEvaluate(string $label, string $uri, string $accept, ?array $auth, string $method = 'GET'): void
    {
        try {
            $result = $method === 'POST'
                ? $this->dispatchPost($uri, $accept, $auth)
                : $this->dispatchGet($uri, $accept, $auth);
        } catch (Throwable $e) {
            $this->recordFailure("{$label} [{$uri}] — exception: ".$e->getMessage());
            $this->line("  FAIL {$label} — exception");

            return;
        }

        $status = $result['status'];
        $acceptable = LiveRouteSmokeCatalog::acceptableStatusCodes();

        if (! in_array($status, $acceptable, true)) {
            $this->recordFailure("{$label} [{$uri}] — HTTP {$status} (500-risk)");
            $this->line("  FAIL {$label} — HTTP {$status}");

            return;
        }

        if ($this->responseContainsSecrets($result['content'])) {
            $this->recordFailure("{$label} [{$uri}] — forbidden secret pattern in response body");
            $this->line("  FAIL {$label} — secret pattern in body");

            return;
        }

        $this->recordPass("dispatch:{$label}");
        $this->line("  OK  {$label} — HTTP {$status}");
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

    /**
     * @param  array{0: string, 1: mixed}|null  $auth
     * @return array{status: int, content: string}
     */
    private function dispatchPost(string $uri, string $accept, ?array $auth): array
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

        $request = Request::create($uri, 'POST', [], [], [], [
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

    private function responseContainsSecrets(string $content): bool
    {
        $haystack = strtolower($content);

        foreach (LiveRouteSmokeCatalog::forbiddenResponsePatterns() as $pattern) {
            if (str_contains($haystack, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    private function recordPass(string $detail): void
    {
        $this->passed++;
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
}
