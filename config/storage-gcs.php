<?php

return [
    'disk' => [
        'driver' => 'gcs',
        'bucket' => env('GCS_BUCKET', ''),
        'project_id' => env('GCS_PROJECT_ID', ''),
        'key_file' => env('GCS_KEY_FILE', ''),
        'prefix' => env('GCS_PREFIX', ''),
        'signed_ttl' => (int) env('GCS_SIGNED_URL_TTL', 3600),
    ],
];
