<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('batch_id', 64)->nullable()->index();
            $table->string('recipient');
            $table->string('channel', 16)->index();
            $table->text('content');
            $table->string('priority', 16)->default('normal')->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('provider_message_id', 128)->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->string('trace_id', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
