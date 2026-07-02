<?php

declare(strict_types=1);

return [
    // Symmetric secret shared with every service that verifies bearer tokens.
    'secret' => env('AUTH_JWT_SECRET', ''),

    'issuer' => env('AUTH_JWT_ISSUER', 'ticketarget-users'),
];
