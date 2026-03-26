<?php

/**
 * Application Features List
 * Define the key-value pairs for features that can be assigned to packages.
 */
return [
    'max_services' => [
        'label' => 'Maximum Services',
        'description' => 'Number of services an artisan can list.',
        'type' => 'number',
        'default' => 5
    ],
    'max_invoices' => [
        'label' => 'Maximum Invoices',
        'description' => 'Maximum number of invoices an artisan can create.',
        'type' => 'number',
        'default' => 10
    ],
    'max_portfolios' => [
        'label' => 'Maximum Portfolios',
        'description' => 'Number of portfolio projects/albums an artisan can create.',
        'type' => 'number',
        'default' => 3
    ],
    'max_portfolio_media' => [
        'label' => 'Media per Portfolio',
        'description' => 'Maximum number of media items (images, videos, documents) allowed per portfolio.',
        'type' => 'number',
        'default' => 5
    ],
    'max_portfolio_media_size' => [
        'label' => 'Max Media Size (MB)',
        'description' => 'Maximum size allowed for each portfolio media file.',
        'type' => 'number',
        'default' => 10
    ],
    'featured_listing' => [
        'label' => 'Featured Listing',
        'description' => 'Appear at the top of search results.',
        'type' => 'boolean',
        'default' => false
    ],
    'verified_badge' => [
        'label' => 'Verified Badge',
        'description' => 'Show a trust badge on the profile.',
        'type' => 'boolean',
        'default' => false
    ],
    'custom_invoice_branding' => [
        'label' => 'Invoice Branding',
        'description' => 'Customize invoice colors and add business logo.',
        'type' => 'boolean',
        'default' => false
    ],
    // 'commission_rate' => [
    //     'label' => 'Commission Rate (%)',
    //     'description' => 'Platform fee percentage on each successful booking.',
    //     'type' => 'number',
    //     'default' => 10
    // ],
    'analytics_access' => [
        'label' => 'Advanced Analytics',
        'description' => 'Access detailed insights about profile views and clicks.',
        'type' => 'boolean',
        'default' => false
    ],
    'priority_support' => [
        'label' => 'Priority Support',
        'description' => 'Get faster responses from our support team.',
        'type' => 'boolean',
        'default' => false
    ],
    // 'chat_file_sharing' => [
    //     'label' => 'Chat File Sharing',
    //     'description' => 'Ability to send files and images via chat.',
    //     'type' => 'boolean',
    //     'default' => false
    // ],
    // 'sms_notifications' => [
    //     'label' => 'SMS Notifications',
    //     'description' => 'Receive instant SMS alerts for new bookings.',
    //     'type' => 'boolean',
    //     'default' => false
    // ],
    'search_priority' => [
        'label' => 'Search Priority',
        'description' => 'Rank higher in search results (1 = Low, 3 = High).',
        'type' => 'number',
        'default' => 1
    ]
];
