<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Iati\IatiBalanceService;
use Illuminate\Console\Command;

class IatiBalanceCommand extends Command
{
    protected $signature = 'iati:balance {--connection=}';

    protected $description = 'Check IATI balance/credit if endpoint exists';

    public function handle(IatiBalanceService $balanceService): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No IATI SupplierConnection found.');

            return self::FAILURE;
        }

        $result = $balanceService->checkBalance($connection);
        $this->line('supported='.($result['supported'] ? 'true' : 'false'));
        $this->line('balance='.($result['balance'] !== null ? (string) $result['balance'] : 'n/a'));
        $this->line('currency='.($result['currency'] ?? 'n/a'));
        $this->line('message='.$result['message']);

        return self::SUCCESS;
    }

    protected function resolveConnection(): ?SupplierConnection
    {
        $id = $this->option('connection');
        if ($id) {
            return SupplierConnection::query()->where('id', (int) $id)->where('provider', SupplierProvider::Iati)->first();
        }

        return SupplierConnection::query()->where('provider', SupplierProvider::Iati)->orderByDesc('is_active')->first();
    }
}
