<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('passenger_id')->nullable()->constrained('booking_passengers')->nullOnDelete();
            $table->string('ticket_number')->nullable();
            $table->string('pnr')->nullable();
            $table->string('provider');
            $table->string('airline_code')->nullable();
            $table->string('status');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->json('raw_summary')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('ticketing_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('status');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('safe_summary')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('attempted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('ticketing_status')->nullable()->after('supplier_booking_status');
            $table->timestamp('ticketed_at')->nullable()->after('ticketing_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['ticketing_status', 'ticketed_at']);
        });

        Schema::dropIfExists('ticketing_attempts');
        Schema::dropIfExists('booking_tickets');
    }
};
