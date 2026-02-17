<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | Here you may specify which ASN data provider will be used for all of your
    | IP lookups and prefix resolution queries. The available providers are
    | defined in the provider class map section directly below this one.
    |
    */
    'provider' => env('ASN_PROVIDER', 'bgpview'),

    /*
    |--------------------------------------------------------------------------
    | Provider Class Map
    |--------------------------------------------------------------------------
    |
    | This map connects provider aliases to their fully-qualified class names.
    | You may register custom providers by adding any class that implements
    | the AsnProvider contract, then set its alias as the provider above.
    |
    */
    'providers' => [
        'bgpview' => \Reno\ASN\Providers\BgpViewProvider::class,
        'ripestat' => \Reno\ASN\Providers\RipeStatProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | ASN prefix lists rarely change, so aggressive caching is strongly advised
    | for production use. Toggle caching, pick a store, set the time-to-live
    | in seconds, and optionally define a key prefix to avoid collisions.
    |
    */
    'cache' => [
        'enabled' => env('ASN_CACHE_ENABLED', true),
        'store' => env('ASN_CACHE_STORE'),
        'ttl' => (int) env('ASN_CACHE_TTL', 86400),
        'prefix' => 'reno:asn:',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | These settings configure the HTTP client used when sending requests to a
    | remote ASN provider. Tune the timeout, retry count, and delay between
    | successive retries to match your network reliability and latency.
    |
    */
    'http' => [
        'timeout' => (int) env('ASN_HTTP_TIMEOUT', 15),
        'retry_times' => (int) env('ASN_HTTP_RETRIES', 3),
        'retry_delay' => (int) env('ASN_HTTP_RETRY_DELAY', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | DNS Resolution
    |--------------------------------------------------------------------------
    |
    | When resolving domain names to IP addresses for ASN checks, these values
    | determine the DNS record type to query and how long resolved results
    | remain cached. Use DNS_AAAA instead when you need to resolve IPv6.
    |
    */
    'dns' => [
        'record_type' => DNS_A,
        'cache_ttl' => (int) env('ASN_DNS_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Processing
    |--------------------------------------------------------------------------
    |
    | When checking large collections of IP addresses against ASN prefix data,
    | results are processed in manageable chunks to keep memory usage low.
    | You may adjust this chunk size to fit your application constraints.
    |
    */
    'batch' => [
        'chunk_size' => (int) env('ASN_BATCH_CHUNK_SIZE', 1000),
    ],

];
