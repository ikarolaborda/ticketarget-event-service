<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->string('type')->nullable();
            $table->string('artist')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestampTz('date')->index();
            $table->foreignUuid('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
