<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Reno\ASN\AsnManager;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Data\Prefix;
use Reno\ASN\DomainResolver;
use Reno\ASN\Rules\DomainInAsn;

function validateDomainRule(object $rule, mixed $value): array
{
    $errors = [];
    $rule->validate('field', $value, function (string $msg) use (&$errors): void {
        $errors[] = $msg;
    });

    return $errors;
}

it('DomainInAsn fails for empty value', function (): void {
    expect(validateDomainRule(new DomainInAsn(13335), ''))->toHaveCount(1)
        ->and(validateDomainRule(new DomainInAsn(13335), null))->toHaveCount(1)
        ->and(validateDomainRule(new DomainInAsn(13335), 123))->toHaveCount(1);
});

it('DomainInAsn passes when domain matches ASN', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')->with(13335)->andReturn(collect([
        new Prefix('93.184.0.0/16', asn: 13335),
    ]));

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $provider,
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

    expect(validateDomainRule(new DomainInAsn(13335), 'example.com'))->toBeEmpty();
});

it('DomainInAsn fails when domain does not match ASN', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')->with(13335)->andReturn(collect([
        new Prefix('104.16.0.0/12', asn: 13335),
    ]));

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $provider,
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
    // IP not in 104.16.0.0/12
    $cache->put('test:dns:dns:example.com', ['93.184.216.34'], 3600);
    $this->app->instance(DomainResolver::class, $resolver);

    $errors = validateDomainRule(new DomainInAsn(13335), 'example.com');

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('AS13335');
});

it('DomainInAsn fails when domain cannot be resolved', function (): void {
    $provider = mock(AsnProvider::class);

    $cache = new CacheRepository(new ArrayStore);
    $manager = new AsnManager(
        provider: $provider,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    $this->app->instance(AsnManager::class, $manager);

    // Create resolver that will fail on DNS (no cache, domain won't resolve)
    $resolver = new DomainResolver(
        asnManager: $manager,
        cache: $cache,
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:dns:',
    );
    $this->app->instance(DomainResolver::class, $resolver);

    $errors = validateDomainRule(new DomainInAsn(13335), 'this-will-not-resolve.invalid');

    // domainMatchesAnyAsn catches DomainResolutionException and returns null,
    // so the rule sees null and shows "does not resolve to any of the following ASNs"
    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('AS13335');
});
