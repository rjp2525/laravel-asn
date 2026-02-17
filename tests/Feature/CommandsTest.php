<?php

declare(strict_types=1);

use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Data\Prefix;
use Reno\ASN\Exceptions\AsnLookupException;

beforeEach(function (): void {
    $this->provider = mock(AsnProvider::class);
    $this->app->instance(AsnProvider::class, $this->provider);
});

it('runs asn:lookup successfully', function (): void {
    $info = new AsnInfo(asn: 13335, name: 'CLOUDFLARENET', description: 'Cloudflare', country: 'US');
    $prefixes = collect([
        new Prefix('104.16.0.0/12', 'CF', 'CDN', 'US'),
    ]);

    $this->provider->shouldReceive('lookupIp')->with('104.16.0.1')->andReturn($info);
    $this->provider->shouldReceive('getPrefixes')->with(13335)->andReturn($prefixes);

    $this->artisan('asn:lookup', ['ip' => '104.16.0.1'])
        ->expectsOutputToContain('AS13335')
        ->expectsOutputToContain('CLOUDFLARENET')
        ->assertSuccessful();
});

it('handles lookup failure gracefully', function (): void {
    $this->provider
        ->shouldReceive('lookupIp')
        ->andThrow(AsnLookupException::ipNotFound('999.999.999.999'));

    $this->artisan('asn:lookup', ['ip' => '999.999.999.999'])
        ->expectsOutputToContain('No ASN information found')
        ->assertFailed();
});

it('runs asn:check and finds match', function (): void {
    $prefixes = collect([new Prefix('104.16.0.0/12')]);
    $info = new AsnInfo(asn: 13335, name: 'CF', description: 'Cloudflare');

    $this->provider->shouldReceive('getPrefixes')->with(13335)->andReturn($prefixes);
    $this->provider->shouldReceive('getAsn')->with(13335)->andReturn(
        new AsnResult(info: $info, prefixes: $prefixes),
    );

    $this->artisan('asn:check', ['ip' => '104.16.0.1', 'asn' => '13335'])
        ->expectsOutputToContain('belongs to AS13335')
        ->assertSuccessful();
});

it('runs asn:check and finds no match', function (): void {
    $this->provider->shouldReceive('getPrefixes')->with(13335)->andReturn(
        collect([new Prefix('104.16.0.0/12')]),
    );

    $this->artisan('asn:check', ['ip' => '8.8.8.8', 'asn' => '13335'])
        ->expectsOutputToContain('does NOT belong')
        ->assertFailed();
});
