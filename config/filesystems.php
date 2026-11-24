<?php

$publicDriver = env('PUBLIC_FILESYSTEM_DRIVER', 'local');
$studentPhotoDriver = env('STUDENT_PHOTOS_DRIVER', $publicDriver === 's3' ? 's3' : 'local');
$r2Options = [
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => env('R2_REGION', 'auto'),
    'bucket' => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),
    'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
    'options' => ['before_upload' => [\App\Support\R2ObjectAcl::class, 'remove']],
    'throw' => true,
    'report' => false,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => array_merge([
            'driver' => $publicDriver,
            'root' => storage_path('app/public'),
            'url' => env('PUBLIC_FILESYSTEM_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/storage'),
            'visibility' => env('PUBLIC_FILESYSTEM_VISIBILITY', $publicDriver === 's3' ? 'private' : 'public'),
            'throw' => $publicDriver === 's3',
            'report' => false,
        ], $publicDriver === 's3' ? $r2Options + [
            'root' => env('R2_PUBLIC_PREFIX', ''),
            'visibility' => env('PUBLIC_FILESYSTEM_VISIBILITY', 'private'),
        ] : []),
        'student_photos' => array_merge([
            'driver' => $studentPhotoDriver,
            'root' => storage_path('app/private/student-photos'),
            'visibility' => 'private',
            'serve' => true,
            'throw' => $studentPhotoDriver === 's3',
            'report' => false,
        ], $studentPhotoDriver === 's3' ? $r2Options + [
            'root' => env('R2_PRIVATE_PREFIX', 'private'),
            'visibility' => 'private',
        ] : []),
        'legacy_public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
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

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
