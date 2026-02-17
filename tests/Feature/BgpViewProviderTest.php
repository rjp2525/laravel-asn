<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Exceptions\AsnLookupException;
use Reno\ASN\Providers\BgpViewProvider;

function makeBgpView(HttpClient $http): BgpViewProvider
{
    return new BgpViewProvider(
        http: $http,
        timeout: 5,
        retryTimes: 1,
        retryDelay: 0,
    );
}

it('looks up an IP and returns ASN info', function (): void {
    Http::fake([
        'api.bgpview.io/ip/104.16.0.1' => Http::response([
            'data' => [
                'prefixes' => [
                    [
                        'prefix' => '104.16.0.0/12',
                        'asn' => [
                            'asn' => 13335,
                            'name' => 'CLOUDFLARENET',
                            'description' => 'Cloudflare, Inc.',
                            'country_code' => 'US',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $info = $provider->lookupIp('104.16.0.1');

    expect($info)->toBeInstanceOf(AsnInfo::class)
        ->and($info->asn)->toBe(13335)
        ->and($info->name)->toBe('CLOUDFLARENET')
        ->and($info->description)->toBe('Cloudflare, Inc.')
        ->and($info->country)->toBe('US');
});

it('throws when IP has no prefixes', function (): void {
    Http::fake([
        'api.bgpview.io/ip/192.0.2.1' => Http::response([
            'data' => ['prefixes' => []],
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $provider->lookupIp('192.0.2.1');
})->throws(AsnLookupException::class);

it('throws when IP data is null', function (): void {
    Http::fake([
        'api.bgpview.io/ip/192.0.2.1' => Http::response([
            'data' => null,
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $provider->lookupIp('192.0.2.1');
})->throws(AsnLookupException::class);

it('throws when prefix has no asn data', function (): void {
    Http::fake([
        'api.bgpview.io/ip/192.0.2.1' => Http::response([
            'data' => [
                'prefixes' => [
                    ['prefix' => '192.0.2.0/24'],
                ],
            ],
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $provider->lookupIp('192.0.2.1');
})->throws(AsnLookupException::class);

it('gets prefixes for an ASN', function (): void {
    Http::fake([
        'api.bgpview.io/asn/13335/prefixes' => Http::response([
            'data' => [
                'ipv4_prefixes' => [
                    ['prefix' => '104.16.0.0/12', 'name' => 'Cloudflare', 'description' => 'CDN', 'country_code' => 'US'],
                    ['prefix' => '172.64.0.0/13', 'name' => 'Cloudflare', 'description' => 'CDN', 'country_code' => 'US'],
                ],
                'ipv6_prefixes' => [
                    ['prefix' => '2606:4700::/32', 'name' => 'Cloudflare IPv6', 'description' => null, 'country_code' => 'US'],
                ],
            ],
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $prefixes = $provider->getPrefixes(13335);

    expect($prefixes)->toHaveCount(3)
        ->and($prefixes->first()->prefix)->toBe('104.16.0.0/12')
        ->and($prefixes->first()->name)->toBe('Cloudflare')
        ->and($prefixes->first()->asn)->toBe(13335)
        ->and($prefixes->last()->isIpv6)->toBeTrue();
});

it('throws when ASN data is null for getPrefixes', function (): void {
    Http::fake([
        'api.bgpview.io/asn/99999/prefixes' => Http::response([
            'data' => null,
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $provider->getPrefixes(99999);
})->throws(AsnLookupException::class);

it('skips invalid entries in getPrefixes', function (): void {
    Http::fake([
        'api.bgpview.io/asn/13335/prefixes' => Http::response([
            'data' => [
                'ipv4_prefixes' => [
                    ['prefix' => '104.16.0.0/12', 'name' => 'Valid'],
                    'not-an-array',
                    ['no_prefix_key' => true],
                    ['prefix' => 123], // not a string
                ],
                'ipv6_prefixes' => [],
            ],
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $prefixes = $provider->getPrefixes(13335);

    expect($prefixes)->toHaveCount(1);
});

it('gets full ASN details', function (): void {
    Http::fake([
        'api.bgpview.io/asn/13335' => Http::response([
            'data' => [
                'asn' => 13335,
                'name' => 'CLOUDFLARENET',
                'description_short' => 'Cloudflare, Inc.',
                'country_code' => 'US',
                'rir_allocation' => ['rir_name' => 'ARIN'],
            ],
        ]),
        'api.bgpview.io/asn/13335/prefixes' => Http::response([
            'data' => [
                'ipv4_prefixes' => [
                    ['prefix' => '104.16.0.0/12'],
                ],
                'ipv6_prefixes' => [],
            ],
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $result = $provider->getAsn(13335);

    expect($result)->toBeInstanceOf(AsnResult::class)
        ->and($result->info->asn)->toBe(13335)
        ->and($result->info->rir)->toBe('ARIN')
        ->and($result->prefixes)->toHaveCount(1);
});

it('throws when getAsn data is null', function (): void {
    Http::fake([
        'api.bgpview.io/asn/99999' => Http::response([
            'data' => null,
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $provider->getAsn(99999);
})->throws(AsnLookupException::class);

it('throws when HTTP request fails', function (): void {
    Http::fake([
        'api.bgpview.io/*' => Http::response('Server Error', 500),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $provider->lookupIp('1.2.3.4');
})->throws(AsnLookupException::class);

it('handles missing optional fields gracefully in lookupIp', function (): void {
    Http::fake([
        'api.bgpview.io/ip/1.2.3.4' => Http::response([
            'data' => [
                'prefixes' => [
                    [
                        'prefix' => '1.0.0.0/8',
                        'asn' => [
                            'asn' => 1234,
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $info = $provider->lookupIp('1.2.3.4');

    expect($info->name)->toBe('')
        ->and($info->description)->toBe('')
        ->and($info->country)->toBeNull();
});

it('handles getAsn with no rir allocation', function (): void {
    Http::fake([
        'api.bgpview.io/asn/1234' => Http::response([
            'data' => [
                'asn' => 1234,
                'name' => 'TEST',
                'description_short' => 'Test ASN',
            ],
        ]),
        'api.bgpview.io/asn/1234/prefixes' => Http::response([
            'data' => [
                'ipv4_prefixes' => [],
                'ipv6_prefixes' => [],
            ],
        ]),
    ]);

    $provider = makeBgpView(resolve(HttpClient::class));
    $result = $provider->getAsn(1234);

    expect($result->info->rir)->toBeNull()
        ->and($result->info->country)->toBeNull();
});
