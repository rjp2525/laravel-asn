<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Collection;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Exceptions\AsnLookupException;
use Reno\ASN\Providers\RipeStatProvider;

class StubCustomProvider implements AsnProvider
{
    public function __construct(
        public readonly HttpClient $http,
        public readonly int $timeout,
        public readonly int $retryTimes,
        public readonly int $retryDelay,
    ) {}

    public function lookupIp(string $ip): AsnInfo
    {
        return new AsnInfo(asn: 99999, name: 'Stub', description: 'Stub Provider', country: 'US');
    }

    public function getPrefixes(int $asn): Collection
    {
        return collect();
    }

    public function getAsn(int $asn): AsnResult
    {
        return new AsnResult(
            info: new AsnInfo(asn: $asn, name: 'Stub', description: 'Stub', country: 'US'),
            prefixes: collect(),
        );
    }
}

it('resolves a custom provider registered via config', function (): void {
    config([
        'asn.provider' => 'custom-stub',
        'asn.providers.custom-stub' => StubCustomProvider::class,
    ]);

    $this->app->forgetInstance(AsnProvider::class);

    $provider = resolve(AsnProvider::class);

    expect($provider)->toBeInstanceOf(StubCustomProvider::class);
});

it('passes HTTP client and config values to custom provider', function (): void {
    config([
        'asn.provider' => 'custom-stub',
        'asn.providers.custom-stub' => StubCustomProvider::class,
        'asn.http.timeout' => 30,
        'asn.http.retry_times' => 5,
        'asn.http.retry_delay' => 1000,
    ]);

    $this->app->forgetInstance(AsnProvider::class);

    /** @var StubCustomProvider $provider */
    $provider = resolve(AsnProvider::class);

    expect($provider->timeout)->toBe(30)
        ->and($provider->retryTimes)->toBe(5)
        ->and($provider->retryDelay)->toBe(1000)
        ->and($provider->http)->toBeInstanceOf(HttpClient::class);
});

it('throws for an invalid provider name', function (): void {
    config(['asn.provider' => 'does-not-exist']);

    $this->app->forgetInstance(AsnProvider::class);

    resolve(AsnProvider::class);
})->throws(AsnLookupException::class, 'Unsupported ASN provider: does-not-exist');

it('still resolves default providers when providers config is set', function (): void {
    // RipeStat (default)
    $this->app->forgetInstance(AsnProvider::class);
    expect(resolve(AsnProvider::class))->toBeInstanceOf(RipeStatProvider::class);
});
