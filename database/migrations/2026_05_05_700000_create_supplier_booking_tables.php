<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_booking_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('action')->default('create_pnr');
            $table->string('status');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('safe_summary')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('attempted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('supplier_reference')->nullable();
            $table->string('pnr')->nullable();
            $table->string('status');
            $table->json('raw_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at_supplier')->nullable();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('supplier_booking_status')->nullable()->after('pnr');
            $table->string('supplier_reference')->nullable()->after('supplier_booking_status');
            $table->timestamp('supplier_booking_created_at')->nullable()->after('supplier_reference');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['supplier_booking_status', 'supplier_reference', 'supplier_booking_created_at']);
        });

        Schema::dropIfExists('supplier_bookings');
        Schema::dropIfExists('supplier_booking_attempts');
    }
};
