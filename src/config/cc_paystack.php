<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cuitcode Paystack Configuration file
    |--------------------------------------------------------------------------
    |
    | This was created for your Paystack API credentials
    |
    | To learn more, visit: https://dashboard.paystack.com/#/settings/developer
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API STATUS
    |--------------------------------------------------------------------------
    |
    | Paystack credentials can be used live or in test mode. 
    | This config sets it to live. 
    |
    | Supported: (TRUE, FALSE)
    | Default: FALSE
    |
    */

    'live_mode' => env('CC_PAYSTACK_LIVE_MODE', 'FALSE'),

    /*
    |--------------------------------------------------------------------------
    | Test Credentials ***DO NOT USE IN PRODUCTION***
    |--------------------------------------------------------------------------
    |
    | Paystack credentials can be used live or in test mode. 
    | This config is for the test mode. 
    |
    |secret: Test Secret Key
    |public: Test Public Key
    |callback: Test Callback URL (APP_URL will be prefixed, please DO NOT INCLUDE IT HERE)
    |webhook: Test Webhook URL
    |
    */

    'test' => [
        'secret' => env('CC_PAYSTACK_TEST_SECRET', null), // sk_test_your_secret_key_is_required
        'public' => env('CC_PAYSTACK_TEST_PUBLIC', null), // pk_test_your_public_key_is_required
        'callback' => env('CC_PAYSTACK_TEST_CALLBACK', '/paystack/test/callback'),
        'webhook' => env('CC_PAYSTACK_TEST_WEBHOOK', '/paystack/test/webhook'),
    ],

     /*
    |--------------------------------------------------------------------------
    | Live Credentials
    |--------------------------------------------------------------------------
    |
    | Paystack credentials can be used live or in test mode. 
    | This config is for the live mode. 
    |
    |secret: Live Secret Key
    |public: Live Public Key
    |callback: Live Callback URL (APP_URL will be prefixed. Just add path, default is '/paystack/callback')
    |webhook: Live Webhook URL
    |
    */

    'live' => [
        'secret' => env('CC_PAYSTACK_LIVE_SECRET', null), // sk_live_your_secret_key_is_required
        'public' => env('CC_PAYSTACK_LIVE_PUBLIC', null), // pk_live_your_public_key_is_required
        'callback' => env('CC_PAYSTACK_LIVE_CALLBACK', '/paystack/callback'),
        'webhook' => env('CC_PAYSTACK_LIVE_WEBHOOK', '/paystack/webhook'),
    ],

    'paths' => ['api/*', 'v1'],
];
