<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    // Permite que o app web leia o tempo de espera das respostas 429.
    'exposed_headers' => ['Retry-After'],
    'max_age' => 0,
    'supports_credentials' => false,
];
