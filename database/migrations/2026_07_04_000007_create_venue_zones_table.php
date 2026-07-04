<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_zones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('kind', 16);
            $table->unsignedSmallInteger('rows')->nullable();
            $table->unsignedSmallInteger('seats_per_row')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedTinyInteger('color_index')->default(0);
            $table->jsonb('geometry');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['venue_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_zones');
    }
};
