<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Services\Integrations\IntegrationHubService;
use App\Services\Integrations\IntegrationManagerResolver;
use App\Support\Integrations\IntegrationAuthorization;
use App\Support\Integrations\IntegrationRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Admin Integrations control plane (JSON for Next dashboard + redirects for HTML).
 */
class IntegrationsController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        private readonly IntegrationHubService $hub,
        private readonly IntegrationManagerResolver $managers,
    ) {}

    public function index(Request $request): JsonResponse|RedirectResponse
    {
        IntegrationAuthorization::assert($request->user(), IntegrationAuthorization::VIEW);

        if (! $this->wantsBackOfficeJson($request)) {
            return redirect()->to('/admin/dashboard/integrations');
        }

        $agencyId = $request->filled('agency_id') ? $request->integer('agency_id') : null;

        return $this->backOfficeJson([
            'ok' => true,
            'hub' => $this->hub->overview($request->query('category'), $agencyId),
            'permissions' => [
                'view' => IntegrationAuthorization::can($request->user(), IntegrationAuthorization::VIEW),
                'manage' => IntegrationAuthorization::can($request->user(), IntegrationAuthorization::MANAGE),
                'test' => IntegrationAuthorization::can($request->user(), IntegrationAuthorization::TEST),
                'activate' => IntegrationAuthorization::can($request->user(), IntegrationAuthorization::ACTIVATE),
                'test_payment' => IntegrationAuthorization::can($request->user(), IntegrationAuthorization::TEST_PAYMENT),
                'audit' => IntegrationAuthorization::can($request->user(), IntegrationAuthorization::AUDIT),
            ],
        ]);
    }

    public function show(Request $request, string $code): JsonResponse|RedirectResponse
    {
        IntegrationAuthorization::assert($request->user(), IntegrationAuthorization::VIEW);
        $definition = IntegrationRegistry::find($code);
        abort_if($definition === null, 404);

        if (! $this->wantsBackOfficeJson($request)) {
            return redirect()->to('/admin/dashboard/integrations?provider='.urlencode($code));
        }

        $agencyId = $request->filled('agency_id') ? $request->integer('agency_id') : null;
        $manager = $this->managers->forDefinition($definition);

        return $this->backOfficeJson([
            'ok' => true,
            'integration' => array_merge($definition->toArray(), [
                'status' => $manager->getStatus($agencyId)->value,
                'status_label' => $manager->getStatus($agencyId)->label(),
                'summary' => $manager->getConfigurationSummary($agencyId),
                'settings' => $manager->getSettingsDefinition(),
                'health' => $manager->getHealth($agencyId),
                'supports_test_transaction' => $manager->supportsTestTransaction(),
            ]),
        ]);
    }

    public function update(Request $request, string $code): JsonResponse
    {
        IntegrationAuthorization::assert($request->user(), IntegrationAuthorization::MANAGE);
        $manager = $this->managers->resolve($code);
        $agencyId = $request->filled('agency_id') ? $request->integer('agency_id') : null;

        $data = $this->validateBackOffice($request, [
            'is_active' => ['nullable', 'boolean'],
            'environment' => ['nullable', 'string', 'max:16'],
            'merchant_id' => ['nullable', 'string', 'max:120'],
            'merchant_secret_key' => ['nullable', 'string', 'max:255'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'success_url' => ['nullable', 'url', 'max:255'],
            'cancel_url' => ['nullable', 'url', 'max:255'],
            'decline_url' => ['nullable', 'url', 'max:255'],
            'agency_id' => ['nullable', 'integer'],
        ]);

        try {
            $presented = $manager->saveSettings($request->user(), $data, $agencyId);
        } catch (Throwable $e) {
            return $this->backOfficeJsonError($e->getMessage(), 422, 'integration_save_failed');
        }

        return $this->backOfficeJson([
            'ok' => true,
            'message' => 'Integration settings saved.',
            'configuration' => $presented,
            'status' => $manager->getStatus($agencyId)->value,
        ]);
    }

    public function activate(Request $request, string $code): JsonResponse
    {
        IntegrationAuthorization::assert($request->user(), IntegrationAuthorization::ACTIVATE);
        $manager = $this->managers->resolve($code);
        $agencyId = $request->filled('agency_id') ? $request->integer('agency_id') : null;

        try {
            $manager->activate($request->user(), $agencyId);
        } catch (Throwable $e) {
            return $this->backOfficeJsonError($e->getMessage(), 422, 'integration_activate_failed');
        }

        return $this->backOfficeJson([
            'ok' => true,
            'message' => 'Integration enabled.',
            'status' => $manager->getStatus($agencyId)->value,
            'summary' => $manager->getConfigurationSummary($agencyId),
        ]);
    }

    public function deactivate(Request $request, string $code): JsonResponse
    {
        IntegrationAuthorization::assert($request->user(), IntegrationAuthorization::ACTIVATE);
        $manager = $this->managers->resolve($code);
        $agencyId = $request->filled('agency_id') ? $request->integer('agency_id') : null;

        try {
            $manager->deactivate($request->user(), $agencyId);
        } catch (Throwable $e) {
            return $this->backOfficeJsonError($e->getMessage(), 422, 'integration_deactivate_failed');
        }

        return $this->backOfficeJson([
            'ok' => true,
            'message' => 'Integration disabled.',
            'status' => $manager->getStatus($agencyId)->value,
            'summary' => $manager->getConfigurationSummary($agencyId),
        ]);
    }

    public function testConnection(Request $request, string $code): JsonResponse
    {
        IntegrationAuthorization::assert($request->user(), IntegrationAuthorization::TEST);
        $manager = $this->managers->resolve($code);
        $agencyId = $request->filled('agency_id') ? $request->integer('agency_id') : null;

        try {
            $result = $manager->testConnection($request->user(), $agencyId);
        } catch (RuntimeException $e) {
            return $this->backOfficeJsonError($e->getMessage(), 429, 'integration_test_throttled');
        } catch (Throwable $e) {
            return $this->backOfficeJsonError($e->getMessage(), 422, 'integration_test_failed');
        }

        return $this->backOfficeJson([
            'ok' => (bool) ($result['ok'] ?? false),
            'result' => $result,
            'health' => $manager->getHealth($agencyId),
            'status' => $manager->getStatus($agencyId)->value,
        ]);
    }

    public function testPayment(Request $request, string $code): JsonResponse
    {
        IntegrationAuthorization::assert($request->user(), IntegrationAuthorization::TEST_PAYMENT);
        $manager = $this->managers->resolve($code);
        if (! $manager->supportsTestTransaction()) {
            return $this->backOfficeJsonError('Test payment is not supported for this integration.', 422, 'unsupported');
        }

        $agencyId = $request->filled('agency_id') ? $request->integer('agency_id') : null;
        $data = $this->validateBackOffice($request, [
            'amount' => ['nullable', 'numeric', 'min:1'],
            'confirm' => ['accepted'],
            'agency_id' => ['nullable', 'integer'],
        ]);

        try {
            $result = $manager->createTestTransaction($request->user(), $data, $agencyId);
        } catch (Throwable $e) {
            return $this->backOfficeJsonError($e->getMessage(), 422, 'integration_test_payment_failed');
        }

        return $this->backOfficeJson([
            'ok' => (bool) ($result['ok'] ?? false),
            'result' => $result,
        ]);
    }

    public function health(Request $request, string $code): JsonResponse
    {
        IntegrationAuthorization::assert($request->user(), IntegrationAuthorization::VIEW);
        $manager = $this->managers->resolve($code);
        $agencyId = $request->filled('agency_id') ? $request->integer('agency_id') : null;

        return $this->backOfficeJson([
            'ok' => true,
            'health' => $manager->getHealth($agencyId),
        ]);
    }

    public function docs(Request $request, string $code): JsonResponse|RedirectResponse
    {
        IntegrationAuthorization::assert($request->user(), IntegrationAuthorization::VIEW);
        $definition = IntegrationRegistry::find($code);
        abort_if($definition === null, 404);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'docs_url' => $definition->docsUrl,
                'internal_only' => is_string($definition->docsUrl) && str_starts_with($definition->docsUrl, '/docs/'),
            ]);
        }

        if (filled($definition->docsUrl)) {
            return redirect()->away($definition->docsUrl);
        }

        return redirect()->to('/admin/dashboard/integrations?provider='.urlencode($code));
    }
}
