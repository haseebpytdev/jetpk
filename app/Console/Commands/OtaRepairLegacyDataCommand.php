<?php

namespace App\Console\Commands;

use App\Enums\MarkupRuleStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OtaRepairLegacyDataCommand extends Command
{
    protected $signature = 'ota:repair-legacy-data';

    protected $description = 'Repair legacy markup rule enum values safely';

    public function handle(): int
    {
        $mapping = [
            'fixed_pkr' => ['rule_type' => 'global', 'value_type' => 'fixed'],
            'percentage' => ['rule_type' => 'global', 'value_type' => 'percentage'],
            'route_fixed' => ['rule_type' => 'route', 'value_type' => 'fixed'],
            'route_percentage' => ['rule_type' => 'route', 'value_type' => 'percentage'],
            'airline_fixed' => ['rule_type' => 'airline', 'value_type' => 'fixed'],
            'airline_percentage' => ['rule_type' => 'airline', 'value_type' => 'percentage'],
        ];
        $validRuleTypes = ['global', 'route', 'airline', 'supplier', 'agent', 'cabin', 'fare_family'];
        $validValueTypes = ['fixed', 'percentage'];

        $rows = DB::table('markup_rules')
            ->select(['id', 'rule_type', 'value_type', 'status'])
            ->get();

        $mapped = 0;
        $normalized = 0;
        $disabledUnknown = 0;
        $warnings = 0;

        foreach ($rows as $row) {
            $ruleType = strtolower((string) $row->rule_type);
            $valueType = strtolower((string) $row->value_type);

            if (isset($mapping[$ruleType])) {
                DB::table('markup_rules')
                    ->where('id', $row->id)
                    ->update([
                        'rule_type' => $mapping[$ruleType]['rule_type'],
                        'value_type' => $mapping[$ruleType]['value_type'],
                        'updated_at' => now(),
                    ]);
                $mapped++;

                continue;
            }

            if (in_array($ruleType, $validRuleTypes, true) && in_array($valueType, $validValueTypes, true)) {
                continue;
            }

            if (! in_array($ruleType, $validRuleTypes, true) && in_array($valueType, $validValueTypes, true)) {
                DB::table('markup_rules')
                    ->where('id', $row->id)
                    ->update([
                        'rule_type' => 'global',
                        'value_type' => $valueType,
                        'status' => MarkupRuleStatus::Draft->value,
                        'updated_at' => now(),
                    ]);
                $normalized++;
                $warnings++;
                $this->warn("Rule #{$row->id}: unknown rule_type '{$row->rule_type}' normalized to global + draft.");

                continue;
            }

            DB::table('markup_rules')
                ->where('id', $row->id)
                ->update([
                    'status' => MarkupRuleStatus::Draft->value,
                    'updated_at' => now(),
                ]);
            $disabledUnknown++;
            $warnings++;
            $this->warn("Rule #{$row->id}: unsupported rule/value type ('{$row->rule_type}'/'{$row->value_type}') moved to draft.");
        }

        $this->newLine();
        $this->info('Legacy data repair summary');
        $this->line("Rules scanned: {$rows->count()}");
        $this->line("Legacy mapped: {$mapped}");
        $this->line("Unknown rule types normalized: {$normalized}");
        $this->line("Unknown values moved to draft: {$disabledUnknown}");
        $this->line("Warnings: {$warnings}");

        return self::SUCCESS;
    }
}
