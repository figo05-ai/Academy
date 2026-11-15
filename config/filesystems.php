<?php

$contaboDisk = [
    'driver' => 's3',

    'key' => env('CONTABO_ACCESS_KEY'),
    'secret' => env('CONTABO_SECRET_KEY'),

    'region' => env('CONTABO_REGION', 'default'),

    'bucket' => env('CONTABO_BUCKET'),

    'url' => env('CONTABO_URL'),

    'endpoint' => env('CONTABO_ENDPOINT'),

    'use_path_style_endpoint' => env(
        'CONTABO_USE_PATH_STYLE_ENDPOINT',
        true
    ),

    'visibility' => env('CONTABO_VISIBILITY', 'private'),

    'throw' => true,
    'report' => true,
];

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'contabo' => $contaboDisk,

        // Temporary compatibility alias for existing S3 references.
        's3' => $contaboDisk,

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
