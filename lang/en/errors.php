<?php

return [
    'back' => 'Back',
    'default' => [
        'title' => 'An Issue Occurred',
        'badge' => 'Status :code',
        'message' => 'An issue occurred while processing your request. Please return to the dashboard.',
    ],
    '401' => [
        'title' => 'Authentication Required',
        'badge' => '401 • Not Authenticated',
        'message' => 'You are not logged into the system. Please sign in first to access this page.',
    ],
    '403' => [
        'title' => 'Access Denied',
        'badge' => '403 • Access Denied',
        'message' => 'You do not have permission to access this page.',
    ],
    '404' => [
        'title' => 'Page Not Found',
        'badge' => '404 • Page Not Found',
        'message' => 'Sorry, the page or link you requested was not found or is no longer available.',
    ],
    '419' => [
        'title' => 'Session Expired',
        'badge' => '419 • Session Expired',
        'message' => 'Your page security session has expired due to inactivity. Please refresh and try again.',
    ],
    '429' => [
        'title' => 'Too Many Requests',
        'badge' => '429 • Too Many Requests',
        'message' => 'The system detected too many requests from your device in a short period. Please wait a moment.',
    ],
    '500' => [
        'title' => 'Internal Server Error',
        'badge' => '500 • Server Error',
        'message' => 'Sorry, an internal technical error occurred while processing your request.',
    ],
    '503' => [
        'title' => 'System Under Maintenance',
        'badge' => '503 • System Maintenance',
        'message' => 'We are currently performing maintenance on the SMART system. We apologize for the inconvenience, please try again in a few moments.',
    ],
];
