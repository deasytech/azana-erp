<?php

return [
    /*
    | Backups are written to the private "backups" disk, then copied to the off-site disk when one is named. Name any disk in
    | config/filesystems.php that lives somewhere other than this server (e.g. "s3", which needs league/flysystem-aws-s3-v3).
    | A backup that only exists on the server it protects is not a backup.
    */
    'disk' => env('BACKUP_DISK', 'backups'),

    'offsite_disk' => env('BACKUP_OFFSITE_DISK'),

    'directory' => 'database',

    /*
    | Retention: every backup of the last "daily_days" days is kept, then the newest of each week for "weekly_weeks" weeks,
    | then the newest of each month for "monthly_months" months. The newest good backup is never removed.
    */
    'retention' => [
        'daily_days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'weekly_weeks' => (int) env('BACKUP_KEEP_WEEKS', 8),
        'monthly_months' => (int) env('BACKUP_KEEP_MONTHS', 12),
    ],

    // The monitor warns when the newest good backup is older than this many hours, or no restore test passed within this many days.
    'max_backup_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 26),

    'max_restore_test_age_days' => (int) env('BACKUP_MAX_RESTORE_TEST_AGE_DAYS', 8),

    // MySQL tools and the scratch database a restore test loads into (it is created and dropped; the account needs the right to do so).
    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),

    'mysql' => env('BACKUP_MYSQL', 'mysql'),

    // Extra mysqldump flags, comma separated. MySQL 8 with GTIDs needs --set-gtid-purged=OFF or the dump cannot be loaded into another
    // database; MariaDB does not know that flag, so set BACKUP_MYSQLDUMP_ARGS= (empty) there.
    'mysqldump_args' => array_values(array_filter(array_map('trim', explode(',', (string) env('BACKUP_MYSQLDUMP_ARGS', '--set-gtid-purged=OFF'))))),

    'restore_test_database' => env('BACKUP_RESTORE_TEST_DATABASE', 'azana_restore_test'),

    // Warn when the disk holding the backups and uploads has less than this share free.
    'min_free_disk_percent' => (int) env('MONITOR_MIN_FREE_DISK_PERCENT', 10),

    // Warn when the application reported more than this many unexpected errors in the last 24 hours; fail at ten times as many.
    'max_errors_per_day' => (int) env('MONITOR_MAX_ERRORS_PER_DAY', 20),
];
