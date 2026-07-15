<?php

namespace App\Console\Commands;

use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Universal admin-confirmed supplier create with explicit strategy (delegates to provider command).
 */
class SupplierCreateWithStrategyCommand extends Command
{
    public const CONFIRM_PHRASE = 'CREATE-SUPPLIER-PNR-WITH-STRATEGY';

    public const PRODUCTION_OPS_APPROVAL_PHRASE = 'APPROVE-PRODUCTION-SUPPLIER-CREATE-WITH-STRATEGY';

    protected $signature = 'supplier:create-with-strategy
                            {--booking= : Booking ID}
                            {--provider=sabre : Supplier provider}
                            {--action=create_pnr : Lifecycle action}
                            {--strategy= : Strategy code}
                            {--confirm= : Required confirmation phrase}
                            {--production-ops-approval= : Production only: APPROVE-PRODUCTION-SUPPLIER-CREATE-WITH-STRATEGY}';

    protected $description = '[admin/operator] Live supplier create with one explicit strategy';

    public function handle(): int
    {
        $provider = strtolower(trim((string) $this->option('provider')));
        $action = strtolower(trim((string) $this->option('action')));
        $confirm = trim((string) $this->option('confirm'));

        if ($confirm !== self::CONFIRM_PHRASE) {
            $this->components->error('--confirm='.self::CONFIRM_PHRASE.' is required.');

            return self::FAILURE;
        }

        if ($this->resolveProductionGate() === null) {
            return self::FAILURE;
        }

        if ($provider === 'sabre' && $action === 'create_pnr') {
            $command = $this->getApplication()?->find('sabre:gds-pnr-create-with-strategy');
            if ($command === null) {
                $this->components->error('Sabre GDS PNR create command is not registered.');

                return self::FAILURE;
            }

            $delegateInput = [
                '--booking' => $this->option('booking'),
                '--strategy' => $this->option('strategy'),
                '--confirm' => SabreGdsPnrCreateWithStrategyCommand::CONFIRM_PHRASE,
            ];
            if (! SabreInspectGate::allowed() && (string) config('app.env', 'production') === 'production') {
                $delegateInput['--production-ops-approval'] = SabreGdsPnrCreateWithStrategyCommand::PRODUCTION_OPS_APPROVAL_PHRASE;
            }

            return $command->run(new ArrayInput($delegateInput), $this->output);
        }

        $this->components->error('No live create command registered for '.$provider.':'.$action);

        return self::FAILURE;
    }

    /**
     * @return bool|null true when production ops approved; false when local/testing; null when blocked
     */
    protected function resolveProductionGate(): ?bool
    {
        if (SabreInspectGate::allowed()) {
            return false;
        }

        $env = (string) config('app.env', 'production');
        if ($env !== 'production') {
            return false;
        }

        $approval = trim((string) $this->option('production-ops-approval'));
        if ($approval === self::PRODUCTION_OPS_APPROVAL_PHRASE) {
            return true;
        }

        if ($approval === '') {
            $this->components->error(
                'Production supplier create requires --production-ops-approval='.self::PRODUCTION_OPS_APPROVAL_PHRASE
            );
        } else {
            $this->components->error('Invalid --production-ops-approval phrase for production supplier create.');
        }

        return null;
    }
}
