<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class SabreCheckServicesCommand extends Command
{
    protected $signature = 'sabre:check-services
                            {--connection= : Supplier connection ID (Sabre); defaults to first Sabre connection}';

    protected $description = '[local/testing only] Probe Sabre API endpoint reachability (not a real flight search)';

    public function handle(SabreClient $client): int
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

        $this->line('This is an endpoint availability check, not a real flight search.');
        $this->newLine();

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
        $this->line('base_host='.(is_string($host) && $host !== '' ? $host : 'unknown'));
        $this->line('connection_id='.$connection->id);
        $this->newLine();

        $shopPath = (string) config('suppliers.sabre.shop_path', '/v4/offers/shop');
        if ($shopPath !== '' && ! str_starts_with($shopPath, '/')) {
            $shopPath = '/'.$shopPath;
        }

        /** @var list<array{label: string, method: string, path: string}> */
        $checks = [
            ['label' => 'Offers Shop (BFM)', 'method' => 'POST', 'path' => $shopPath],
            ['label' => 'Passengers Create', 'method' => 'POST', 'path' => '/v2/passengers/create'],
            ['label' => 'Shop Flights Fares', 'method' => 'POST', 'path' => '/v1/shop/flights/fares'],
            ['label' => 'Flight Status', 'method' => 'GET', 'path' => '/v1/flight/status'],
            ['label' => 'Lists Utilities Airports', 'method' => 'GET', 'path' => '/v1/lists/utilities/airports'],
            ['label' => 'Lists Utilities Airlines', 'method' => 'GET', 'path' => '/v1/lists/utilities/airlines'],
        ];

        try {
            $token = $client->getAccessToken($connection);
        } catch (Throwable) {
            $this->components->error('Authentication failed.');
            $this->line('auth_error_code=token_request_failed');
            $this->line('token_present=false');
            $this->line('pcc_present='.(trim((string) data_get($connection->credentials ?? [], 'pcc', '')) !== '' ? 'true' : 'false'));

            return self::FAILURE;
        }

        $timeout = (int) config('suppliers.sabre.timeout_seconds', 30);
        $connectTimeout = (int) config('suppliers.sabre.connect_timeout_seconds', 10);

        foreach ($checks as $check) {
            $method = strtoupper($check['method']);
            $path = $check['path'];
            if ($path !== '' && ! str_starts_with($path, '/')) {
                $path = '/'.$path;
            }
            $url = $base.$path;

            $httpStatus = 0;
            try {
                if ($method === 'GET') {
                    $response = Http::withToken($token)
                        ->acceptJson()
                        ->timeout($timeout)
                        ->connectTimeout($connectTimeout)
                        ->get($url);
                } else {
                    $response = Http::withToken($token)
                        ->acceptJson()
                        ->timeout($timeout)
                        ->connectTimeout($connectTimeout)
                        ->withBody('{}', 'application/json')
                        ->send($method, $url);
                }
                $httpStatus = $response->status();
            } catch (ConnectionException) {
                $httpStatus = 0;
            } catch (Throwable) {
                $httpStatus = 0;
            }

            $available = $httpStatus > 0;
            $ready = $httpStatus === 200 || $httpStatus === 201;

            $this->line('service='.$check['label']);
            $this->line('endpoint='.$path);
            $this->line('method='.$method);
            $this->line('http_status='.$httpStatus);
            $this->line('available='.($available ? 'yes' : 'no'));
            $this->line('ready='.($ready ? 'yes' : 'no'));
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
