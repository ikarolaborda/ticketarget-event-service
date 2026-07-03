<?php

declare(strict_types=1);
use App\Models\User;

return [
    'defaults' => ['guard' => 'sanctum', 'passwords' => 'users'],

    'guards' => [
        'sanctum' => ['driver' => 'sanctum', 'provider' => 'users'],
    ],

    'providers' => [
        'users' => ['driver' => 'eloquent', 'model' => User::class],
    ],
];
