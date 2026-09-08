<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PlaceToPay Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials provided by PlaceToPay to authenticate API requests.
    |
    */
    'login' => env('PLACETOPAY_LOGIN', env('P2P_LOGIN', '')),
    'tranKey' => env('PLACETOPAY_TRAN_KEY', env('P2P_TRAN_KEY', '')),

    /*
    |--------------------------------------------------------------------------
    | PlaceToPay Endpoint URL
    |--------------------------------------------------------------------------
    |
    | Base URL for the PlaceToPay WebCheckout REST service.
    | Default is the sandbox / testing environment:
    | https://checkout-test.placetopay.com
    | Production:
    | https://checkout.placetopay.com
    |
    */
    'url' => env('PLACETOPAY_URL', env('P2P_URL', 'https://checkout-test.placetopay.com')),

    /*
    |--------------------------------------------------------------------------
    | REST Hash Algorithm
    |--------------------------------------------------------------------------
    |
    | Hashing algorithm used to generate tranKey in the auth payload.
    | Accepted values: 'sha256' (recommended) or 'sha1'.
    |
    */
    'rest_type' => env('PLACETOPAY_REST_TYPE', 'sha256'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP request timeout in seconds when calling PlaceToPay endpoints.
    |
    */
    'timeout' => (int) env('PLACETOPAY_TIMEOUT', 30),
];
