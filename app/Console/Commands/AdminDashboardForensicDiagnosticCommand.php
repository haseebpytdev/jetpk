<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Http\Controllers\Admin\DashboardController;
use App\Models\Booking;
use App\Models\ClientProfile;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\User;
use App\Services\Client\CurrentClientContext;
use App\Services\Dashboard\AgencyDashboardService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\ViewException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Read-only admin dashboard forensics: data composition, route match, auth, and optional HTTP render.
 */
class AdminDashboardForensicDiagnosticCommand extends Command
{
    protected $signature = 'ota:admin-dashboard-forensic-diagnostic
                            {--user-id= : Platform admin user id for platform-admin auth state}
                            {--auth-state=platform-admin : guest|platform-admin|customer|agent|staff}
                            {--render-view : Render via HTTP kernel GET /admin (uses real guard + middleware)}
                            {--correlation= : Optional correlation token for log grep}
                            {--client-slug= : Optional client slug bootstrap (defaults jetpk when configured)}
                            {--simulate-host= : Optional host for HTTP simulation (defaults APP_URL host)}
                            {--simulate-scheme=https : Scheme for HTTP simulation}';

    protected $description = 'Admin dashboard route/auth/view forensic diagnostic (read-only)';

    public function handle(AgencyDashboardService $dashboardService): int
    {
        $correlation = trim((string) ($this->option('correlation') ?: ''));
        if ($correlation === '') {
            $correlation = 'admin-dash-forensic-'.Str::lower(Str::random(12));
        }

        $authState = strtolower(trim((string) $this->option('auth-state', 'platform-admin')));
        $this->bootstrapClientContext(trim((string) ($this->option('client-slug') ?: '')));

        $payload = [
            'correlation' => $correlation,
            'route' => 'GET /admin',
            'route_name' => 'admin.dashboard',
            'controller' => DashboardController::class.'@index',
            'auth_state' => $authState,
            'client_slug' => function_exists('current_client_slug') ? current_client_slug() : null,
            'resolved_admin_view' => function_exists('client_view') ? client_view('index', 'admin') : 'dashboard.admin.index',
            'booking_aggregates' => $this->bookingAggregates(),
        ];

        $payload['route_match_status'] = $this->probeRouteMatch('/admin');

        $user = $this->resolveUserForAuthState($authState);
        $payload['resolved_user_id'] = $user?->id;
        $payload['resolved_account_type'] = $user?->account_type?->value;

        if ($authState === 'platform-admin' && $user === null) {
            $payload['composition_status'] = 'skipped';
            $payload['middleware_authorization_status'] = 'no_platform_admin_user';
            $this->emitDiagnostic($payload);
            $this->error('No platform admin user available for auth-state=platform-admin.');

            return self::FAILURE;
        }

        if ($authState !== 'guest' && $user !== null) {
            try {
                $data = $dashboardService->build($user);
                $commandCenter = $dashboardService->buildAdminCommandCenter($user);
                $payload['has_live_data'] = (bool) ($data['hasLiveData'] ?? false);
                $payload['operational_kpi_keys'] = collect($data['operationalKpis'] ?? [])->pluck('key')->values()->all();
                $payload['recent_bookings_count'] = collect($data['recentBookings'] ?? [])->count();
                $payload['supplier_failures_count'] = collect($commandCenter['recentSupplierFailures'] ?? [])->count();
                $payload['composition_status'] = 'ok';
            } catch (Throwable $e) {
                $payload['composition_status'] = 'failed';
                $payload['exception'] = $this->exceptionSummary($e);
                $this->emitDiagnostic($payload);

                return self::FAILURE;
            }
        } else {
            $payload['composition_status'] = 'skipped_guest';
        }

        if ($this->option('render-view')) {
            $httpReport = $this->simulateHttpGetAdmin($user, $authState);
            $payload = array_merge($payload, $httpReport);

            if (($httpReport['view_render_status'] ?? '') === 'failed'
                || ($httpReport['http_status'] ?? 0) >= 500) {
                $this->emitDiagnostic($payload);

                return self::FAILURE;
            }
        } else {
            if ($authState === 'platform-admin' && $user !== null) {
                $controllerReport = $this->simulateControllerWithAuth($user);
                $payload = array_merge($payload, $controllerReport);
            }
        }

        $this->emitDiagnostic($payload);
        $this->info('Admin dashboard forensic diagnostic completed. correlation='.$correlation);

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    protected function bookingAggregates(): array
    {
        return [
            'bookings_total' => Booking::query()->count(),
            'bookings_cancelled' => Booking::query()->where('status', 'cancelled')->count(),
            'bookings_pending' => Booking::query()->where('status', 'pending')->count(),
            'cancellation_status_cancelled' => Booking::query()->where('cancellation_status', 'cancelled')->count(),
            'cancellation_status_null' => Booking::query()->whereNull('cancellation_status')->count(),
            'supplier_bookings_cancelled' => SupplierBooking::query()->where('status', 'cancelled')->count(),
            'supplier_attempts_total' => SupplierBookingAttempt::query()->count(),
        ];
    }

    protected function bootstrapClientContext(string $slug): void
    {
        $slug = $slug !== '' ? $slug : trim((string) config('ota_client.slug', ''));
        if ($slug === '') {
            $slug = 'jetpk';
        }

        $profile = ClientProfile::query()->where('slug', $slug)->first();
        if ($profile !== null) {
            app(CurrentClientContext::class)->set($profile);
        }
    }

    protected function probeRouteMatch(string $uri): string
    {
        try {
            $request = Request::create($uri, 'GET');
            $route = Route::getRoutes()->match($request);
            if ((string) $route->getName() === 'admin.dashboard') {
                return 'matched_admin_dashboard';
            }

            return 'matched_other:'.(string) $route->getName();
        } catch (NotFoundHttpException) {
            return 'not_found';
        } catch (Throwable) {
            return 'match_error';
        }
    }

    protected function resolveUserForAuthState(string $authState): ?User
    {
        return match ($authState) {
            'guest' => null,
            'platform-admin' => $this->resolvePlatformAdmin(),
            'customer' => User::query()->where('account_type', AccountType::Customer)->orderBy('id')->first(),
            'agent' => User::query()->whereIn('account_type', [AccountType::Agent, AccountType::AgentStaff])->orderBy('id')->first(),
            'staff' => User::query()->where('account_type', AccountType::Staff)->orderBy('id')->first(),
            default => null,
        };
    }

    protected function resolvePlatformAdmin(): ?User
    {
        $userId = $this->option('user-id');
        if ($userId !== null && $userId !== '') {
            $user = User::query()->find((int) $userId);

            return $user?->isPlatformAdmin() ? $user : $user;
        }

        return User::query()
            ->where('account_type', AccountType::PlatformAdmin)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function simulateControllerWithAuth(User $user): array
    {
        Auth::guard('web')->setUser($user);
        Auth::shouldUse('web');

        try {
            $controller = app(DashboardController::class);
            /** @var \Illuminate\View\View $view */
            $view = $controller->index();
            $view->render();

            return [
                'controller_authorization_status' => 'ok',
                'view_resolution_status' => 'ok',
                'view_render_status' => 'ok',
                'view_name' => $view->name(),
            ];
        } catch (Throwable $e) {
            return [
                'controller_authorization_status' => $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 'denied' : 'error',
                'view_resolution_status' => 'failed',
                'view_render_status' => 'failed',
                'exception' => $this->exceptionSummary($e),
            ];
        } finally {
            Auth::guard('web')->logout();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function simulateHttpGetAdmin(?User $user, string $authState): array
    {
        $this->applyDiagnosticUrlRoot();

        $kernel = app(HttpKernel::class);
        $request = Request::create('/admin', 'GET');
        $request->setLaravelSession(app('session.store'));
        app('session')->start();

        if ($user !== null) {
            Auth::guard('web')->login($user);
            $request->setUserResolver(static fn () => $user);
        } else {
            Auth::guard('web')->logout();
        }

        $response = null;
        try {
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            $report = [
                'http_status' => $status,
                'middleware_authorization_status' => match (true) {
                    $authState === 'guest' && $status === 302 => 'redirect_unauthenticated',
                    $authState === 'platform-admin' && $status === 200 => 'ok',
                    in_array($authState, ['customer', 'agent', 'staff'], true) && $status === 403 => 'forbidden_expected',
                    default => 'http_'.$status,
                },
                'view_render_status' => $status === 200 ? 'ok' : 'not_rendered',
            ];

            if ($status === 200) {
                $content = (string) $response->getContent();
                $report['dashboard_marker_present'] = str_contains($content, 'ota-dash-overview')
                    || str_contains($content, 'Admin Dashboard');
            }

            if ($status === 302) {
                $report['redirect_location'] = $response->headers->get('Location');
            }

            return $report;
        } catch (Throwable $e) {
            return [
                'http_status' => 0,
                'middleware_authorization_status' => 'exception',
                'view_render_status' => 'failed',
                'exception' => $this->exceptionSummary($e),
            ];
        } finally {
            if ($response !== null) {
                $kernel->terminate($request, $response);
            }
            Auth::guard('web')->logout();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function exceptionSummary(Throwable $e): array
    {
        $summary = [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'frames' => collect($e->getTrace())
                ->take(8)
                ->map(static fn (array $frame): array => [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'function' => $frame['function'] ?? null,
                ])
                ->all(),
        ];

        if ($e instanceof ViewException) {
            $summary['view_name'] = $e->getView();
            $previous = $e->getPrevious();
            if ($previous instanceof Throwable) {
                $summary['previous_exception'] = $this->exceptionSummary($previous);
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitDiagnostic(array $payload): void
    {
        Log::info('admin.dashboard.forensic_diagnostic', $payload);
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function applyDiagnosticUrlRoot(): void
    {
        $scheme = strtolower(trim((string) $this->option('simulate-scheme', 'https')));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'https';
        }

        $host = trim((string) ($this->option('simulate-host') ?: ''));
        if ($host === '') {
            $configured = (string) config('app.url', '');
            $parsed = $configured !== '' ? parse_url($configured) : false;
            $host = is_array($parsed) && isset($parsed['host']) ? (string) $parsed['host'] : 'localhost';
            if ($scheme === 'https' && $host === 'localhost' && ! empty($parsed['scheme'])) {
                $scheme = (string) $parsed['scheme'];
            }
        }

        $root = $scheme.'://'.$host;
        URL::forceRootUrl($root);
        URL::forceScheme($scheme);
    }
}
