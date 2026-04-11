<?php

$defaultAllowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:8080',
    'http://127.0.0.1:8080',
    'https://reserva-escolar.web.app',
    'https://reserva-escolar.firebaseapp.com',
];

$configuredOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('RESERVA_ALLOWED_ORIGINS', ''))
)));

return [
    'paths' => ['*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $configuredOrigins !== [] ? $configuredOrigins : $defaultAllowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
