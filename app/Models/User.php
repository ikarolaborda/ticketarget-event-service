<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Administrative principal for catalog write operations. Authentication is via
 * Sanctum personal access tokens scoped with abilities (e.g. `events:write`).
 */
final class User extends Authenticatable
{
    use HasApiTokens;
    use HasUuids;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
