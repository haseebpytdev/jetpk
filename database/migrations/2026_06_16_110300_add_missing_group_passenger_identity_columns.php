<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_booking_passengers', function (Blueprint $table) {
            if (! Schema::hasColumn('group_booking_passengers', 'given_name')) {
                $table->string('given_name')->nullable()->after('title');
            }

            if (! Schema::hasColumn('group_booking_passengers', 'surname')) {
                $table->string('surname')->nullable()->after('given_name');
            }

            if (! Schema::hasColumn('group_booking_passengers', 'passport_expiry_date')) {
                $table->date('passport_expiry_date')->nullable()->after('passport_issue_date');
            }
        });

        if (
            Schema::hasColumn('group_booking_passengers', 'given_name') &&
            Schema::hasColumn('group_booking_passengers', 'first_name')
        ) {
            DB::table('group_booking_passengers')
                ->whereNull('given_name')
                ->update(['given_name' => DB::raw('first_name')]);
        }

        if (
            Schema::hasColumn('group_booking_passengers', 'surname') &&
            Schema::hasColumn('group_booking_passengers', 'last_name')
        ) {
            DB::table('group_booking_passengers')
                ->whereNull('surname')
                ->update(['surname' => DB::raw('last_name')]);
        }

        if (
            Schema::hasColumn('group_booking_passengers', 'passport_expiry_date') &&
            Schema::hasColumn('group_booking_passengers', 'passport_expiry')
        ) {
            DB::table('group_booking_passengers')
                ->whereNull('passport_expiry_date')
                ->update(['passport_expiry_date' => DB::raw('passport_expiry')]);
        }
    }

    public function down(): void
    {
        Schema::table('group_booking_passengers', function (Blueprint $table) {
            if (Schema::hasColumn('group_booking_passengers', 'passport_expiry_date')) {
                $table->dropColumn('passport_expiry_date');
            }

            if (Schema::hasColumn('group_booking_passengers', 'surname')) {
                $table->dropColumn('surname');
            }

            if (Schema::hasColumn('group_booking_passengers', 'given_name')) {
                $table->dropColumn('given_name');
            }
        });
    }
};
