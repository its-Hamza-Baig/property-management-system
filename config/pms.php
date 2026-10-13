<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform administrator
    |--------------------------------------------------------------------------
    |
    | The email address of the bootstrap Super Admin account, created by the
    | database seeder or by "php artisan pms:create-super-admin".
    |
    */

    'super_admin_email' => env('PMS_SUPER_ADMIN_EMAIL', 'admin@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    |
    | The daily database backup writes to this disk and keeps this many days
    | of history. Retention should match what the hosting plan can hold.
    |
    */

    'backup' => [
        'disk' => env('PMS_BACKUP_DISK', 'backups'),
        'retention_days' => (int) env('PMS_BACKUP_RETENTION_DAYS', 14),
        // Also sweep uploaded documents into the backup when enabled.
        'include_documents' => (bool) env('PMS_BACKUP_INCLUDE_DOCUMENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    |
    | How many days before an installment falls due the first reminder goes
    | out. Each reminder is logged so it is never sent twice.
    |
    */

    'reminders' => [
        'installment_due_days_before' => (int) env('PMS_INSTALLMENT_REMINDER_DAYS', 3),
    ],

];
