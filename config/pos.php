<?php

return [
    /*
    |--------------------------------------------------------------------------
    | POS Subdomain (Laravel Forge Domain Alias)
    |--------------------------------------------------------------------------
    |
    | When set (e.g. "pos.yourdomain.com"), POS routes are served exclusively
    | on that subdomain at "/". When left empty in local development or CI,
    | POS routes fall back automatically to "/pos".
    |
    */
    'domain' => env('POS_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Optional Store IP Allowlist
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of store public IPs allowed to access POS routes.
    | Leave empty to allow any authenticated staff member with "operate pos".
    |
    */
    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('POS_ALLOWED_IPS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Loyalty Points Redemption Rules
    |--------------------------------------------------------------------------
    |
    | Matches the storefront reward milestone (500 points = $10.00 discount).
    |
    */
    'loyalty' => [
        'points_per_block' => 500,
        'discount_per_block' => 10.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cash Drawer Safe-Drop Threshold
    |--------------------------------------------------------------------------
    |
    | Visual high-cash alert threshold for the POS header and shift drawer UI.
    | When expected drawer cash reaches this amount, cashiers are prompted to
    | perform a Safe Drop without interrupting active customer checkouts.
    |
    */
    'drawer' => [
        'safe_drop_alert_threshold' => (float) env('POS_SAFE_DROP_THRESHOLD', 1000.00),
    ],
];
