<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_passengers', function (Blueprint $table) {
            $table->string('gender', 32)->nullable()->after('nationality');
            $table->string('passport_issuing_country', 8)->nullable()->after('passport_number');
            $table->date('passport_expiry_date')->nullable()->after('passport_issuing_country');
            $table->date('passport_issue_date')->nullable()->after('passport_expiry_date');
            $table->string('document_type', 32)->nullable()->default('passport')->after('passport_issue_date');
            $table->string('national_id_number', 64)->nullable()->after('document_type');
            $table->string('country_of_residence', 120)->nullable()->after('national_id_number');
            $table->string('place_of_birth', 120)->nullable()->after('country_of_residence');
        });
    }

    public function down(): void
    {
        Schema::table('booking_passengers', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'passport_issuing_country',
                'passport_expiry_date',
                'passport_issue_date',
                'document_type',
                'national_id_number',
                'country_of_residence',
                'place_of_birth',
            ]);
        });
    }
};
