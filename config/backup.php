<?php

return [
    /*
    | Absolute path where `db:backup` writes gzipped pg_dump files.
    | In production, mount a durable Hetzner Volume at this path so backups
    | survive container rebuilds (see docs/DEPLOY.md).
    */
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    /*
    | How many of the most recent backup files to keep. Older files are pruned
    | after each successful backup.
    */
    'retention' => (int) env('BACKUP_RETENTION', 14),
];
