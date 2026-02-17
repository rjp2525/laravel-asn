<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Exceptions\AsnLookupException;
use Reno\ASN\Providers\RipeStatProvider;

function makeRipeStat(HttpClient $http): RipeStatProvider
{
    return new RipeStatProvider(
        http: $http,
        timeout: 5,
        retryTimes: 1,
        retryDelay: 0,
    );
}

it('looks up an IP via RipeStat', function (): void {
    Http::fake([
        'stat.ripe.net/data/network-info/data.json*' => Http::response([
            'data' => ['asns' => [13335]],
        ]),
        'stat.ripe.net/data/as-overview/data.json*' => Http::response([
            'data' => [
                'holder' => 'CLOUDFLARENET',
                'resource_country' => 'US',
                'block' => ['resource' => 'AS13335'],
            ],
        ]),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $info = $provider->lookupIp('104.16.0.1');

    expect($info)->toBeInstanceOf(AsnInfo::class)
        ->and($info->asn)->toBe(13335)
        ->and($info->name)->toBe('CLOUDFLARENET')
        ->and($info->country)->toBe('US')
        ->and($info->rir)->toBe('AS13335');
});

it('throws when IP has no ASNs via RipeStat', function (): void {
    Http::fake([
        'stat.ripe.net/data/network-info/data.json*' => Http::response([
            'data' => ['asns' => []],
        ]),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $provider->lookupIp('192.0.2.1');
})->throws(AsnLookupException::class);

it('throws when data is null for lookupIp', function (): void {
    Http::fake([
        'stat.ripe.net/data/network-info/data.json*' => Http::response([
            'data' => null,
        ]),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $provider->lookupIp('192.0.2.1');
})->throws(AsnLookupException::class);

it('gets prefixes via RipeStat', function (): void {
    Http::fake([
        'stat.ripe.net/data/announced-prefixes/data.json*' => Http::response([
            'data' => [
                'prefixes' => [
                    ['prefix' => '104.16.0.0/12'],
                    ['prefix' => '172.64.0.0/13'],
                ],
            ],
        ]),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $prefixes = $provider->getPrefixes(13335);

    expect($prefixes)->toHaveCount(2)
        ->and($prefixes->first()->prefix)->toBe('104.16.0.0/12');
});

it('throws when ASN has no prefixes via RipeStat', function (): void {
    Http::fake([
        'stat.ripe.net/data/announced-prefixes/data.json*' => Http::response([
            'data' => ['prefixes' => []],
        ]),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $provider->getPrefixes(99999);
})->throws(AsnLookupException::class);

it('filters out sentinel 0.0.0.0/0 prefixes', function (): void {
    Http::fake([
        'stat.ripe.net/data/announced-prefixes/data.json*' => Http::response([
            'data' => [
                'prefixes' => [
                    ['prefix' => '104.16.0.0/12'],
                    ['prefix' => null], // will produce 0.0.0.0/0 sentinel
                    ['prefix' => 123],  // non-string
                ],
            ],
        ]),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $prefixes = $provider->getPrefixes(13335);

    expect($prefixes)->toHaveCount(1)
        ->and($prefixes->first()->prefix)->toBe('104.16.0.0/12');
});

it('gets full ASN result via RipeStat', function (): void {
    Http::fake([
        'stat.ripe.net/data/as-overview/data.json*' => Http::response([
            'data' => [
                'holder' => 'CLOUDFLARENET',
                'resource_country' => 'US',
            ],
        ]),
        'stat.ripe.net/data/announced-prefixes/data.json*' => Http::response([
            'data' => [
                'prefixes' => [
                    ['prefix' => '104.16.0.0/12'],
                ],
            ],
        ]),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $result = $provider->getAsn(13335);

    expect($result)->toBeInstanceOf(AsnResult::class)
        ->and($result->info->asn)->toBe(13335)
        ->and($result->info->name)->toBe('CLOUDFLARENET')
        ->and($result->prefixes)->toHaveCount(1);
});

it('throws when getAsn data is null via RipeStat', function (): void {
    Http::fake([
        'stat.ripe.net/data/as-overview/data.json*' => Http::response([
            'data' => null,
        ]),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $provider->getAsn(99999);
})->throws(AsnLookupException::class);

it('throws when HTTP request fails via RipeStat', function (): void {
    Http::fake([
        'stat.ripe.net/*' => Http::response('Server Error', 500),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $provider->lookupIp('1.2.3.4');
})->throws(AsnLookupException::class);

it('handles missing optional fields in lookupIp via RipeStat', function (): void {
    Http::fake([
        'stat.ripe.net/data/network-info/data.json*' => Http::response([
            'data' => ['asns' => [1234]],
        ]),
        'stat.ripe.net/data/as-overview/data.json*' => Http::response([
            'data' => [],
        ]),
    ]);

    $provider = makeRipeStat(resolve(HttpClient::class));
    $info = $provider->lookupIp('1.2.3.4');

    expect($info->name)->toBe('')
        ->and($info->description)->toBe('')
        ->and($info->country)->toBeNull()
        ->and($info->rir)->toBeNull();
});
