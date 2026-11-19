<?php

return [
    'enabled' => env('INDEXNOW_ENABLED', env('APP_ENV') === 'production'),
    'key' => env('INDEXNOW_KEY', '9837f087ef832e082b4f3eb61b1ae473'),
    'connection' => env('INDEXNOW_QUEUE_CONNECTION', 'database'),
];
