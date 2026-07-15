<?php

namespace App\Console\Commands;

use App\Models\ClientProfile;
use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Support\Audits\BookingFlowSmokeSafetyOutput;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class OtaClientContextFlowAuditCommand extends Command
{
    protected $signature = 'ota:client-context-flow-audit
                            {--client=jetpk : Client slug to audit under prefixed routes}';

    protected $description = 'MC-7C/7D read-only audit — client context persistence, helpers, and prefixed route flow';

    public function handle(ClientProfileResolver $profileResolver): int
    {
        $clientSlug = trim((string) $this->option('client'));

        if ($clientSlug === '') {
            $this->error('Option --client must not be empty.');

            return self::FAILURE;
        }

        foreach (BookingFlowSmokeSafetyOutput::readOnlyBanner() as $line) {
            $this->line($line);
        }
        $this->line('Classification: READ-ONLY client context flow audit (MC-7C/7D).');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $profile = $profileResolver->resolveBySlug($clientSlug);
        if ($profile === null) {
            $this->error("Client profile not found for slug: {$clientSlug}");

            return self::FAILURE;
        }

        $checks = [];
        $checks[] = $this->checkHttpRoute($clientSlug, 'login', '/login');
        $checks[] = $this->checkHttpRoute($clientSlug, 'register', '/register');
        $checks[] = $this->checkHttpRoute($clientSlug, 'group-ticketing.search', '/groups/search');
        $checks[] = $this->checkHttpRoute($clientSlug, 'booking.lookup', '/lookup-booking');
        $checks[] = $this->checkAdminGuestRedirect($clientSlug);
        $checks[] = $this->checkHelper('client_route(login)', fn () => client_route('login'), "/{$clientSlug}/login");
        $checks[] = $this->checkHelper('client_route(admin.dashboard)', fn () => client_route('admin.dashboard'), "/{$clientSlug}/admin");
        $checks[] = $this->checkHelper('client_url(/groups/search)', fn () => client_url('/groups/search'), "/{$clientSlug}/groups/search");
        $checks[] = $this->checkRootLoginUnprefixed();
        $checks[] = $this->checkDevCpUnprefixed();

        $this->table(
            ['check', 'expected', 'actual', 'status'],
            array_map(static fn (array $row): array => [
                $row['name'],
                $row['expected'],
                $row['actual'],
                $row['ok'] ? 'OK' : 'FAIL',
            ], $checks),
        );

        $failed = array_filter($checks, static fn (array $row): bool => ! $row['ok']);

        if ($failed !== []) {
            $this->error(sprintf('Audit failed: %d check(s) did not pass.', count($failed)));

            return self::FAILURE;
        }

        $this->info('All client context flow checks passed.');

        return self::SUCCESS;
    }

    /**
     * @return array{name: string, expected: string, actual: string, ok: bool}
     */
    private function checkHttpRoute(string $clientSlug, string $routeName, string $suffix): array
    {
        $parityName = 'client.parity.'.$routeName;
        $expectedPath = "/{$clientSlug}{$suffix}";

        if (! Route::has($parityName)) {
            return [
                'name' => "GET {$expectedPath}",
                'expected' => '200 OK',
                'actual' => 'parity route missing',
                'ok' => false,
            ];
        }

        $response = $this->httpGet($expectedPath);

        return [
            'name' => "GET {$expectedPath}",
            'expected' => '200 OK',
            'actual' => (string) $response->getStatusCode(),
            'ok' => $response->isOk(),
        ];
    }

    /**
     * @return array{name: string, expected: string, actual: string, ok: bool}
     */
    private function checkAdminGuestRedirect(string $clientSlug): array
    {
        $expectedLogin = "/{$clientSlug}/login";
        $response = $this->httpGet("/{$clientSlug}/admin");

        $location = $response->headers->get('Location') ?? '';
        $actual = $location !== '' ? parse_url($location, PHP_URL_PATH) : (string) $response->getStatusCode();

        return [
            'name' => "GET /{$clientSlug}/admin (guest)",
            'expected' => "redirect {$expectedLogin}",
            'actual' => is_string($actual) ? $actual : (string) $response->getStatusCode(),
            'ok' => $response->isRedirect() && is_string($actual) && str_ends_with($actual, $expectedLogin),
        ];
    }

    /**
     * @return array{name: string, expected: string, actual: string, ok: bool}
     */
    private function checkHelper(string $name, callable $generator, string $expected): array
    {
        $clientSlug = trim((string) $this->option('client'));
        $profile = ClientProfile::query()->where('slug', $clientSlug)->first();

        if ($profile === null) {
            return [
                'name' => $name,
                'expected' => $expected,
                'actual' => 'profile missing',
                'ok' => false,
            ];
        }

        app(CurrentClientContext::class)->set($profile);

        $actual = $generator();

        return [
            'name' => $name,
            'expected' => $expected,
            'actual' => is_string($actual) ? $actual : json_encode($actual),
            'ok' => $actual === $expected,
        ];
    }

    /**
     * @return array{name: string, expected: string, actual: string, ok: bool}
     */
    private function checkRootLoginUnprefixed(): array
    {
        app(CurrentClientContext::class)->clear();

        $actual = route('login', [], false);

        return [
            'name' => 'route(login) without preview',
            'expected' => '/login',
            'actual' => $actual,
            'ok' => $actual === '/login',
        ];
    }

    /**
     * @return array{name: string, expected: string, actual: string, ok: bool}
     */
    private function checkDevCpUnprefixed(): array
    {
        $actual = route('dev.cp.login', [], false);

        return [
            'name' => 'dev.cp.login unprefixed',
            'expected' => '/dev/cp/login',
            'actual' => $actual,
            'ok' => $actual === '/dev/cp/login',
        ];
    }

    private function httpGet(string $path): Response
    {
        $kernel = app(Kernel::class);
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
