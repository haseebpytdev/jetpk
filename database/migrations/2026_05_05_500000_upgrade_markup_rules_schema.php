<?php

use App\Enums\MarkupRuleStatus;
use App\Enums\MarkupValueType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markup_rules', function (Blueprint $table): void {
            $table->string('value_type', 32)->default(MarkupValueType::Percentage->value)->after('value');
            $table->json('applies_to')->nullable()->after('value_type');
            $table->string('status', 32)->default(MarkupRuleStatus::Active->value)->after('priority');
            $table->timestamp('starts_at')->nullable()->after('status');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->json('meta')->nullable()->after('ends_at');
        });

        DB::table('markup_rules')
            ->orderBy('id')
            ->get()
            ->each(function (object $rule): void {
                $config = $rule->config !== null ? json_decode((string) $rule->config, true) : null;
                $ruleType = strtolower((string) $rule->rule_type);
                $valueType = str_contains($ruleType, 'fixed') ? MarkupValueType::Fixed->value : MarkupValueType::Percentage->value;
                $status = (bool) $rule->is_active ? MarkupRuleStatus::Active->value : MarkupRuleStatus::Inactive->value;

                DB::table('markup_rules')
                    ->where('id', $rule->id)
                    ->update([
                        'value_type' => $valueType,
                        'status' => $status,
                        'applies_to' => $config ? json_encode($config, JSON_THROW_ON_ERROR) : null,
                        'meta' => $config ? json_encode(['legacy_config' => $config], JSON_THROW_ON_ERROR) : null,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('markup_rules', function (Blueprint $table): void {
            $table->dropColumn(['value_type', 'applies_to', 'status', 'starts_at', 'ends_at', 'meta']);
        });
    }
};
