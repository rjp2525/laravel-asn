<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Http;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Exceptions\AsnLookupException;
use Reno\ASN\Providers\IpInfoProvider;

function makeIpInfo(HttpClient $http): IpInfoProvider
{
    return new IpInfoProvider(
        http: $http,
        timeout: 5,
        retryTimes: 1,
        retryDelay: 0,
        token: 'test-token',
    );
}

it('looks up an IP via IPinfo', function (): void {
    Http::fake([
        'api.ipinfo.io/lite/*' => Http::response([
            'asn' => 'AS15169',
            'as_name' => 'Google LLC',
            'as_domain' => 'google.com',
            'country_code' => 'US',
        ]),
    ]);

    $provider = makeIpInfo(resolve(HttpClient::class));
    $info = $provider->lookupIp('8.8.8.8');

    expect($info)->toBeInstanceOf(AsnInfo::class)
        ->and($info->asn)->toBe(15169)
        ->and($info->name)->toBe('Google LLC')
        ->and($info->description)->toBe('Google LLC')
        ->and($info->country)->toBe('US');
});

it('throws when IP has no ASN via IPinfo', function (): void {
    Http::fake([
        'api.ipinfo.io/lite/*' => Http::response([
            'as_name' => 'Unknown',
        ]),
    ]);

    $provider = makeIpInfo(resolve(HttpClient::class));
    $provider->lookupIp('192.0.2.1');
})->throws(AsnLookupException::class);

it('throws when ASN is empty string via IPinfo', function (): void {
    Http::fake([
        'api.ipinfo.io/lite/*' => Http::response([
            'asn' => '',
            'as_name' => 'Unknown',
        ]),
    ]);

    $provider = makeIpInfo(resolve(HttpClient::class));
    $provider->lookupIp('192.0.2.1');
})->throws(AsnLookupException::class);

it('gets prefixes via IPinfo', function (): void {
    Http::fake([
        'ipinfo.io/AS15169/json*' => Http::response([
            'asn' => 'AS15169',
            'name' => 'Google LLC',
            'country' => 'US',
            'prefixes' => [
                ['netblock' => '8.8.8.0/24', 'id' => 'LVLT-GOGL-8-8-8', 'name' => 'Google LLC', 'country' => 'US'],
                ['netblock' => '8.8.4.0/24', 'id' => 'LVLT-GOGL-8-8-4', 'name' => 'Google LLC', 'country' => 'US'],
            ],
            'prefixes6' => [
                ['netblock' => '2001:4860::/32', 'id' => 'GOGL-V6', 'name' => 'Google LLC', 'country' => 'US'],
            ],
        ]),
    ]);

    $provider = makeIpInfo(resolve(HttpClient::class));
    $prefixes = $provider->getPrefixes(15169);

    expect($prefixes)->toHaveCount(3)
        ->and($prefixes->first()->prefix)->toBe('8.8.8.0/24')
        ->and($prefixes->first()->name)->toBe('Google LLC')
        ->and($prefixes->first()->country)->toBe('US')
        ->and($prefixes->last()->prefix)->toBe('2001:4860::/32');
});

it('throws when ASN has no prefixes via IPinfo', function (): void {
    Http::fake([
        'ipinfo.io/AS99999/json*' => Http::response([
            'asn' => 'AS99999',
            'name' => 'Unknown',
            'prefixes' => [],
            'prefixes6' => [],
        ]),
    ]);

    $provider = makeIpInfo(resolve(HttpClient::class));
    $provider->getPrefixes(99999);
})->throws(AsnLookupException::class);

it('gets full ASN result via IPinfo', function (): void {
    Http::fake([
        'ipinfo.io/AS15169/json*' => Http::response([
            'asn' => 'AS15169',
            'name' => 'Google LLC',
            'country' => 'US',
            'registry' => 'ARIN',
            'prefixes' => [
                ['netblock' => '8.8.8.0/24', 'id' => 'LVLT-GOGL-8-8-8', 'name' => 'Google LLC', 'country' => 'US'],
            ],
            'prefixes6' => [],
        ]),
    ]);

    $provider = makeIpInfo(resolve(HttpClient::class));
    $result = $provider->getAsn(15169);

    expect($result)->toBeInstanceOf(AsnResult::class)
        ->and($result->info->asn)->toBe(15169)
        ->and($result->info->name)->toBe('Google LLC')
        ->and($result->info->country)->toBe('US')
        ->and($result->info->rir)->toBe('ARIN')
        ->and($result->prefixes)->toHaveCount(1);
});

it('throws when getAsn data is empty via IPinfo', function (): void {
    Http::fake([
        'ipinfo.io/AS99999/json*' => Http::response([]),
    ]);

    $provider = makeIpInfo(resolve(HttpClient::class));
    $provider->getAsn(99999);
})->throws(AsnLookupException::class);

it('throws when HTTP request fails via IPinfo', function (): void {
    Http::fake([
        'api.ipinfo.io/*' => Http::response('Unauthorized', 401),
    ]);

    $provider = makeIpInfo(resolve(HttpClient::class));
    $provider->lookupIp('1.2.3.4');
})->throws(AsnLookupException::class);

it('handles missing optional fields in lookupIp via IPinfo', function (): void {
    Http::fake([
        'api.ipinfo.io/lite/*' => Http::response([
            'asn' => 'AS1234',
        ]),
    ]);

    $provider = makeIpInfo(resolve(HttpClient::class));
    $info = $provider->lookupIp('1.2.3.4');

    expect($info->asn)->toBe(1234)
        ->and($info->name)->toBe('')
        ->and($info->description)->toBe('')
        ->and($info->country)->toBeNull();
});
