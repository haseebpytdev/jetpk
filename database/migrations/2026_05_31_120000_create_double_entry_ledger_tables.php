<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('account_type', 32);
            $table->string('normal_balance', 8);
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 8)->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'is_active']);
        });

        Schema::create('ledger_transaction_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_ref')->unique();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_key')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_identifier')->nullable();
            $table->string('transaction_type', 64);
            $table->string('status', 16)->default('draft');
            $table->string('currency', 8)->default('PKR');
            $table->decimal('amount_total', 14, 2);
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'transaction_type'], 'ledger_tx_source_unique');
            $table->index(['agency_id', 'occurred_at']);
            $table->index('booking_id');
            $table->index(['status', 'posted_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('currency', 8)->default('PKR');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['ledger_account_id', 'agency_id']);
            $table->index('ledger_transaction_id');
        });

        Schema::create('ledger_posting_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->unique();
            $table->string('debit_account_code');
            $table->string('credit_account_code');
            $table->boolean('enabled')->default(true);
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_posting_rules');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_transaction_sequences');
        Schema::dropIfExists('ledger_accounts');
    }
};
