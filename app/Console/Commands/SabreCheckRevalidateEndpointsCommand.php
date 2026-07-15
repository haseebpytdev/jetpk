<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabreRevalidationPayloadBuilder;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * [local/testing only] OAuth once, then POST {@code {}} to candidate Sabre revalidate/shop paths. Classifies reachability
 * without raw bodies, bearer tokens, PCC, or passenger data. Does not create PNRs or issue tickets.
 */
class SabreCheckRevalidateEndpointsCommand extends Command
{
    protected $signature = 'sabre:check-revalidate-endpoints
                            {--connection= : Supplier connection ID (Sabre); defaults to first Sabre connection}';

    protected $description = '[local/testing only] Probe Sabre revalidate-related REST paths (POST {} only; no booking)';

    public function handle(SabreClient $client, SabreRevalidationPayloadBuilder $builder): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $connectionId = $this->option('connection');
        $query = SupplierConnection::query()->where('provider', SupplierProvider::Sabre);

        if ($connectionId !== null && $connectionId !== '') {
            $query->whereKey((int) $connectionId);
        }

        $connection = $query->orderBy('id')->first();

        if ($connection === null) {
            $this->components->error('No Sabre supplier connection found. Create one in API settings or pass --connection=');

            return self::FAILURE;
        }

        $this->line('Connectivity probe only: POST {} (empty JSON object), no PNR, no ticketing, no raw response body.');
        $this->newLine();

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
        $this->line('base_host='.(is_string($host) && $host !== '' ? $host : 'unknown'));
        $this->line('connection_id='.$connection->id);
        $this->newLine();

        $configuredRevalidate = (string) config('suppliers.sabre.revalidate_path', '/v4/shop/flights/revalidate');
        $configuredRevalidate = $configuredRevalidate !== '' && $configuredRevalidate[0] === '/' ? $configuredRevalidate : '/'.$configuredRevalidate;

        /** @var list<array{label: string, method: string, path: string}> */
        $rawChecks = [
            ['label' => 'Configured revalidate_path', 'method' => 'POST', 'path' => $configuredRevalidate],
            ['label' => 'BFM v4 shop flights revalidate', 'method' => 'POST', 'path' => '/v4/shop/flights/revalidate'],
            ['label' => 'Offers v4 shop revalidate', 'method' => 'POST', 'path' => '/v4/offers/shop/revalidate'],
            ['label' => 'Offers v4 shop', 'method' => 'POST', 'path' => '/v4/offers/shop'],
            ['label' => 'Offers v5 shop revalidate', 'method' => 'POST', 'path' => '/v5/offers/shop/revalidate'],
            ['label' => 'Offers v5 shop', 'method' => 'POST', 'path' => '/v5/offers/shop'],
            ['label' => 'BFM v1 shop flights revalidate', 'method' => 'POST', 'path' => '/v1/shop/flights/revalidate'],
            ['label' => 'BFM v1 shop flights fares', 'method' => 'POST', 'path' => '/v1/shop/flights/fares'],
            ['label' => 'Trip orders createBooking (booking endpoint, not revalidate)', 'method' => 'POST', 'path' => '/v1/trip/orders/createBooking'],
        ];

        $seen = [];
        $checks = [];
        foreach ($rawChecks as $row) {
            $p = $row['path'];
            if ($p !== '' && ! str_starts_with($p, '/')) {
                $p = '/'.$p;
            }
            $key = strtoupper($row['method']).' '.$p;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $checks[] = ['label' => $row['label'], 'method' => $row['method'], 'path' => $p];
        }

        try {
            $client->getAccessToken($connection);
        } catch (Throwable $e) {
            $this->components->error('Authentication failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $timeout = (int) config('suppliers.sabre.timeout_seconds', 30);
        $connectTimeout = (int) config('suppliers.sabre.connect_timeout_seconds', 10);

        foreach ($checks as $check) {
            $method = strtoupper($check['method']);
            $path = $check['path'];
            $url = $base.$path;
            $httpStatus = 0;
            $timedOut = false;
            $arr = [];

            try {
                $response = Http::withToken($client->getAccessToken($connection))
                    ->acceptJson()
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withBody('{}', 'application/json')
                    ->post($url);
                $httpStatus = $response->status();
                $json = $response->json();
                $arr = is_array($json) ? $json : [];
            } catch (ConnectionException) {
                $timedOut = true;
            } catch (Throwable) {
                $httpStatus = 0;
            }

            $accessResult = self::accessResultForProbe($httpStatus, $timedOut);
            $digest = ($httpStatus > 0 && ! in_array($httpStatus, [200, 201], true))
                ? $builder->extractSafeErrorDigest($arr)
                : [];
            $codes = isset($digest['response_error_codes']) && is_array($digest['response_error_codes'])
                ? array_slice($digest['response_error_codes'], 0, 4) : [];
            $messages = isset($digest['response_error_messages']) && is_array($digest['response_error_messages'])
                ? array_slice($digest['response_error_messages'], 0, 2) : [];
            $safeCode = isset($codes[0]) ? substr((string) $codes[0], 0, 32) : '';
            $safeMsg = isset($messages[0]) ? substr((string) $messages[0], 0, 160) : '';

            $this->line('label='.$check['label']);
            $this->line('endpoint_path='.$path);
            $this->line('method='.$method);
            $this->line('http_status='.$httpStatus);
            $this->line('access_result='.$accessResult);
            $this->line('safe_error_code='.$safeCode);
            $this->line('safe_error_message='.$safeMsg);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * Map probe HTTP outcome to a stable label (no response bodies printed by caller).
     */
    public static function accessResultForProbe(int $httpStatus, bool $timedOut): string
    {
        if ($timedOut) {
            return 'timeout';
        }
        if ($httpStatus === 0) {
            return 'unknown';
        }
        if (in_array($httpStatus, [200, 201], true)) {
            return 'ready';
        }
        if (in_array($httpStatus, [400, 422], true)) {
            return 'reachable_validation_error';
        }
        if ($httpStatus === 403) {
            return 'forbidden';
        }
        if ($httpStatus === 404) {
            return 'not_found';
        }
        if ($httpStatus === 405) {
            return 'method_not_allowed';
        }

        return 'unknown';
    }
}
