<?php

return [
    'encryption' => [
        'active_kek_version' => env('SECURITY_ACTIVE_KEK_VERSION', 'v1'),
        'kek_ring' => [
            'v1' => env('SECURITY_KEK_V1', env('APP_KEY')),
        ],
    ],
];
