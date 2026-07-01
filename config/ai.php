<?php

return [
    /*
    | Provider for certificate extraction. Currently "anthropic" (Claude Vision).
    */
    'provider' => env('AI_PROVIDER', 'anthropic'),

    'anthropic' => [
        'key'     => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        'base'    => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],
];
