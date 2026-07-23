<?php

use App\Exceptions\PlatformModuleDisabledException;
use App\Http\Controllers\Developer\DevCpClientProfilesController;
use App\Http\Controllers\Developer\DevCpMonitoringController;
use App\Http\Controllers\Developer\DevCpOverviewController;
use App\Http\Controllers\Developer\DevCpPlatformAdminUsersController;
use App\Http\Controllers\Developer\DevCpPlatformController;
use App\Http\Controllers\Developer\DevCpSecurityEventsController;
use App\Http\Controllers\Developer\DevCpUiLayersController;
use App\Http\Controllers\Developer\DevCpUiVersionsController;
use App\Http\Controllers\Developer\DeveloperAuthController;
use App\Http\Controllers\Developer\DeveloperPasswordController;
use App\Http\Controllers\Developer\PlatformModuleControlController;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\EnsureAgencyContext;
use App\Http\Middleware\EnsureAgentAdmin;
use App\Http\Middleware\EnsureAgentPermission;
use App\Http\Middleware\EnsureCustomerEmailVerifiedForPortal;
use App\Http\Middleware\EnsureDeveloperControlPanelAccess;
use App\Http\Middleware\EnsureGoogleOnboardingComplete;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsurePlatformModuleRouteEnabled;
use App\Http\Middleware\EnsureStaffPermission;
use App\Http\Middleware\PersistClientPreviewContext;
use App\Http\Middleware\ProtectClientUiPreview;
use App\Http\Middleware\ResolveClientUiVersion;
use App\Http\Middleware\ResolvePreviewClient;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UiVersionRoutePrefixMiddleware;
use App\Services\Client\ClientMutatingFlowParityRouteRegistrar;
use App\Services\Client\ClientPageSettingsParityRouteRegistrar;
use App\Services\Client\ClientPrefixedRouteRegistrar;
use App\Support\Client\ClientErrorResponseResolver;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Platform\PlatformModuleRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware([
                'web',
                'auth',
                'agency.context',
                'account.type:platform_admin',
            ])->group(base_path('routes/admin.php'));

            Route::middleware([
                'web',
                'auth',
                'agency.context',
                'account.type:platform_admin,staff',
            ])->group(base_path('routes/admin-page-settings.php'));

            Route::middleware([
                'web',
                'auth',
                'agency.context',
                'account.type:staff',
            ])->group(base_path('routes/staff.php'));

            Route::middleware([
                'web',
                'auth',
                'agency.context',
                'account.type:agent,agent_staff',
            ])->group(base_path('routes/agent.php'));

            Route::middleware([
                'web',
                'auth',
                'agency.context',
                'account.type:customer',
                'customer.email.portal.verified',
            ])->group(base_path('routes/customer.php'));

            Route::middleware(['web'])
                ->group(base_path('routes/preview.php'));

            Route::middleware(['web'])
                ->prefix('dev/cp')
                ->name('dev.cp.')
                ->group(function (): void {
                    Route::get('/login', [DeveloperAuthController::class, 'showLogin'])->name('login');
                    Route::post('/login', [DeveloperAuthController::class, 'login'])
                        ->middleware('throttle:6,1')
                        ->name('login.store');

                    Route::post('/logout', [DeveloperAuthController::class, 'logout'])
                        ->middleware('developer.cp')
                        ->name('logout');

                    Route::middleware('developer.cp')->group(function (): void {
                        Route::get('/password', [DeveloperPasswordController::class, 'show'])->name('password');
                        Route::post('/password', [DeveloperPasswordController::class, 'store'])->name('password.store');

                        Route::get('/', [DevCpOverviewController::class, 'index'])->name('index');
                        Route::get('/companies', [DevCpPlatformController::class, 'companies'])->name('companies.index');
                        Route::post('/companies/{agency}/package', [DevCpPlatformController::class, 'assignPackage'])->name('companies.package');
                        Route::post('/companies/{agency}/modules', [DevCpPlatformController::class, 'updateModules'])->name('companies.modules');
                        Route::get('/clients', [DevCpClientProfilesController::class, 'index'])->name('clients.index');
                        Route::get('/clients/create', [DevCpClientProfilesController::class, 'create'])->name('clients.create');
                        Route::post('/clients', [DevCpClientProfilesController::class, 'store'])->name('clients.store');
                        Route::get('/clients/{clientProfile}/edit', [DevCpClientProfilesController::class, 'edit'])->name('clients.edit');
                        Route::put('/clients/{clientProfile}', [DevCpClientProfilesController::class, 'update'])->name('clients.update');
                        Route::get('/clients/{clientProfile}/branding', [DevCpClientProfilesController::class, 'branding'])->name('clients.branding');
                        Route::put('/clients/{clientProfile}/branding', [DevCpClientProfilesController::class, 'updateBranding'])->name('clients.branding.update');
                        Route::get('/clients/{clientProfile}/modules', [DevCpClientProfilesController::class, 'modules'])->name('clients.modules');
                        Route::put('/clients/{clientProfile}/modules', [DevCpClientProfilesController::class, 'updateModules'])->name('clients.modules.update');
                        Route::get('/clients/{clientProfile}/suppliers', [DevCpClientProfilesController::class, 'suppliers'])->name('clients.suppliers');
                        Route::put('/clients/{clientProfile}/suppliers', [DevCpClientProfilesController::class, 'updateSuppliers'])->name('clients.suppliers.update');
                        Route::get('/clients/{clientProfile}/theme', [DevCpClientProfilesController::class, 'theme'])->name('clients.theme');
                        Route::put('/clients/{clientProfile}/theme', [DevCpClientProfilesController::class, 'updateTheme'])->name('clients.theme.update');
                        Route::post('/clients/{clientProfile}/export', [DevCpClientProfilesController::class, 'export'])->name('clients.export');
                        Route::post('/clients/{clientProfile}/duplicate', [DevCpClientProfilesController::class, 'duplicate'])->name('clients.duplicate');
                        Route::get('/users', [DevCpPlatformAdminUsersController::class, 'index'])->name('users.index');
                        Route::post('/users', [DevCpPlatformAdminUsersController::class, 'store'])->name('users.store');
                        Route::post('/users/{user}/reset-password', [DevCpPlatformAdminUsersController::class, 'resetPassword'])->name('users.reset-password');
                        Route::post('/users/{user}/status', [DevCpPlatformAdminUsersController::class, 'updateStatus'])->name('users.status');
                        Route::get('/security-events', [DevCpSecurityEventsController::class, 'index'])->name('security-events.index');
                        Route::get('/health', [DevCpMonitoringController::class, 'health'])->name('health');
                        Route::get('/sabre-status', [DevCpMonitoringController::class, 'sabre'])->name('sabre');
                        Route::get('/group-ticketing', [DevCpMonitoringController::class, 'groupTicketing'])->name('group-ticketing');
                        Route::get('/dashboards', [DevCpMonitoringController::class, 'dashboards'])->name('dashboards');
                        Route::get('/ui-versions', [DevCpUiVersionsController::class, 'index'])->name('ui-versions');
                        Route::get('/ui-layers', [DevCpUiLayersController::class, 'index'])->name('ui-layers');
                        Route::post('/ui-layers', [DevCpUiLayersController::class, 'update'])->name('ui-layers.update');
                        Route::get('/deployment', [DevCpMonitoringController::class, 'deployment'])->name('deployment');
                        Route::get('/modules', [PlatformModuleControlController::class, 'index'])->name('modules.index');
                        Route::post('/modules', [PlatformModuleControlController::class, 'update'])->name('modules.update');
                        Route::post('/modules/preset', [PlatformModuleControlController::class, 'applyPreset'])->name('modules.preset');
                        Route::post('/modules/package', [PlatformModuleControlController::class, 'applyPackage'])->name('modules.package');
                        Route::post('/modules/reset', [PlatformModuleControlController::class, 'reset'])->name('modules.reset');
                        Route::post('/modules/emergency-reset', [PlatformModuleControlController::class, 'emergencyReset'])->name('modules.emergency-reset');
                    });
                });

            app(ClientPrefixedRouteRegistrar::class)->register();
            app(ClientPageSettingsParityRouteRegistrar::class)->register();
            app(ClientMutatingFlowParityRouteRegistrar::class)->register();
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(UiVersionRoutePrefixMiddleware::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->appendToGroup('web', [
            ProtectClientUiPreview::class,
            ResolveClientUiVersion::class,
            EnsureGoogleOnboardingComplete::class,
            EnsurePasswordChanged::class,
        ]);

        $middleware->appendToPriorityList(
            StartSession::class,
            ProtectClientUiPreview::class,
        );

        $middleware->alias([
            'agency.context' => EnsureAgencyContext::class,
            'account.type' => EnsureAccountType::class,
            'agent.permission' => EnsureAgentPermission::class,
            'agent.admin' => EnsureAgentAdmin::class,
            'staff.permission' => EnsureStaffPermission::class,
            'customer.email.portal.verified' => EnsureCustomerEmailVerifiedForPortal::class,
            'developer.cp' => EnsureDeveloperControlPanelAccess::class,
            'platform.module' => EnsurePlatformModuleRouteEnabled::class,
            'preview.client' => ResolvePreviewClient::class,
            'preview.client.persist' => PersistClientPreviewContext::class,
            'client.ui.preview.protect' => ProtectClientUiPreview::class,
        ]);

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            ResolvePreviewClient::class,
        );

        $middleware->validateCsrfTokens(except: [
            'payments/abhipay/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof AuthenticationException) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Authentication required.'], 401);
                }

                if ($request->is('dev/cp', 'dev/cp/*', 'devcp', 'devcp/*')) {
                    return redirect()->guest(route('dev.cp.login'));
                }

                return redirect()->guest(client_route('login'));
            }

            if ($e instanceof \App\Services\Suppliers\OneApi\Exceptions\OneApiException) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $e->safeMessage,
                        'error' => $e->normalizedCode,
                    ], $e->httpStatus);
                }
            }

            if ($e instanceof PlatformModuleDisabledException) {
                if ($request->expectsJson() || ! $request->isMethodSafe()) {
                    return response()->json([
                        'message' => PlatformModuleDisabledException::PUBLIC_MESSAGE,
                    ], 403);
                }

                $moduleLabel = null;
                if ($request->user() !== null) {
                    $moduleLabel = PlatformModuleRegistry::find($e->moduleKey())?->label;
                }

                return response()->view('errors.module-disabled', [
                    'message' => PlatformModuleDisabledException::PUBLIC_MESSAGE,
                    'moduleLabel' => $moduleLabel,
                    'showSupportLink' => app(PlatformModuleEnforcer::class)->routeEnabled('support_system'),
                ], 403);
            }

            if ($e instanceof AuthorizationException) {
                $message = app()->environment('production')
                    ? 'You do not have permission to access this area.'
                    : ($e->getMessage() !== '' ? $e->getMessage() : 'You do not have permission to access this area.');

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'You do not have permission to access this area.'], 403);
                }

                return client_error_response('403', ['message' => $message], 403);
            }

            if ($e instanceof AccessDeniedHttpException) {
                $message = app()->environment('production')
                    ? 'You do not have permission to access this area.'
                    : ($e->getMessage() !== '' ? $e->getMessage() : 'You do not have permission to access this area.');

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'You do not have permission to access this area.'], 403);
                }

                return client_error_response('403', ['message' => $message], 403);
            }

            if ($e instanceof TokenMismatchException) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session expired. Please retry.'], 419);
                }

                return client_error_response('419', [], 419);
            }

            if ($e instanceof TooManyRequestsHttpException) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Too many requests. Please try again later.'], 429);
                }

                return client_error_response('429', [], 429);
            }

            if ($e instanceof QueryException) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Something went wrong on our side.'], 500);
                }

                return client_error_response('500', [], 500);
            }

            if ($request->expectsJson() && $e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => match ($e->getStatusCode()) {
                        401 => 'Authentication required.',
                        403 => 'You do not have permission to access this area.',
                        404 => 'The requested resource was not found.',
                        419 => 'Session expired. Please retry.',
                        429 => 'Too many requests. Please try again later.',
                        503 => 'Service is temporarily unavailable.',
                        default => 'Something went wrong on our side.',
                    },
                ], $e->getStatusCode());
            }

            if ($e instanceof ValidationException) {
                return null;
            }

            if (! $request->expectsJson()) {
                $resolver = app(ClientErrorResponseResolver::class);

                if ($e instanceof HttpExceptionInterface && $resolver->supportsStatus($e->getStatusCode())) {
                    return $resolver->fromHttpException($e);
                }

                if (! config('app.debug')) {
                    return $resolver->response('500', [], 500);
                }
            }

            return null;
        });
    })->create();
