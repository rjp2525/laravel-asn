<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Reno\ASN\AsnManager;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\DomainResolver;
use Reno\ASN\Providers\RipeStatProvider;

it('registers the ASN manager as a singleton', function (): void {
    $manager = resolve(AsnManager::class);

    expect($manager)->toBeInstanceOf(AsnManager::class)
        ->and(resolve(AsnManager::class))->toBe($manager);
});

it('resolves the default provider as RipeStat', function (): void {
    $provider = resolve(AsnProvider::class);

    expect($provider)->toBeInstanceOf(RipeStatProvider::class);
});

it('registers the asn alias', function (): void {
    expect(resolve('asn'))->toBeInstanceOf(AsnManager::class);
});

it('registers the domain resolver', function (): void {
    $resolver = resolve(DomainResolver::class);

    expect($resolver)->toBeInstanceOf(DomainResolver::class)
        ->and(resolve('asn.dns'))->toBe($resolver);
});

it('registers query macros on eloquent builder', function (): void {
    expect(Builder::hasGlobalMacro('whereIpInRange'))->toBeTrue();
});

it('registers query macros on base query builder', function (): void {
    $macros = ['whereIpInRange'];

    foreach ($macros as $macro) {
        expect(QueryBuilder::hasMacro($macro))->toBeTrue();
    }
});
