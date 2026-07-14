<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionStatement;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingDocument;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\MarkupRule;
use App\Models\StaffProfile;
use App\Models\SupplierConnection;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;

class OtaAuditPoliciesCommand extends Command
{
    protected $signature = 'ota:audit-policies';

    protected $description = 'Audit registered policies and key method coverage';

    public function handle(): int
    {
        $map = [
            Booking::class => ['viewAny', 'view', 'changeStatus', 'recordPayment', 'issueTicket'],
            BookingPayment::class => ['view', 'create'],
            BookingDocument::class => ['view', 'create'],
            BookingCancellationRequest::class => ['request', 'approve', 'reject', 'process'],
            BookingRefund::class => ['create', 'approve', 'markPaid', 'reject'],
            AgentCommissionEntry::class => ['viewAny', 'approve', 'reject'],
            AgentCommissionStatement::class => ['view', 'statement'],
            SupplierConnection::class => ['viewAny', 'create', 'update', 'test'],
            MarkupRule::class => ['viewAny', 'create', 'update'],
            User::class => ['viewAny', 'create', 'update', 'suspend', 'activate'],
            Agent::class => ['viewAny', 'view', 'create', 'update'],
            StaffProfile::class => ['viewAny', 'view', 'create', 'update'],
        ];

        $this->info('=== OTA Policy Audit ===');
        foreach ($map as $model => $methods) {
            $policy = Gate::getPolicyFor($model);
            if ($policy === null) {
                $this->warn("MISSING policy: {$model}");

                continue;
            }

            $missing = collect($methods)->filter(fn (string $method): bool => ! method_exists($policy, $method))->values()->all();
            $this->line("{$model} -> ".get_class($policy));
            if ($missing === []) {
                $this->info('  methods: OK');
            } else {
                $this->warn('  missing methods: '.implode(', ', $missing));
            }
        }

        return self::SUCCESS;
    }
}
