<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Mints an admin personal access token scoped to `events:write`. The plaintext
 * token is shown once; only its hash is stored.
 */
final class CreateAdminToken extends Command
{
    protected $signature = 'admin:token {email=admin@ticketarget.local} {--name=admin}';
    protected $description = 'Create (or reuse) an admin user and issue an events:write API token';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => (string) $this->option('name'), 'password' => Hash::make(Str::random(40))],
        );

        $token = $user->createToken('admin-cli', ['events:write'])->plainTextToken;

        $this->info('Admin token (store it now, it will not be shown again):');
        $this->line($token);

        return self::SUCCESS;
    }
}
