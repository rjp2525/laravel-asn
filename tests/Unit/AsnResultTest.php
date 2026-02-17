<?php

declare(strict_types=1);

use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Data\Prefix;

function makeResult(): AsnResult
{
    return new AsnResult(
        info: new AsnInfo(asn: 13335, name: 'CF', description: 'Cloudflare', country: 'US'),
        prefixes: collect([
            new Prefix('104.16.0.0/12', 'Cloudflare IPv4'),
            new Prefix('172.64.0.0/13', 'Cloudflare IPv4 Alt'),
            new Prefix('2606:4700::/32', 'Cloudflare IPv6'),
        ]),
    );
}

it('checks if an IP is within any prefix', function (): void {
    $result = makeResult();

    expect($result->containsIp('104.16.0.1'))->toBeTrue()
        ->and($result->containsIp('8.8.8.8'))->toBeFalse();
});

it('finds the matching prefix for an IP', function (): void {
    $result = makeResult();

    $prefix = $result->findPrefixForIp('172.64.1.1');

    expect($prefix)->not->toBeNull()
        ->and($prefix->prefix)->toBe('172.64.0.0/13');
});

it('returns null when no prefix matches', function (): void {
    $result = makeResult();

    expect($result->findPrefixForIp('8.8.8.8'))->toBeNull();
});

it('filters IPv4 prefixes', function (): void {
    $result = makeResult();

    $ipv4 = $result->ipv4Prefixes();

    expect($ipv4)->toHaveCount(2)
        ->and($ipv4->every(fn (Prefix $p) => ! $p->isIpv6))->toBeTrue();
});

it('filters IPv6 prefixes', function (): void {
    $result = makeResult();

    $ipv6 = $result->ipv6Prefixes();

    expect($ipv6)->toHaveCount(1)
        ->and($ipv6->every(fn (Prefix $p) => $p->isIpv6))->toBeTrue();
});

it('serializes to array', function (): void {
    $result = makeResult();
    $array = $result->toArray();

    expect($array)->toHaveKeys(['info', 'prefixes'])
        ->and($array['info']['asn'])->toBe(13335)
        ->and($array['prefixes'])->toHaveCount(3);
});
