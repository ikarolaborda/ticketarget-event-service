<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same transactional-outbox shape as the Booking service, but under a
        // context-prefixed name: the shadow-period shared database already has
        // booking's outbox_messages, and one shared table would let either
        // publisher ship the other context's events to the wrong topic.
        Schema::create('catalog_outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('aggregate_type');
            $table->uuid('aggregate_id');
            $table->string('event_type');
            $table->string('event_key')->unique();
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampTz('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_outbox_messages');
    }
};
