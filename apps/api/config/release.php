<?php

return [
    'trusted_proxies' => array_filter(explode(',', (string) env('TRUSTED_PROXIES', ''))),
    'proxy_secret' => env('CONSUMER_PROXY_SECRET'),
    'consumer_per_minute' => (int) env('CONSUMER_REQUESTS_PER_MINUTE', 60),
    'analytics_per_minute' => (int) env('ANALYTICS_REQUESTS_PER_MINUTE', 40),
    'global_per_minute' => (int) env('CONSUMER_GLOBAL_REQUESTS_PER_MINUTE', 600),
];
