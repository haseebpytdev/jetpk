<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiAuthService;
use App\Services\Suppliers\Iati\IatiClient;
use App\Services\Suppliers\Iati\IatiConfigResolver;
use Illuminate\Console\Command;

class IatiHealthCommand extends Command
{
    protected $signature = 'iati:health {--connection= : Supplier connection ID}';

    protected $description = 'Check IATI credentials, ping (auth_code Bearer), and optional JWT token exchange';

    public function handle(
        IatiAuthService $authService,
        IatiClient $client,
        IatiConfigResolver $configResolver,
    ): int {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No active IATI SupplierConnection found.');

            return self::FAILURE;
        }

        $config = $configResolver->resolve($connection);
        $usesJwt = $authService->usesJwtExchange($connection);
        $this->line('connection_id='.$connection->id);
        $this->line('environment='.$config['environment']);
        $this->line('is_test='.($config['is_test'] ? 'true' : 'false'));
        $this->line('flight_base='.$config['flight_base']);
        $this->line('organization_id='.($config['organization_id'] !== '' ? $config['organization_id'] : '(not set)'));
        $this->line('ping_bearer_mode=auth_code');
        $this->line('flight_bearer_mode='.($usesJwt ? 'jwt_exchange' : 'auth_code'));

        try {
            $authService->getPingBearerToken($connection);
            $this->info('auth_code=ok');
        } catch (\Throwable $e) {
            $this->error('auth_code=failed message='.$e->getMessage());

            return self::FAILURE;
        }

        if ($usesJwt) {
            try {
                $authService->getBearerToken($connection);
                $this->info('token_exchange=ok');
            } catch (\Throwable $e) {
                $this->error('token_exchange=failed message='.$e->getMessage());

                return self::FAILURE;
            }
        } else {
            $this->line('token_exchange=skipped reason=no_secret');
        }

        try {
            $client->get($connection, '/test/ping', ['request_context' => 'health_ping']);
            $this->info('ping=ok');
        } catch (\Throwable $e) {
            $this->error('ping=failed message='.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id !== null && $id !== '') {
            return SupplierConnection::query()
                ->where('id', (int) $id)
                ->where('provider', SupplierProvider::Iati)
                ->first();
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::Iati)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();
    }
}
