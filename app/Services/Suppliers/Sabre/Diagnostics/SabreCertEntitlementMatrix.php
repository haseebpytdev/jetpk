<?php

namespace App\Services\Suppliers\Sabre\Diagnostics;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreBookingClient;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * CERT-focused Sabre REST entitlement matrix (empty/minimal probes only; no cancel/PNR side effects).
 */
final class SabreCertEntitlementMatrix
{
    public const MATRIX_VERSION = 'cert_entitlement_v1';

    /** @var list<array{endpoint: string, method: string, label: string, probe_body: string}> */
    public const ENDPOINTS = [
        ['endpoint' => '/v2/auth/token', 'method' => 'POST', 'label' => 'oauth_token', 'probe_body' => 'oauth'],
        ['endpoint' => '/v4/offers/shop', 'method' => 'POST', 'label' => 'shop_v4', 'probe_body' => '{}'],
        ['endpoint' => '/v5/offers/shop', 'method' => 'POST', 'label' => 'shop_v5', 'probe_body' => '{}'],
        ['endpoint' => '/v4/shop/flights/revalidate', 'method' => 'POST', 'label' => 'revalidate_v4', 'probe_body' => '{}'],
        ['endpoint' => '/v2.4.0/passenger/records?mode=create', 'method' => 'POST', 'label' => 'cpnr_v24_create', 'probe_body' => '{}'],
        ['endpoint' => '/v2/passengers/create', 'method' => 'POST', 'label' => 'passengers_create_v2', 'probe_body' => '{}'],
        ['endpoint' => '/v1/trip/orders/getBooking', 'method' => 'POST', 'label' => 'trip_orders_get_booking', 'probe_body' => '{}'],
        ['endpoint' => '/v1/trip/orders/cancelBooking', 'method' => 'POST', 'label' => 'trip_orders_cancel_booking', 'probe_body' => '{}'],
        ['endpoint' => '/v1/offers/price', 'method' => 'POST', 'label' => 'ndc_offers_price', 'probe_body' => '{}'],
        ['endpoint' => '/v1/orders/create', 'method' => 'POST', 'label' => 'ndc_orders_create', 'probe_body' => '{}'],
    ];

