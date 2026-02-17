<?php

declare(strict_types=1);

use Reno\ASN\Data\IpRange;
use Reno\ASN\Data\Prefix;

it('creates from a Prefix object', function (): void {
    $prefix = new Prefix('10.0.0.0/8', asn: 1234);
    $range = IpRange::fromPrefix($prefix);

    expect($range->startAddress())->toBe('10.0.0.0')
        ->and($range->endAddress())->toBe('10.255.255.255')
        ->and($range->isIpv6)->toBeFalse()
        ->and($range->asn)->toBe(1234)
        ->and($range->prefix)->toBe('10.0.0.0/8');
});

it('creates from a /32 single host', function (): void {
    $range = IpRange::fromPrefix(new Prefix('10.0.0.1/32'));

    expect($range->startAddress())->toBe('10.0.0.1')
        ->and($range->endAddress())->toBe('10.0.0.1');
});

it('creates from a /0 all-match', function (): void {
    $range = IpRange::fromPrefix(new Prefix('0.0.0.0/0'));

    expect($range->startAddress())->toBe('0.0.0.0')
        ->and($range->endAddress())->toBe('255.255.255.255');
});

it('creates from a single IP', function (): void {
    $range = IpRange::fromIp('1.2.3.4', asn: 100, label: 'test');

    expect($range->startAddress())->toBe('1.2.3.4')
        ->and($range->endAddress())->toBe('1.2.3.4')
        ->and($range->asn)->toBe(100)
        ->and($range->prefix)->toBe('1.2.3.4/32');
});

it('creates from explicit range', function (): void {
    $range = IpRange::fromRange('10.0.0.1', '10.0.0.100');

    expect($range->contains(inet_pton('10.0.0.50')))->toBeTrue()
        ->and($range->contains(inet_pton('10.0.0.101')))->toBeFalse();
});

it('creates IPv6 range from prefix', function (): void {
    $range = IpRange::fromPrefix(new Prefix('2606:4700::/32'));

    expect($range->isIpv6)->toBeTrue()
        ->and($range->startAddress())->toBe('2606:4700::')
        ->and($range->contains(inet_pton('2606:4700::1')))->toBeTrue()
        ->and($range->contains(inet_pton('2607:4700::1')))->toBeFalse();
});

it('converts IPv4 range to longs', function (): void {
    $range = IpRange::fromPrefix(new Prefix('10.0.0.0/24'));

    expect($range->startLong())->toBe(ip2long('10.0.0.0'))
        ->and($range->endLong())->toBe(ip2long('10.0.0.255'));
});

it('throws when converting IPv6 startLong', function (): void {
    $range = IpRange::fromPrefix(new Prefix('2606:4700::/32'));

    $range->startLong();
})->throws(RuntimeException::class);

it('throws when converting IPv6 endLong', function (): void {
    $range = IpRange::fromPrefix(new Prefix('2606:4700::/32'));

    $range->endLong();
})->throws(RuntimeException::class);

it('throws for invalid IP in fromIp', function (): void {
    IpRange::fromIp('not-an-ip');
})->throws(InvalidArgumentException::class);

it('throws for invalid IP in fromRange', function (): void {
    IpRange::fromRange('not-valid', '10.0.0.1');
})->throws(InvalidArgumentException::class);

it('creates IPv6 single IP via fromIp', function (): void {
    $range = IpRange::fromIp('2606:4700::1');

    expect($range->isIpv6)->toBeTrue()
        ->and($range->prefix)->toBe('2606:4700::1/128')
        ->and($range->startAddress())->toBe('2606:4700::1')
        ->and($range->endAddress())->toBe('2606:4700::1');
});

it('creates IPv6 explicit range via fromRange', function (): void {
    $range = IpRange::fromRange('2606:4700::1', '2606:4700::ff');

    expect($range->isIpv6)->toBeTrue()
        ->and($range->contains(inet_pton('2606:4700::50')))->toBeTrue()
        ->and($range->contains(inet_pton('2606:4700::fff')))->toBeFalse();
});

it('serializes to array', function (): void {
    $range = IpRange::fromPrefix(new Prefix('10.0.0.0/8', asn: 100));
    $arr = $range->toArray();

    expect($arr)->toHaveKeys(['prefix', 'start', 'end', 'is_ipv6', 'asn', 'label'])
        ->and($arr['start'])->toBe('10.0.0.0')
        ->and($arr['end'])->toBe('10.255.255.255');
});

it('handles /16 boundaries correctly', function (): void {
    $range = IpRange::fromPrefix(new Prefix('172.16.0.0/16'));

    expect($range->startAddress())->toBe('172.16.0.0')
        ->and($range->endAddress())->toBe('172.16.255.255')
        ->and($range->contains(inet_pton('172.16.128.1')))->toBeTrue()
        ->and($range->contains(inet_pton('172.17.0.0')))->toBeFalse();
});
