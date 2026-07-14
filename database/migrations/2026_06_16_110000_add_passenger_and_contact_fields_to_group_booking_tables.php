<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_booking_passengers', function (Blueprint $table): void {
            $table->string('gender', 16)->nullable()->after('last_name');
            $table->string('document_type', 32)->nullable()->after('nationality');
            $table->date('passport_issue_date')->nullable()->after('passport_number');
        });

        Schema::table('group_bookings', function (Blueprint $table): void {
            $table->string('contact_name', 120)->nullable()->after('currency');
            $table->string('contact_email', 120)->nullable()->after('contact_name');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('group_booking_passengers', function (Blueprint $table): void {
            $table->dropColumn(['gender', 'document_type', 'passport_issue_date']);
        });

        Schema::table('group_bookings', function (Blueprint $table): void {
            $table->dropColumn(['contact_name', 'contact_email', 'contact_phone']);
        });
    }
};
