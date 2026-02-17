# Laravel ASN

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rjp2525/laravel-asn.svg?style=flat-square)](https://packagist.org/packages/rjp2525/laravel-asn)
[![Tests](https://img.shields.io/github/actions/workflow/status/rjp2525/laravel-asn/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rjp2525/laravel-asn/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/rjp2525/laravel-asn.svg?style=flat-square)](https://packagist.org/packages/rjp2525/laravel-asn)

A full-featured Laravel package for working with Autonomous System Numbers (ASNs) and IP address ranges. Look up which network owns an IP, check if addresses belong to specific ASNs, resolve domains to their ASN, and query your database with driver-optimized IP range filters.

## Installation

```bash
composer require rjp2525/laravel-asn
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="laravel-asn-config"
```

## Usage

### ASN Lookups

Look up which ASN owns an IP address:

```php
use Reno\ASN\Facades\Asn;

$info = Asn::lookupIp('104.16.0.1');

$info->asn;         // 13335
$info->name;        // "CLOUDFLARENET"
$info->description; // "Cloudflare, Inc."
$info->country;     // "US"
```

Get all prefixes announced by an ASN:

```php
$prefixes = Asn::getPrefixes(13335);

foreach ($prefixes as $prefix) {
    echo $prefix->prefix;  // "104.16.0.0/12"
    echo $prefix->name;    // "Cloudflare"
    echo $prefix->country; // "US"
}
```

Check if an IP belongs to an ASN:

```php
Asn::ipBelongsToAsn('104.16.0.1', 13335); // true
Asn::ipBelongsToAsn('8.8.8.8', 13335);    // false
```

Check an IP against multiple ASNs:

```php
$matched = Asn::ipMatchesAnyAsn('8.8.8.8', [13335, 15169]);
// Returns 15169 (Google's ASN) or null if no match
```

Check if two IPs are on the same network:

```php
Asn::ipBelongsToSameAsn('104.16.0.1', '172.64.0.1'); // true (both Cloudflare)
```

### IP Matching

For checking many IPs against the same set of ranges, build a compiled `IpMatcher` for O(log n) binary search:

```php
use Reno\ASN\Facades\Asn;

// Build from ASN prefixes
$matcher = Asn::buildMatcher([13335, 15169]); // Cloudflare + Google

$matcher->contains('104.16.0.1'); // true
$matcher->contains('8.8.8.8');    // true
$matcher->contains('1.2.3.4');    // false
```

Build a custom matcher with CIDR prefixes, single IPs, and explicit ranges:

```php
use Reno\ASN\IpMatcher;

$matcher = (new IpMatcher)
    ->addPrefix('10.0.0.0/8')
    ->addPrefix('172.16.0.0/12')
    ->addIp('1.1.1.1')
    ->addExplicitRange('192.168.1.1', '192.168.1.100')
    ->compile();

$matcher->contains('10.0.0.50');    // true
$matcher->contains('1.1.1.1');      // true
$matcher->contains('192.168.1.50'); // true
```

Batch check with detailed results:

```php
$results = Asn::batchCheck(
    ips: ['104.16.0.1', '8.8.8.8', '1.2.3.4'],
    asns: [13335],
);

foreach ($results as $result) {
    $result->ip;      // "104.16.0.1"
    $result->matched; // true
    $result->asn();   // 13335
    $result->label(); // "Cloudflare"
}
```

### Domain Resolution

Resolve domains and check their ASN:

```php
use Reno\ASN\Facades\AsnDns;

$ips = AsnDns::resolveIps('cloudflare.com');
// ["104.16.132.229", "104.16.133.229"]

$info = AsnDns::lookupAsn('cloudflare.com');
// AsnInfo { asn: 13335, name: "CLOUDFLARENET", ... }

AsnDns::domainBelongsToAsn('cloudflare.com', 13335); // true

$matched = AsnDns::domainMatchesAnyAsn('cloudflare.com', [13335, 15169]);
// 13335
```

Check a domain against a compiled matcher:

```php
$matcher = (new IpMatcher)->addPrefix('104.16.0.0/12')->compile();

AsnDns::domainMatchesRanges('cloudflare.com', $matcher); // true
```

### Validation Rules

All rules support PHP Attributes for use on DTOs:

```php
use Reno\ASN\Rules\IpInAsn;
use Reno\ASN\Rules\IpNotInAsn;
use Reno\ASN\Rules\IpInRange;
use Reno\ASN\Rules\DomainInAsn;

// In a FormRequest
public function rules(): array
{
    return [
        // IP must belong to Cloudflare
        'ip' => ['required', 'ip', new IpInAsn(13335)],

        // IP must not be from Comcast or AT&T
        'ip' => ['required', 'ip', new IpNotInAsn(7922, 7018)],

        // IP must be in a private range
        'ip' => ['required', 'ip', new IpInRange('10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16')],

        // Domain must resolve to a Cloudflare IP
        'domain' => ['required', 'string', new DomainInAsn(13335)],
    ];
}
```

As PHP Attributes on a DTO:

```php
class ServerConfig
{
    #[IpInAsn(13335, 15169)]
    public string $ip;

    #[IpInRange('10.0.0.0/8')]
    public string $internalIp;
}
```

### Eloquent Query Macros

Query your database with driver-optimized IP range filters. Works with PostgreSQL (native `inet`), MySQL/MariaDB (`INET_ATON`), and SQLite (numeric extraction):

```php
// Filter by CIDR range
User::whereIpInRange('ip_address', '10.0.0.0/8')->get();

// Filter by multiple ranges
User::whereIpInRanges('ip_address', ['10.0.0.0/8', '172.16.0.0/12'])->get();

// Filter by ASN (resolves prefixes automatically)
User::whereIpInAsn('ip_address', 13335)->get();
User::whereIpInAsn('ip_address', [13335, 15169])->get();

// Exclude by ASN
User::whereIpNotInAsn('ip_address', 7922)->get();

// Filter using a pre-compiled matcher
$matcher = Asn::buildMatcher([13335]);
User::whereIpInMatcher('ip_address', $matcher)->get();

// Normalized IP comparison
User::whereIpEquals('ip_address', '8.8.8.8')->get();
```

All macros work on both Eloquent Builder and base Query Builder:

```php
DB::table('logs')->whereIpInRange('ip', '10.0.0.0/8')->get();
```

### Artisan Commands

```bash
# Look up ASN information for an IP
php artisan asn:lookup 104.16.0.1

# Check if an IP belongs to an ASN
php artisan asn:check 104.16.0.1 13335

# List prefixes announced by an ASN
php artisan asn:prefixes 13335
php artisan asn:prefixes 13335 --ipv4-only
php artisan asn:prefixes 13335 --ipv6-only
php artisan asn:prefixes 13335 --count

# Look up ASN for a domain
php artisan asn:domain cloudflare.com
php artisan asn:domain cloudflare.com --check-asn=13335
```

## Configuration

```php
return [
    // ASN data provider: "bgpview" or "ripestat"
    'provider' => env('ASN_PROVIDER', 'bgpview'),

    'cache' => [
        'enabled' => env('ASN_CACHE_ENABLED', true),
        'store'   => env('ASN_CACHE_STORE'),       // null = default store
        'ttl'     => env('ASN_CACHE_TTL', 86400),  // 24 hours
        'prefix'  => 'reno:asn:',
    ],

    'http' => [
        'timeout'     => env('ASN_HTTP_TIMEOUT', 15),
        'retry_times' => env('ASN_HTTP_RETRIES', 3),
        'retry_delay' => env('ASN_HTTP_RETRY_DELAY', 500),
    ],

    'dns' => [
        'record_type' => DNS_A,
        'cache_ttl'   => env('ASN_DNS_CACHE_TTL', 3600),
    ],
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Reno Philibert](https://github.com/rjp2525)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
