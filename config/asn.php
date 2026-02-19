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
    'provider' => env('ASN_PROVIDER', 'ripestat'),

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
        'ripestat' => \Reno\ASN\Providers\RipeStatProvider::class,
        'ipinfo' => \Reno\ASN\Providers\IpInfoProvider::class,
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

    /*
    |--------------------------------------------------------------------------
    | IPinfo Configuration
    |--------------------------------------------------------------------------
    |
    | When using the IPinfo provider, you must supply an API token. You can
    | obtain one by signing up at https://ipinfo.io. Set it via the env
    | variable below or directly in this configuration array.
    |
    */
    'ipinfo' => [
        'token' => env('ASN_IPINFO_TOKEN'),
    ],

];
