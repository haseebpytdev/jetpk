<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_communication_settings', function (Blueprint $table): void {
            $table->boolean('daily_report_enabled')->default(false)->after('reply_to_email');
            $table->string('daily_report_time')->nullable()->after('daily_report_enabled');
            $table->boolean('weekly_report_enabled')->default(false)->after('daily_report_time');
            $table->string('weekly_report_day')->nullable()->after('weekly_report_enabled');
            $table->string('weekly_report_time')->nullable()->after('weekly_report_day');
            $table->boolean('monthly_report_enabled')->default(false)->after('weekly_report_time');
            $table->unsignedTinyInteger('monthly_report_day')->nullable()->after('monthly_report_enabled');
            $table->string('monthly_report_time')->nullable()->after('monthly_report_day');
            $table->boolean('monthly_ledger_enabled')->default(false)->after('monthly_report_time');
        });
    }

    public function down(): void
    {
        Schema::table('agency_communication_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'daily_report_enabled',
                'daily_report_time',
                'weekly_report_enabled',
                'weekly_report_day',
                'weekly_report_time',
                'monthly_report_enabled',
                'monthly_report_day',
                'monthly_report_time',
                'monthly_ledger_enabled',
            ]);
        });
    }
};
