<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Snapshot retention thresholds
    |--------------------------------------------------------------------------
    | storage_bytes_per_diagram: max cumulative snapshot storage before pruning
    | max_age_days:              snapshots older than this are marked for expiry
    | export_quota_bytes:        max exportable snapshot data per user per month
    */
    'snapshot_storage_bytes_per_diagram' => env('SNAPSHOT_STORAGE_BYTES', 10 * 1024 * 1024),
    'snapshot_max_age_days'              => env('SNAPSHOT_MAX_AGE_DAYS',  90),
    'export_quota_bytes'                 => env('EXPORT_QUOTA_BYTES',    100 * 1024 * 1024),
];
