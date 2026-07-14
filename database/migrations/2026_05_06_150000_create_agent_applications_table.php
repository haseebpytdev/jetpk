<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('mobile');
            $table->string('company_name');
            $table->string('business_type');
            $table->string('city');
            $table->string('country');
            $table->text('office_address');
            $table->string('website')->nullable();
            $table->string('cnic')->nullable();
            $table->string('ntn')->nullable();
            $table->string('iata_number')->nullable();
            $table->unsignedInteger('years_in_business')->nullable();
            $table->string('expected_booking_volume')->nullable();
            $table->json('services_interested')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_applications');
    }
};
