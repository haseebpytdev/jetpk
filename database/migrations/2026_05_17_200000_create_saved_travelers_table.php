<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_travelers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('title', 16)->nullable();
            $table->string('gender', 32)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 2)->nullable();
            $table->string('document_type', 32)->nullable();
            $table->text('document_number')->nullable();
            $table->date('document_expiry')->nullable();
            $table->string('issuing_country', 2)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'agency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_travelers');
    }
};
