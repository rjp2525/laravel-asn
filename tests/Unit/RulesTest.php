<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Reno\ASN\AsnManager;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Data\Prefix;
use Reno\ASN\Exceptions\AsnLookupException;
use Reno\ASN\Rules\IpInAsn;
use Reno\ASN\Rules\IpInRange;
use Reno\ASN\Rules\IpNotInAsn;

function mockAsnManager(array $asnPrefixes = []): void
{
    $provider = mock(AsnProvider::class);

    foreach ($asnPrefixes as $asn => $prefixes) {
        $provider->shouldReceive('getPrefixes')
            ->with($asn)
            ->andReturn(collect(array_map(
                fn (string $cidr) => new Prefix($cidr, asn: $asn),
                $prefixes,
            )));
    }

    $manager = new AsnManager(
        provider: $provider,
        cache: new CacheRepository(new ArrayStore),
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );

    app()->instance(AsnManager::class, $manager);
}

function validateRule(object $rule, mixed $value): array
{
    $errors = [];
    $rule->validate('field', $value, function (string $msg) use (&$errors): void {
        $errors[] = $msg;
    });

    return $errors;
}

it('IpInRange passes for IP within range', function (): void {
    $rule = new IpInRange('10.0.0.0/8', '172.16.0.0/12');

    expect(validateRule($rule, '10.1.2.3'))->toBeEmpty()
        ->and(validateRule($rule, '172.20.0.1'))->toBeEmpty();
});

it('IpInRange fails for IP outside all ranges', function (): void {
    $rule = new IpInRange('10.0.0.0/8');

    expect(validateRule($rule, '8.8.8.8'))->toHaveCount(1);
});

it('IpInRange fails for non-IP value', function (): void {
    $rule = new IpInRange('10.0.0.0/8');

    expect(validateRule($rule, 'not-an-ip'))->toHaveCount(1)
        ->and(validateRule($rule, 123))->toHaveCount(1)
        ->and(validateRule($rule, null))->toHaveCount(1);
});

it('IpInAsn passes for IP in the specified ASN', function (): void {
    mockAsnManager([13335 => ['104.16.0.0/12']]);

    $rule = new IpInAsn(13335);

    expect(validateRule($rule, '104.16.0.1'))->toBeEmpty();
});

it('IpInAsn fails for IP not in the specified ASN', function (): void {
    mockAsnManager([13335 => ['104.16.0.0/12']]);

    $rule = new IpInAsn(13335);

    expect(validateRule($rule, '8.8.8.8'))->toHaveCount(1);
});

it('IpInAsn checks multiple ASNs', function (): void {
    mockAsnManager([
        13335 => ['104.16.0.0/12'],
        15169 => ['8.8.8.0/24'],
    ]);

    $rule = new IpInAsn(13335, 15169);

    expect(validateRule($rule, '8.8.8.8'))->toBeEmpty();
});

it('IpInAsn handles lookup failures gracefully', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')
        ->andThrow(AsnLookupException::asnNotFound(99999));

    $manager = new AsnManager(
        provider: $provider,
        cache: new CacheRepository(new ArrayStore),
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    app()->instance(AsnManager::class, $manager);

    $rule = new IpInAsn(99999);

    expect(validateRule($rule, '1.2.3.4'))->toHaveCount(1);
});

it('IpInAsn fails for non-IP values', function (): void {
    mockAsnManager([13335 => ['104.16.0.0/12']]);

    $rule = new IpInAsn(13335);

    expect(validateRule($rule, 'not-an-ip'))->toHaveCount(1)
        ->and(validateRule($rule, null))->toHaveCount(1)
        ->and(validateRule($rule, 123))->toHaveCount(1);
});

it('IpNotInAsn passes for IP not in blocked ASN', function (): void {
    mockAsnManager([7922 => ['73.0.0.0/8']]);

    $rule = new IpNotInAsn(7922);

    expect(validateRule($rule, '8.8.8.8'))->toBeEmpty();
});

it('IpNotInAsn fails for IP in blocked ASN', function (): void {
    mockAsnManager([7922 => ['73.0.0.0/8']]);

    $rule = new IpNotInAsn(7922);

    $errors = validateRule($rule, '73.1.2.3');

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('AS7922');
});

it('IpNotInAsn fails for non-IP values', function (): void {
    $rule = new IpNotInAsn(7922);

    expect(validateRule($rule, 'not-an-ip'))->toHaveCount(1)
        ->and(validateRule($rule, null))->toHaveCount(1);
});

it('IpNotInAsn fails open on lookup errors', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')
        ->andThrow(AsnLookupException::asnNotFound(99999));

    $manager = new AsnManager(
        provider: $provider,
        cache: new CacheRepository(new ArrayStore),
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    app()->instance(AsnManager::class, $manager);

    $rule = new IpNotInAsn(99999);

    // Should pass (fail open)
    expect(validateRule($rule, '1.2.3.4'))->toBeEmpty();
});
