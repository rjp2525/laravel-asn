<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Reno\ASN\AsnManager;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\Prefix;
use Reno\ASN\DomainResolver;
use Reno\ASN\Exceptions\AsnLookupException;

beforeEach(function (): void {
    $this->provider = mock(AsnProvider::class);
    $this->app->instance(AsnProvider::class, $this->provider);
});

it('runs asn:prefixes and lists all prefixes', function (): void {
    $this->provider->shouldReceive('getPrefixes')->with(13335)->andReturn(collect([
        new Prefix('104.16.0.0/12', 'Cloudflare', 'CDN', 'US', 13335),
        new Prefix('2606:4700::/32', 'Cloudflare IPv6', null, 'US', 13335),
    ]));

    $this->artisan('asn:prefixes', ['asn' => '13335'])
        ->assertSuccessful();
});

it('runs asn:prefixes with --ipv4-only', function (): void {
    $this->provider->shouldReceive('getPrefixes')->with(13335)->andReturn(collect([
        new Prefix('104.16.0.0/12', 'Cloudflare', null, 'US', 13335),
        new Prefix('2606:4700::/32', 'Cloudflare IPv6', null, 'US', 13335),
    ]));

    $this->artisan('asn:prefixes', ['asn' => '13335', '--ipv4-only' => true])
        ->assertSuccessful();
});

it('runs asn:prefixes with --ipv6-only', function (): void {
    $this->provider->shouldReceive('getPrefixes')->with(13335)->andReturn(collect([
        new Prefix('104.16.0.0/12', 'Cloudflare', null, 'US', 13335),
        new Prefix('2606:4700::/32', 'Cloudflare IPv6', null, 'US', 13335),
    ]));

    $this->artisan('asn:prefixes', ['asn' => '13335', '--ipv6-only' => true])
        ->assertSuccessful();
});

it('runs asn:prefixes with --count', function (): void {
    $this->provider->shouldReceive('getPrefixes')->with(13335)->andReturn(collect([
        new Prefix('104.16.0.0/12', asn: 13335),
        new Prefix('172.64.0.0/13', asn: 13335),
    ]));

    $this->artisan('asn:prefixes', ['asn' => '13335', '--count' => true])
        ->assertSuccessful();
});

it('handles asn:prefixes failure', function (): void {
    $this->provider->shouldReceive('getPrefixes')
        ->andThrow(AsnLookupException::asnNotFound(99999));

    $this->artisan('asn:prefixes', ['asn' => '99999'])
        ->assertFailed();
});

it('runs asn:domain successfully', function (): void {
    $this->provider->shouldReceive('lookupIp')
        ->with('93.184.216.34')
        ->andReturn(new AsnInfo(asn: 15133, name: 'EDGECAST', description: 'Edgecast Inc.', country: 'US'));

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $this->provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    $this->app->instance(AsnManager::class, $manager);

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );
    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);
    $this->app->instance(DomainResolver::class, $resolver);

    $this->artisan('asn:domain', ['domain' => 'example.com'])
        ->expectsOutputToContain('example.com')
        ->expectsOutputToContain('AS15133')
        ->assertSuccessful();
});

it('runs asn:domain with --check-asn that matches', function (): void {
    $this->provider->shouldReceive('lookupIp')
        ->andReturn(new AsnInfo(asn: 15133, name: 'EDGECAST', description: 'Edgecast Inc.', country: 'US'));
    $this->provider->shouldReceive('getPrefixes')
        ->with(15133)
        ->andReturn(collect([new Prefix('93.184.0.0/16', asn: 15133)]));

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $this->provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    $this->app->instance(AsnManager::class, $manager);

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );
    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);
    $this->app->instance(DomainResolver::class, $resolver);

    $this->artisan('asn:domain', ['domain' => 'example.com', '--check-asn' => '15133'])
        ->expectsOutputToContain('belongs to')
        ->assertSuccessful();
});

it('runs asn:domain with --check-asn that does not match', function (): void {
    $this->provider->shouldReceive('lookupIp')
        ->andReturn(new AsnInfo(asn: 15133, name: 'EDGECAST', description: 'Edgecast Inc.', country: 'US'));
    $this->provider->shouldReceive('getPrefixes')
        ->with(13335)
        ->andReturn(collect([new Prefix('104.16.0.0/12', asn: 13335)]));

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $this->provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    $this->app->instance(AsnManager::class, $manager);

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );
    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);
    $this->app->instance(DomainResolver::class, $resolver);

    $this->artisan('asn:domain', ['domain' => 'example.com', '--check-asn' => '13335'])
        ->expectsOutputToContain('does NOT belong to')
        ->assertFailed();
});

it('handles asn:domain resolution failure', function (): void {
    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $this->provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    $this->app->instance(AsnManager::class, $manager);

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:dns:',
    );
    $this->app->instance(DomainResolver::class, $resolver);

    $this->artisan('asn:domain', ['domain' => 'nonexistent.invalid'])
        ->assertFailed();
});

it('handles asn:domain lookup failure', function (): void {
    $this->provider->shouldReceive('lookupIp')
        ->andThrow(AsnLookupException::ipNotFound('1.2.3.4'));

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $this->provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    $this->app->instance(AsnManager::class, $manager);

    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: true,
        cacheTtl: 3600,
        cachePrefix: 'test:dns:',
    );
    $cache->put('test:dns:dns:example.com', ['1.2.3.4'], 3600);
    $this->app->instance(DomainResolver::class, $resolver);

    $this->artisan('asn:domain', ['domain' => 'example.com'])
        ->assertFailed();
});

it('handles asn:check lookup exception', function (): void {
    $this->provider->shouldReceive('getPrefixes')
        ->andThrow(AsnLookupException::asnNotFound(99999));

    $this->artisan('asn:check', ['ip' => '1.2.3.4', 'asn' => '99999'])
        ->assertFailed();
});
