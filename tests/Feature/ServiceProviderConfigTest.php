<?php

declare(strict_types=1);

use Reno\ASN\AsnManager;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Exceptions\AsnLookupException;
use Reno\ASN\Providers\RipeStatProvider;

it('resolves RipeStat provider when configured', function (): void {
    config(['asn.provider' => 'ripestat']);

    // Clear the singleton to force re-resolution
    $this->app->forgetInstance(AsnProvider::class);

    $provider = resolve(AsnProvider::class);

    expect($provider)->toBeInstanceOf(RipeStatProvider::class);
});

it('throws for invalid provider configuration', function (): void {
    config(['asn.provider' => 'nonexistent']);

    $this->app->forgetInstance(AsnProvider::class);

    resolve(AsnProvider::class);
})->throws(AsnLookupException::class);

it('uses custom cache store when configured', function (): void {
    config(['asn.cache.store' => 'array']);

    $this->app->forgetInstance(AsnManager::class);

    $manager = resolve(AsnManager::class);

    expect($manager)->toBeInstanceOf(AsnManager::class);
});
