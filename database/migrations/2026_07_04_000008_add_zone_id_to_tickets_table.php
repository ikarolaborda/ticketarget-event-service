<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            // Nullable on purpose: pre-zone tickets stay valid forever.
            $table->uuid('zone_id')->nullable()->after('event_id');
            $table->index(['event_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['event_id', 'zone_id']);
            $table->dropColumn('zone_id');
        });
    }
};