    public function __construct(
        protected SabreClient $sabreClient,
        protected SabreBookingClient $bookingClient,
        protected SabrePnrCertificationSupport $certificationSupport,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(SupplierConnection $connection, bool $send, int $maxCalls): array
    {
        $maxCalls = max(1, $maxCalls);
        $budget = new SabrePccCapabilityCallBudget($maxCalls);
        $baseUrlContext = SabreInspectGate::resolveSabreBaseUrlContext($connection);
        $host = (string) ($baseUrlContext['resolved_base_host'] ?? 'unknown');
        $tokenPath = (string) config('suppliers.sabre.token_path', '/v2/auth/token');
        if ($tokenPath !== '' && ! str_starts_with($tokenPath, '/')) {
            $tokenPath = '/'.$tokenPath;
        }

        $rows = [];
        foreach (self::ENDPOINTS as $def) {
            $endpoint = $def['endpoint'] === '/v2/auth/token' ? $tokenPath : $def['endpoint'];
            $row = $this->baseRow($def, $endpoint, $send);
            if ($send && $budget->canConsume()) {
                if ($def['probe_body'] === 'oauth') {
                    $row = array_merge($row, $this->probeOAuth($connection));
                } else {
                    $row = array_merge($row, $this->postEmptyProbe($connection, $endpoint));
                }
                $row['live_call_attempted'] = true;
                $row['probe_body'] = $def['probe_body'] === 'oauth' ? 'oauth_token_request' : 'empty_json_object';
                $budget->consume(1);
            }
            $row = $this->finalizeRow($row, $endpoint);
            $rows[] = SensitiveDataRedactor::redact($row);
        }

        $payload = SensitiveDataRedactor::redact([
            'matrix_version' => self::MATRIX_VERSION,
            'connection_id' => $connection->id,
            'base_host' => $host !== '' ? $host : 'unknown',
            'resolved_base_host' => $host !== '' ? $host : 'unknown',
            'base_url_resolution' => $baseUrlContext,
            'environment_label' => $this->inferEnvironmentLabel(
                SabreInspectGate::resolveSabreBaseUrlForGate($connection),
            ),
            'inspect_only' => ! $send,
            'live_call_attempted' => $send,
            'max_calls' => $maxCalls,
            'calls_made' => $budget->used(),
            'ticketing_enabled_config' => (bool) config('suppliers.sabre.ticketing_enabled', false),
            'rows' => $rows,
            'summary' => [
                'endpoint_count' => count($rows),
                'probed_count' => count(array_filter($rows, static fn (array $r): bool => ($r['live_call_attempted'] ?? false) === true)),
            ],
        ]);

        $this->certificationSupport->assertOutputSafe($payload);

        return $payload;
    }

    public static function resolveConnection(?int $connectionId): ?SupplierConnection
    {
        return SabrePccCapabilityMatrix::resolveConnection($connectionId, null);
    }

    /**
     * @param  array{endpoint: string, method: string, label: string, probe_body: string}  $def
     * @return array<string, mixed>
     */
    protected function baseRow(array $def, string $endpoint, bool $send): array
    {
        $isCancel = str_contains(strtolower($endpoint), 'cancelbooking');

        return [
            'label' => $def['label'],
            'endpoint' => $endpoint,
            'method' => strtoupper($def['method']),
            'http_status' => $send ? 0 : 'not_sent',
            'sabre_error_code' => '',
            'safe_message' => '',
            'access_result' => $send ? 'pending' : 'inspect_only',
            'entitled_guess' => null,
            'recommended_next_action' => $send ? '' : 'Re-run with --send to probe live (empty/minimal only).',
            'live_call_attempted' => false,
            'probe_body' => null,
            'destructive_action' => false,
            'empty_probe_only' => $isCancel || $def['probe_body'] === '{}',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function probeOAuth(SupplierConnection $connection): array
    {
        try {
            $this->sabreClient->getAccessToken($connection);

            return [
                'http_status' => 200,
                'sabre_error_code' => '',
                'safe_message' => 'token_obtained',
                'access_result' => 'ready',
            ];
        } catch (Throwable $e) {
            return [
                'http_status' => 0,
                'sabre_error_code' => 'oauth_failed',
                'safe_message' => substr($e->getMessage(), 0, 120),
                'access_result' => 'transport_error',
            ];
        }
    }

    /**
     * @return array{http_status: int|string, sabre_error_code: string, safe_message: string, access_result: string}
     */
    protected function postEmptyProbe(SupplierConnection $connection, string $path): array
    {
        try {
            $token = $this->sabreClient->getAccessToken($connection);
        } catch (Throwable) {
            return [
                'http_status' => 0,
                'sabre_error_code' => 'oauth_failed',
                'safe_message' => 'oauth_failed',
                'access_result' => 'transport_error',
            ];
        }

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $timeouts = $this->sabreClient->httpTimeoutSettings();
        $url = $base.($path[0] === '/' ? $path : '/'.$path);
        $httpStatus = 0;
        $transport = null;
        $arr = [];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout($timeouts['timeout_seconds'])
                ->connectTimeout($timeouts['connect_timeout_seconds'])
                ->withBody('{}', 'application/json')
                ->post($url);
            $httpStatus = $response->status();
            $json = $response->json();
            $arr = is_array($json) ? $json : [];
        } catch (ConnectionException $e) {
            $transport = str_contains(strtolower($e->getMessage()), 'timeout') ? 'timeout' : 'network';
        } catch (Throwable) {
            $transport = 'network';
        }

        $digest = $arr !== [] ? $this->bookingClient->digestBookingResponseJsonForProbe($arr) : [];
        $safeCode = $this->firstDigestCode($digest);
        $safeMsg = $this->firstDigestMessage($digest);
        $access = SabrePccCapabilityMatrix::classifyMatrixAccessResult($httpStatus, $transport, $path, $safeCode, $safeMsg);

        return [
            'http_status' => $httpStatus,
            'sabre_error_code' => $safeCode,
            'safe_message' => $safeMsg,
            'access_result' => $access,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function finalizeRow(array $row, string $endpoint): array
    {
        $access = (string) ($row['access_result'] ?? 'inspect_only');
        $httpStatus = is_numeric($row['http_status'] ?? null) ? (int) $row['http_status'] : 0;
        $row['entitled_guess'] = self::entitledGuess($access, $httpStatus);
        $row['recommended_next_action'] = self::recommendedNextAction($access, $endpoint);

        return $row;
    }

    public static function entitledGuess(string $accessResult, int $httpStatus): ?bool
    {
        if ($accessResult === 'inspect_only' || $accessResult === 'pending') {
            return null;
        }

        return match ($accessResult) {
            'ready', 'reachable_validation_error', 'profile_configuration_error', 'host_application_error' => true,
            'not_authorized', 'forbidden', 'entitlement_missing', 'not_found', 'method_not_allowed' => false,
            'transport_error' => null,
            default => $httpStatus >= 200 && $httpStatus < 500
              && ! in_array($accessResult, ['not_authorized', 'forbidden', 'entitlement_missing'], true)
              ? true
              : null,
        };
    }

    public static function recommendedNextAction(string $accessResult, string $endpoint): string
    {
        $ep = strtolower($endpoint);

        return match ($accessResult) {
            'inspect_only' => 'Re-run with --send to probe live (empty/minimal only).',
            'not_authorized', 'forbidden', 'entitlement_missing' => 'Request Sabre entitlement for this endpoint from your account team.',
            'not_found' => str_contains($ep, '/v1/offers/price') || str_contains($ep, '/v1/orders/create')
              ? 'NDC endpoint not found — confirm NDC entitlement or correct API version with Sabre.'
              : 'Verify endpoint path/version; may not be provisioned for this tenant.',
            'reachable_validation_error' => 'Endpoint reachable; run structured cert flow with real payloads when ready.',
            'ready' => 'Endpoint acknowledged empty probe; proceed with structured cert flow.',
            'profile_configuration_error' => 'Fix PCC/agency profile configuration (e.g. agency phone) before live booking cert.',
            'host_application_error' => 'Host returned application error — review fare/itinerary rules with Sabre support.',
            'transport_error' => 'Check network, CERT base URL, and OAuth credentials before re-probing.',
            default => 'Review sabre_error_code and Sabre support documentation.',
        };
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    protected function firstDigestCode(array $digest): string
    {
        $top = isset($digest['response_top_level_error_code']) && is_string($digest['response_top_level_error_code'])
          ? $digest['response_top_level_error_code'] : '';
        if ($top !== '') {
            return substr($top, 0, 120);
        }
        $codes = (array) ($digest['response_error_codes'] ?? []);

        return isset($codes[0]) && is_string($codes[0]) ? substr($codes[0], 0, 120) : '';
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    protected function firstDigestMessage(array $digest): string
    {
        $top = isset($digest['response_top_level_message']) && is_string($digest['response_top_level_message'])
          ? $digest['response_top_level_message'] : '';
        if ($top !== '') {
            return substr($top, 0, 200);
        }
        $messages = (array) ($digest['response_error_messages'] ?? []);

        return isset($messages[0]) && is_string($messages[0]) ? substr($messages[0], 0, 200) : '';
    }

    protected function inferEnvironmentLabel(string $baseUrl): string
    {
        $lower = strtolower($baseUrl);
        if (str_contains($lower, 'cert') || str_contains($lower, 'test') || str_contains($lower, 'sws-crt')) {
            return 'cert';
        }

        return 'live';
    }
}
