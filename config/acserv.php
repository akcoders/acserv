<?php

return [
    'public_tenant_slug' => env('PUBLIC_TENANT_SLUG', 'acserv-demo'),
    'update_web_root' => env('ACSERV_WEB_ROOT'),

    'demo_login' => [
        'workspace' => env('DEMO_WORKSPACE', 'acserv-demo'),
        'owner_email' => env('DEMO_OWNER_EMAIL', 'owner@acserv.test'),
        'technician_email' => env('DEMO_TECHNICIAN_EMAIL', 'technician@acserv.test'),
        'customer_email' => env('DEMO_CUSTOMER_EMAIL', 'customer@acserv.test'),
    ],

    'otp' => [
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    ],

    'attendance' => [
        'geofence_metres' => (int) env('ATTENDANCE_GEOFENCE_METRES', 250),
    ],

    'reminders' => [
        'service_interval_months' => (int) env('NEXT_SERVICE_INTERVAL_MONTHS', 3),
        'advance_days' => (int) env('SERVICE_REMINDER_ADVANCE_DAYS', 7),
        'warranty_advance_days' => (int) env('WARRANTY_REMINDER_ADVANCE_DAYS', 30),
    ],

    'workforce' => [
        'leave_opening_days' => (float) env('LEAVE_OPENING_DAYS', 12),
    ],
];
