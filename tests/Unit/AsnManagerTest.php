<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Reno\ASN\AsnManager;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Data\Prefix;
use Reno\ASN\IpMatcher;

function makeManager(
    AsnProvider $provider,
    bool $cacheEnabled = false,
): AsnManager {
    return new AsnManager(
        provider: $provider,
        cache: new CacheRepository(new ArrayStore),
        cacheEnabled: $cacheEnabled,
        cacheTtl: 3600,
        cachePrefix: 'asn:test:',
    );
}

function fakeCloudflareProvider(): AsnProvider
{
    $provider = mock(AsnProvider::class);

    $info = new AsnInfo(
        asn: 13335,
        name: 'CLOUDFLARENET',
        description: 'Cloudflare, Inc.',
        country: 'US',
    );

    $prefixes = collect([
        new Prefix('104.16.0.0/12', 'Cloudflare', 'CDN', 'US', 13335),
        new Prefix('172.64.0.0/13', 'Cloudflare', 'CDN', 'US', 13335),
        new Prefix('2606:4700::/32', 'Cloudflare', 'CDN IPv6', 'US', 13335),
    ]);

    $provider->shouldReceive('lookupIp')->andReturn($info);
    $provider->shouldReceive('getPrefixes')->with(13335)->andReturn($prefixes);
    $provider->shouldReceive('getAsn')->with(13335)->andReturn(
        new AsnResult(info: $info, prefixes: $prefixes),
    );

    return $provider;
}

it('looks up an IP and returns ASN info', function (): void {
    $manager = makeManager(fakeCloudflareProvider());
    $info = $manager->lookupIp('104.16.0.1');

    expect($info->asn)->toBe(13335)
        ->and($info->name)->toBe('CLOUDFLARENET');
});

it('retrieves prefixes for an ASN', function (): void {
    $manager = makeManager(fakeCloudflareProvider());
    $prefixes = $manager->getPrefixes(13335);

    expect($prefixes)->toHaveCount(3)
        ->and($prefixes->first()->prefix)->toBe('104.16.0.0/12');
});

it('checks if an IP belongs to an ASN', function (): void {
    $manager = makeManager(fakeCloudflareProvider());

    expect($manager->ipBelongsToAsn('104.16.0.1', 13335))->toBeTrue()
        ->and($manager->ipBelongsToAsn('8.8.8.8', 13335))->toBeFalse();
});

it('checks IPv6 belongs to ASN', function (): void {
    $manager = makeManager(fakeCloudflareProvider());

    expect($manager->ipBelongsToAsn('2606:4700::1', 13335))->toBeTrue()
        ->and($manager->ipBelongsToAsn('2001:4860::1', 13335))->toBeFalse();
});

it('checks if two IPs belong to the same ASN', function (): void {
    $manager = makeManager(fakeCloudflareProvider());

    expect($manager->ipBelongsToSameAsn('104.16.0.1', '172.64.0.1'))->toBeTrue()
        ->and($manager->ipBelongsToSameAsn('104.16.0.1', '8.8.8.8'))->toBeFalse();
});

it('matches IP against multiple ASNs', function (): void {
    $provider = mock(AsnProvider::class);

    $provider->shouldReceive('getPrefixes')->with(13335)->andReturn(collect([
        new Prefix('104.16.0.0/12', asn: 13335),
    ]));
    $provider->shouldReceive('getPrefixes')->with(15169)->andReturn(collect([
        new Prefix('8.8.8.0/24', asn: 15169),
    ]));

    $manager = makeManager($provider);

    expect($manager->ipMatchesAnyAsn('8.8.8.8', [13335, 15169]))->toBe(15169)
        ->and($manager->ipMatchesAnyAsn('1.1.1.1', [13335, 15169]))->toBeNull();
});

it('builds an IpMatcher from ASN prefixes', function (): void {
    $manager = makeManager(fakeCloudflareProvider());

    $matcher = $manager->buildMatcher(13335);

    expect($matcher)->toBeInstanceOf(IpMatcher::class)
        ->and($matcher->count())->toBe(3)
        ->and($matcher->contains('104.16.0.1'))->toBeTrue()
        ->and($matcher->contains('8.8.8.8'))->toBeFalse();
});

it('builds a matcher from multiple ASNs', function (): void {
    $provider = mock(AsnProvider::class);

    $provider->shouldReceive('getPrefixes')->with(13335)->andReturn(collect([
        new Prefix('104.16.0.0/12', asn: 13335),
    ]));
    $provider->shouldReceive('getPrefixes')->with(15169)->andReturn(collect([
        new Prefix('8.8.8.0/24', asn: 15169),
    ]));

    $manager = makeManager($provider);
    $matcher = $manager->buildMatcher([13335, 15169]);

    expect($matcher->count())->toBe(2)
        ->and($matcher->contains('104.16.0.1'))->toBeTrue()
        ->and($matcher->contains('8.8.8.8'))->toBeTrue();
});

it('batch checks IPs against ASNs', function (): void {
    $manager = makeManager(fakeCloudflareProvider());

    $results = $manager->batchCheck(
        ips: ['104.16.0.1', '8.8.8.8', '172.64.0.1'],
        asns: [13335],
    );

    expect($results)->toHaveCount(3)
        ->and($results[0]->matched)->toBeTrue()
        ->and($results[1]->matched)->toBeFalse()
        ->and($results[2]->matched)->toBeTrue();
});

it('caches results when enabled', function (): void {
    $provider = mock(AsnProvider::class);

    $provider->shouldReceive('lookupIp')
        ->once()
        ->andReturn(new AsnInfo(asn: 13335, name: 'CF', description: 'Cloudflare'));

    $manager = makeManager($provider, cacheEnabled: true);

    $manager->lookupIp('104.16.0.1');
    $manager->lookupIp('104.16.0.1');

    expect(true)->toBeTrue();
});

it('bypasses cache when disabled', function (): void {
    $provider = mock(AsnProvider::class);

    $provider->shouldReceive('lookupIp')
        ->twice()
        ->andReturn(new AsnInfo(asn: 13335, name: 'CF', description: 'Cloudflare'));

    $manager = makeManager($provider, cacheEnabled: false);

    $manager->lookupIp('104.16.0.1');
    $manager->lookupIp('104.16.0.1');

    expect(true)->toBeTrue();
});

it('flushes ASN cache', function (): void {
    $provider = mock(AsnProvider::class);

    $provider->shouldReceive('getPrefixes')
        ->twice()
        ->with(13335)
        ->andReturn(collect([new Prefix('104.16.0.0/12')]));

    $manager = makeManager($provider, cacheEnabled: true);

    $manager->getPrefixes(13335);
    $manager->flushAsn(13335);
    $manager->getPrefixes(13335);

    expect(true)->toBeTrue();
});

it('flushes IP cache', function (): void {
    $provider = mock(AsnProvider::class);

    $provider->shouldReceive('lookupIp')
        ->twice()
        ->andReturn(new AsnInfo(asn: 13335, name: 'CF', description: 'Cloudflare'));

    $manager = makeManager($provider, cacheEnabled: true);

    $manager->lookupIp('104.16.0.1');
    $manager->flushIp('104.16.0.1');
    $manager->lookupIp('104.16.0.1');

    expect(true)->toBeTrue();
});

it('exposes the underlying provider', function (): void {
    $provider = mock(AsnProvider::class);
    $manager = makeManager($provider);

    expect($manager->provider())->toBe($provider);
});
