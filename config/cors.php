<?php

return [
    'paths' => ['api/public/v1/*'],
    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type'],
    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
