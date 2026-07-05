<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same transactional-outbox shape as the Booking service: rows are
        // written inside the mutating transaction and shipped by
        // outbox:publish; event_key dedupes retried application paths.
        Schema::create('outbox_messages', function (Blueprint $table): void {
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
        Schema::dropIfExists('outbox_messages');
    }
};
