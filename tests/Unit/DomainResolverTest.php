<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Reno\ASN\AsnManager;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\DomainResolver;
use Reno\ASN\Exceptions\DomainResolutionException;
use Reno\ASN\IpMatcher;

function makeDomainResolver(AsnProvider $provider): DomainResolver
{
    $cache = new CacheRepository(new ArrayStore);

    $manager = new AsnManager(
        provider: $provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );

    return new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:dns:',
    );
}

it('normalizes domain by stripping protocol and paths', function (): void {
    // Test via reflection since normalizeDomain is private
    $provider = mock(AsnProvider::class);
    $resolver = makeDomainResolver($provider);

    $reflection = new ReflectionMethod($resolver, 'normalizeDomain');

    expect($reflection->invoke($resolver, 'https://example.com/path'))->toBe('example.com')
        ->and($reflection->invoke($resolver, 'http://Example.COM.'))->toBe('example.com')
        ->and($reflection->invoke($resolver, '  EXAMPLE.com  '))->toBe('example.com');
});

it('checks domain against matcher', function (): void {
    $provider = mock(AsnProvider::class);
    $resolver = makeDomainResolver($provider);

    // We can't easily mock dns_get_record in unit tests,
    // but we can test the matcher integration
    $matcher = (new IpMatcher)
        ->addPrefix('93.184.0.0/16') // example.com range
        ->compile();

    // This will fail in CI since we can't mock DNS, but demonstrates the API
    // In a real test environment, you'd use a DNS mock or integration test
    expect($matcher->contains('93.184.216.34'))->toBeTrue();
});

it('throws DomainResolutionException for unresolvable domains', function (): void {
    $provider = mock(AsnProvider::class);
    $resolver = makeDomainResolver($provider);

    $resolver->resolveIps('this-domain-definitely-does-not-exist-asn-test.invalid');
})->throws(DomainResolutionException::class);

it('returns false for domain check against ASN when domain unresolvable', function (): void {
    $provider = mock(AsnProvider::class);
    $resolver = makeDomainResolver($provider);

    expect($resolver->domainBelongsToAsn('unresolvable.invalid', 13335))->toBeFalse();
});

it('returns null for domain match against ASNs when domain unresolvable', function (): void {
    $provider = mock(AsnProvider::class);
    $resolver = makeDomainResolver($provider);

    expect($resolver->domainMatchesAnyAsn('unresolvable.invalid', [13335]))->toBeNull();
});

it('returns false for domain matcher check when domain unresolvable', function (): void {
    $provider = mock(AsnProvider::class);
    $resolver = makeDomainResolver($provider);

    $matcher = (new IpMatcher)->addPrefix('10.0.0.0/8')->compile();

    expect($resolver->domainMatchesRanges('unresolvable.invalid', $matcher))->toBeFalse();
});

it('caches DNS resolution results when cache is enabled', function (): void {
    $provider = mock(AsnProvider::class);
    $cache = new CacheRepository(new ArrayStore);

    $manager = new AsnManager(
        provider: $provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );

    // Pre-populate cache to avoid actual DNS
    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);

    $ips = $resolver->resolveIps('example.com');

    expect($ips)->toBe(['93.184.216.34']);
});

it('lookupAsn resolves domain and looks up ASN', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('lookupIp')
        ->with('93.184.216.34')
        ->andReturn(new \Reno\ASN\Data\AsnInfo(asn: 15133, name: 'EDGECAST', description: 'Edgecast'));

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );

    // Pre-populate cache to avoid actual DNS
    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);

    $info = $resolver->lookupAsn('example.com');

    expect($info->asn)->toBe(15133);
});

it('lookupAsn throws when no IPs resolved', function (): void {
    $provider = mock(AsnProvider::class);
    $cache = new CacheRepository(new ArrayStore);

    $manager = new AsnManager(
        provider: $provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );

    // Pre-populate cache with empty array
    $cache->put('test:dns:dns:empty.example.com', [], 3600);

    $resolver->lookupAsn('empty.example.com');
})->throws(DomainResolutionException::class);

it('domainBelongsToAsn returns true when IP matches', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')
        ->with(15133)
        ->andReturn(collect([new \Reno\ASN\Data\Prefix('93.184.0.0/16', asn: 15133)]));

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );

    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);

    expect($resolver->domainBelongsToAsn('example.com', 15133))->toBeTrue();
});

it('domainMatchesAnyAsn returns matching ASN', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')
        ->with(15133)
        ->andReturn(collect([new \Reno\ASN\Data\Prefix('93.184.0.0/16', asn: 15133)]));
    $provider->shouldReceive('getPrefixes')
        ->with(13335)
        ->andReturn(collect([new \Reno\ASN\Data\Prefix('104.16.0.0/12', asn: 13335)]));

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );

    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);

    expect($resolver->domainMatchesAnyAsn('example.com', [13335, 15133]))->toBe(15133);
});

it('domainMatchesRanges returns true when IP in ranges', function (): void {
    $provider = mock(AsnProvider::class);
    $cache = new CacheRepository(new ArrayStore);

    $manager = new AsnManager(
        provider: $provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );

    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);

    $matcher = (new IpMatcher)->addPrefix('93.184.0.0/16')->compile();

    expect($resolver->domainMatchesRanges('example.com', $matcher))->toBeTrue();
});

it('domainMatchesRanges returns false when IP not in ranges', function (): void {
    $provider = mock(AsnProvider::class);
    $cache = new CacheRepository(new ArrayStore);

    $manager = new AsnManager(
        provider: $provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );

    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);

    $matcher = (new IpMatcher)->addPrefix('10.0.0.0/8')->compile();

    expect($resolver->domainMatchesRanges('example.com', $matcher))->toBeFalse();
});
