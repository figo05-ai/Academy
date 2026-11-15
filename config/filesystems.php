<?php

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

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'contabo' => [
            'driver' => 's3',

            'key' => env('CONTABO_ACCESS_KEY'),
            'secret' => env('CONTABO_SECRET_KEY'),

            'region' => env('CONTABO_REGION', 'default'),

            'bucket' => env('CONTABO_BUCKET'),

            'endpoint' => env('CONTABO_ENDPOINT'),
            'url' => env('CONTABO_URL'),

            'use_path_style_endpoint' => env(
                'CONTABO_USE_PATH_STYLE_ENDPOINT',
                true
            ),

            'visibility' => env('CONTABO_VISIBILITY', 'private'),

            'throw' => true,
            'report' => true,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
