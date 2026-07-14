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

class SabreCheckBookingEndpointsCommand extends Command
{
    protected $signature = 'sabre:check-booking-endpoints
                            {--connection= : Supplier connection ID (Sabre); defaults to first Sabre connection}';

    protected $description = '[local/testing only] Probe Sabre booking REST paths (empty JSON POST where applicable; not a real booking)';

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

        $this->line('Connectivity probe only: empty JSON body on POST probes, no PNR creation, no ticketing.');
        $this->newLine();

        $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
        $host = parse_url(str_contains($base, '://') ? $base : 'https://'.$base, PHP_URL_HOST);
        $this->line('base_host='.(is_string($host) && $host !== '' ? $host : 'unknown'));
        $this->line('connection_id='.$connection->id);
        $this->newLine();

        $configuredPath = (string) config('suppliers.sabre.booking_path', '/v2/passengers/create');
        if ($configuredPath !== '' && ! str_starts_with($configuredPath, '/')) {
            $configuredPath = '/'.$configuredPath;
        }

        /** @var list<array{label: string, method: string, path: string}> */
        $rawChecks = [
            ['label' => 'Configured booking_path', 'method' => 'POST', 'path' => $configuredPath],
            ['label' => 'Flight booking (v2 passengers create)', 'method' => 'POST', 'path' => '/v2/passengers/create'],
            ['label' => 'Passenger create singular (v2)', 'method' => 'POST', 'path' => '/v2/passenger/create'],
            ['label' => 'Passenger records v2.5.0', 'method' => 'POST', 'path' => '/v2.5.0/passenger/records'],
            ['label' => 'Passenger records v2.4.0', 'method' => 'POST', 'path' => '/v2.4.0/passenger/records'],
            ['label' => 'Trip orders createBooking', 'method' => 'POST', 'path' => '/v1/trip/orders/createBooking'],
            ['label' => 'Trip orders base', 'method' => 'POST', 'path' => '/v1/trip/orders'],
            ['label' => 'Trip orders getBooking', 'method' => 'POST', 'path' => '/v1/trip/orders/getBooking'],
        ];

        $seenPaths = [];
        $checks = [];
        foreach ($rawChecks as $row) {
            $p = $row['path'];
            if ($p !== '' && ! str_starts_with($p, '/')) {
                $p = '/'.$p;
            }
            $key = strtoupper($row['method']).' '.$p;
            if (isset($seenPaths[$key])) {
                continue;
            }
            $seenPaths[$key] = true;
            $checks[] = ['label' => $row['label'], 'method' => $row['method'], 'path' => $p];
        }

        try {
            $token = $client->getAccessToken($connection);
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
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withBody('{}', 'application/json')
                    ->post($url);
                $httpStatus = $response->status();
            } catch (ConnectionException) {
                $httpStatus = 0;
            } catch (Throwable) {
                $httpStatus = 0;
            }

            $available = $httpStatus > 0;
            $ready = $httpStatus === 200 || $httpStatus === 201;
            $accessResult = self::accessResultForStatus($httpStatus);

            $this->line('label='.$check['label']);
            $this->line('endpoint_path='.$path);
            $this->line('method='.$method);
            $this->line('http_status='.$httpStatus);
            $this->line('available='.($available ? 'yes' : 'no'));
            $this->line('ready='.($ready ? 'yes' : 'no'));
            $this->line('access_result='.$accessResult);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * Map HTTP status to a stable probe label (no response bodies).
     */
    public static function accessResultForStatus(int $httpStatus): string
    {
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
