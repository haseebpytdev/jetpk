<?php

use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('ticket_reference', 32)->nullable()->unique()->after('id');
            $table->string('source', 32)->default('customer')->after('ticket_reference');
            $table->string('requester_name')->nullable()->after('source');
            $table->string('requester_email')->nullable()->after('requester_name');
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropForeign(['created_by_user_id']);
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by_user_id')->nullable()->change();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        $service = app(SupportTicketService::class);

        SupportTicket::query()
            ->whereNull('ticket_reference')
            ->orderBy('id')
            ->each(function (SupportTicket $ticket) use ($service): void {
                $ticket->forceFill([
                    'ticket_reference' => $service->generateUniqueReference(),
                    'source' => $ticket->created_by_user_id !== null ? 'customer' : 'public',
                ])->save();
            });
    }

    public function down(): void
    {
        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropForeign(['created_by_user_id']);
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by_user_id')->nullable(false)->change();
            $table->foreign('created_by_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn(['ticket_reference', 'source', 'requester_name', 'requester_email']);
        });
    }
};
