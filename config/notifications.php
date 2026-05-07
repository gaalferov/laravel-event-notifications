<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mailtrap Template UUIDs
    |--------------------------------------------------------------------------
    |
    | One template per notification type. The mailer looks up the UUID by the
    | notification key (e.g. `teammate_invited`) and sends the email via
    | Mailtrap's template API with per-event variables.
    |
    */
    'templates' => [
        'teammate_invited' => env('MAILTRAP_TEMPLATE_TEAMMATE_INVITED'),
        'task_assigned' => env('MAILTRAP_TEMPLATE_TASK_ASSIGNED'),
        'comment_posted' => env('MAILTRAP_TEMPLATE_COMMENT_POSTED'),
        'weekly_digest' => env('MAILTRAP_TEMPLATE_WEEKLY_DIGEST'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Digest window
    |--------------------------------------------------------------------------
    |
    | How many days of recent activity the weekly digest includes.
    |
    */
    'digest_window_days' => (int) env('DIGEST_WINDOW_DAYS', 7),
];
