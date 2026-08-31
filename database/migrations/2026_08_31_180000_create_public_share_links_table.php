<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_share_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('link_type', 32); // flight_fare|group_offer
            $table->string('origin', 8)->nullable();
            $table->string('destination', 8)->nullable();
            $table->date('depart_date')->nullable();
            $table->date('return_date')->nullable();
            $table->string('trip_type', 32)->nullable();
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            $table->string('cabin', 32)->nullable();
            $table->string('display_currency', 8)->nullable();
            $table->decimal('display_fare', 14, 2)->nullable();
            $table->string('airline_code', 8)->nullable();
            $table->string('airline_name', 120)->nullable();
            $table->string('offer_fingerprint', 64)->nullable();
            $table->timestamp('supplier_offer_expires_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->json('payload')->nullable();
            $table->string('created_by_context', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_share_links');
    }
};
