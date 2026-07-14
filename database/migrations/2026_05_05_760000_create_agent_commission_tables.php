<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_commission_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status');
            $table->string('calculation_basis')->nullable();
            $table->decimal('rate', 10, 4)->nullable();
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('description')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_commission_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('statement_number')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status');
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('earned_total', 12, 2)->default(0);
            $table->decimal('adjustment_total', 12, 2)->default(0);
            $table->decimal('payout_total', 12, 2)->default(0);
            $table->decimal('closing_balance', 12, 2)->default(0);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_commission_entry_statement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('agent_commission_statements')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('agent_commission_entries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['statement_id', 'entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commission_entry_statement');
        Schema::dropIfExists('agent_commission_statements');
        Schema::dropIfExists('agent_commission_entries');
    }
};
