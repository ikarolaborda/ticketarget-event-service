<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Users service owns the identity schema (shared data plane), so suites
 * that reference it create the minimal table themselves — the same pattern
 * used for the bookings table owned by the Booking service. Auth itself is
 * now stateless (RS256/JWKS), so no personal-access-token table is needed.
 */
trait CreatesIdentityTables
{
    protected function createIdentityTables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->rememberToken();
                $table->boolean('is_admin')->default(false);
                $table->timestamps();
            });
        }
    }
}
