<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('seat');
            $table->decimal('price', 10, 2);
            $table->string('type')->default('standard');
            $table->string('status')->default('available')->index();
            $table->timestamps();

            // A seat is unique within an event; the DB is the last line of
            // defense against double-selling even if application checks slip.
            $table->unique(['event_id', 'seat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
