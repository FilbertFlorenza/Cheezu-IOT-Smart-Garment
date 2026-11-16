<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Device liveness
    |--------------------------------------------------------------------------
    |
    | A device is considered online when its configured status is ACTIVE and
    | it has sent an inspection within this many minutes.
    |
    */
    'online_threshold_minutes' => (int) env('INSPECTION_ONLINE_THRESHOLD_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Inspection image storage
    |--------------------------------------------------------------------------
    */
    'image_disk' => env('INSPECTION_IMAGE_DISK', env('FILESYSTEM_DISK', 'local')),
    'image_max_kb' => (int) env('INSPECTION_IMAGE_MAX_KB', 5120),
    'image_max_dimension' => (int) env('INSPECTION_IMAGE_MAX_DIMENSION', 4096),

    /*
    |--------------------------------------------------------------------------
    | Incoming inspection contract
    |--------------------------------------------------------------------------
    */
    'backfill_days' => (int) env('INSPECTION_BACKFILL_DAYS', 7),
    'future_tolerance_minutes' => (int) env('INSPECTION_FUTURE_TOLERANCE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Defect classes
    |--------------------------------------------------------------------------
    |
    | Used for filtering and display hints. Unknown classes are still accepted
    | so the inference team can evolve the model without a backend deployment.
    |
    */
    'defect_classes' => [
        'hole',
        'stain',
        'tear',
        'missed_stitch',
        'slub',
        'color_variation',
        'wrinkle',
        'foreign_fiber',
        'selvedge_damage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Display timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC. The dashboard renders them in this zone.
    |
    */
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Manila'),
];
