<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway;
use App\Services\Payments\PaymentGatewaySettingsService;
use Illuminate\Console\Command;

class AbhiPayStatusCommand extends Command
{
    protected $signature = 'payments:abhipay-status {--agency_id=}';

    protected $description = 'Show AbhiPay gateway configuration status (no secrets printed).';

    public function handle(PaymentGatewaySettingsService $settingsService): int
    {
        $agencyId = $this->option('agency_id') !== null ? (int) $this->option('agency_id') : null;
        $gateway = $settingsService->findOrNewAbhiPay($agencyId);

        $this->table(['Field', 'Value'], [
            ['Code', $gateway->code ?? PaymentGateway::CODE_ABHIPAY],
            ['Agency ID', (string) ($gateway->agency_id ?? 'platform')],
            ['Active', $gateway->is_active ? 'yes' : 'no'],
            ['Environment', (string) $gateway->environment],
            ['Merchant ID', $gateway->maskedMerchantId() ?? 'not set'],
            ['Secret configured', $gateway->hasMerchantSecretKey() ? 'yes' : 'no'],
            ['Base URL', (string) ($gateway->base_url ?: PaymentGateway::DEFAULT_BASE_URL)],
            ['Checkout ready', $gateway->isAvailableForCheckout() ? 'yes' : 'no'],
        ]);

        return self::SUCCESS;
    }
}
