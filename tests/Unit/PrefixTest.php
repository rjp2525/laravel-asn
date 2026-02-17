<?php

declare(strict_types=1);

use Reno\ASN\Data\Prefix;

it('parses a valid IPv4 CIDR prefix', function (): void {
    $prefix = new Prefix('192.168.1.0/24');

    expect($prefix->network)->toBe('192.168.1.0')
        ->and($prefix->cidr)->toBe(24)
        ->and($prefix->isIpv6)->toBeFalse();
});

it('parses a valid IPv6 CIDR prefix', function (): void {
    $prefix = new Prefix('2606:4700::/32');

    expect($prefix->network)->toBe('2606:4700::')
        ->and($prefix->cidr)->toBe(32)
        ->and($prefix->isIpv6)->toBeTrue();
});

it('throws on invalid CIDR notation', function (): void {
    new Prefix('192.168.1.0');
})->throws(InvalidArgumentException::class);

it('detects IP within IPv4 prefix', function (): void {
    $prefix = new Prefix('104.16.0.0/12');

    expect($prefix->contains('104.16.0.1'))->toBeTrue()
        ->and($prefix->contains('104.31.255.255'))->toBeTrue()
        ->and($prefix->contains('104.32.0.0'))->toBeFalse();
});

it('detects IP within /32 single host', function (): void {
    $prefix = new Prefix('10.0.0.1/32');

    expect($prefix->contains('10.0.0.1'))->toBeTrue()
        ->and($prefix->contains('10.0.0.2'))->toBeFalse();
});

it('detects IP within /0 matches all', function (): void {
    $prefix = new Prefix('0.0.0.0/0');

    expect($prefix->contains('1.2.3.4'))->toBeTrue()
        ->and($prefix->contains('255.255.255.255'))->toBeTrue();
});

it('detects IP within IPv6 prefix', function (): void {
    $prefix = new Prefix('2606:4700::/32');

    expect($prefix->contains('2606:4700::1'))->toBeTrue()
        ->and($prefix->contains('2606:4700:ffff::1'))->toBeTrue()
        ->and($prefix->contains('2607:4700::1'))->toBeFalse();
});

it('rejects cross-family checks (IPv4 in IPv6 prefix)', function (): void {
    $prefix = new Prefix('2606:4700::/32');

    expect($prefix->contains('104.16.0.1'))->toBeFalse();
});

it('rejects cross-family checks (IPv6 in IPv4 prefix)', function (): void {
    $prefix = new Prefix('104.16.0.0/12');

    expect($prefix->contains('2606:4700::1'))->toBeFalse();
});

it('handles invalid IPs gracefully', function (): void {
    $prefix = new Prefix('104.16.0.0/12');

    expect($prefix->contains('not-an-ip'))->toBeFalse();
});

it('serializes to array', function (): void {
    $prefix = new Prefix(
        prefix: '104.16.0.0/12',
        name: 'Cloudflare',
        description: 'CDN',
        country: 'US',
    );

    expect($prefix->toArray())->toBe([
        'prefix' => '104.16.0.0/12',
        'network' => '104.16.0.0',
        'cidr' => 12,
        'is_ipv6' => false,
        'name' => 'Cloudflare',
        'description' => 'CDN',
        'country' => 'US',
        'asn' => null,
    ]);
});

it('handles /8 prefix boundaries correctly', function (): void {
    $prefix = new Prefix('10.0.0.0/8');

    expect($prefix->contains('10.0.0.1'))->toBeTrue()
        ->and($prefix->contains('10.255.255.255'))->toBeTrue()
        ->and($prefix->contains('11.0.0.0'))->toBeFalse();
});

it('computes the end address for IPv4 prefix', function (): void {
    $prefix = new Prefix('10.0.0.0/24');

    expect($prefix->endAddress())->toBe('10.0.0.255');
});

it('computes the end address for IPv6 prefix', function (): void {
    $prefix = new Prefix('2606:4700::/32');

    expect($prefix->endAddress())->toBe('2606:4700:ffff:ffff:ffff:ffff:ffff:ffff');
});

it('computes the end address for /32 single host', function (): void {
    $prefix = new Prefix('10.0.0.1/32');

    expect($prefix->endAddress())->toBe('10.0.0.1');
});

it('converts to IpRange via toRange', function (): void {
    $prefix = new Prefix('192.168.1.0/24');
    $range = $prefix->toRange();

    expect($range->startAddress())->toBe('192.168.1.0')
        ->and($range->endAddress())->toBe('192.168.1.255')
        ->and($range->isIpv6)->toBeFalse()
        ->and($range->prefix)->toBe('192.168.1.0/24');
});

it('builds mask correctly', function (): void {
    // /8 = 255.0.0.0
    expect(Prefix::buildMask(32, 8))->toBe("\xff\x00\x00\x00")
        // /24 = 255.255.255.0
        ->and(Prefix::buildMask(32, 24))->toBe("\xff\xff\xff\x00")
        // /32 = 255.255.255.255
        ->and(Prefix::buildMask(32, 32))->toBe("\xff\xff\xff\xff")
        // /0 = 0.0.0.0
        ->and(Prefix::buildMask(32, 0))->toBe("\x00\x00\x00\x00");
});

it('handles /16 prefix boundaries correctly', function (): void {
    $prefix = new Prefix('172.16.0.0/16');

    expect($prefix->contains('172.16.0.1'))->toBeTrue()
        ->and($prefix->contains('172.16.255.255'))->toBeTrue()
        ->and($prefix->contains('172.17.0.0'))->toBeFalse();
});
