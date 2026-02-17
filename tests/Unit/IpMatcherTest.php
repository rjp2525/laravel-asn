<?php

declare(strict_types=1);

use Reno\ASN\Data\Prefix;
use Reno\ASN\IpMatcher;

it('matches an IPv4 address in a single prefix', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('104.16.0.0/12')
        ->compile();

    expect($matcher->contains('104.16.0.1'))->toBeTrue()
        ->and($matcher->contains('104.31.255.255'))->toBeTrue()
        ->and($matcher->contains('104.32.0.0'))->toBeFalse();
});

it('matches across multiple IPv4 prefixes', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->addPrefix('172.16.0.0/12')
        ->addPrefix('192.168.0.0/16')
        ->compile();

    expect($matcher->contains('10.1.2.3'))->toBeTrue()
        ->and($matcher->contains('172.20.0.1'))->toBeTrue()
        ->and($matcher->contains('192.168.1.1'))->toBeTrue()
        ->and($matcher->contains('8.8.8.8'))->toBeFalse();
});

it('matches IPv6 addresses', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('2606:4700::/32')
        ->compile();

    expect($matcher->contains('2606:4700::1'))->toBeTrue()
        ->and($matcher->contains('2606:4700:ffff::1'))->toBeTrue()
        ->and($matcher->contains('2607:4700::1'))->toBeFalse();
});

it('handles mixed IPv4 and IPv6', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('104.16.0.0/12')
        ->addPrefix('2606:4700::/32')
        ->compile();

    expect($matcher->contains('104.16.0.1'))->toBeTrue()
        ->and($matcher->contains('2606:4700::1'))->toBeTrue()
        ->and($matcher->contains('8.8.8.8'))->toBeFalse();
});

it('accepts string prefixes', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->compile();

    expect($matcher->contains('10.0.0.1'))->toBeTrue();
});

it('accepts Prefix objects', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix(new Prefix('10.0.0.0/8'))
        ->compile();

    expect($matcher->contains('10.0.0.1'))->toBeTrue();
});

it('adds single IP addresses', function (): void {
    $matcher = (new IpMatcher)
        ->addIp('1.2.3.4')
        ->compile();

    expect($matcher->contains('1.2.3.4'))->toBeTrue()
        ->and($matcher->contains('1.2.3.5'))->toBeFalse();
});

it('adds explicit ranges', function (): void {
    $matcher = (new IpMatcher)
        ->addExplicitRange('10.0.0.1', '10.0.0.100')
        ->compile();

    expect($matcher->contains('10.0.0.50'))->toBeTrue()
        ->and($matcher->contains('10.0.0.101'))->toBeFalse();
});

it('auto-compiles on first lookup if not compiled', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8');

    // No explicit compile() call
    expect($matcher->contains('10.1.2.3'))->toBeTrue();
});

it('returns detailed match results', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix(new Prefix('104.16.0.0/12', 'Cloudflare', asn: 13335))
        ->compile();

    $result = $matcher->match('104.16.0.1');

    expect($result->matched)->toBeTrue()
        ->and($result->ip)->toBe('104.16.0.1')
        ->and($result->asn())->toBe(13335)
        ->and($result->label())->toBe('Cloudflare');
});

it('returns unmatched result for unknown IPs', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->compile();

    $result = $matcher->match('8.8.8.8');

    expect($result->matched)->toBeFalse()
        ->and($result->range)->toBeNull();
});

it('batch matches multiple IPs', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->addPrefix('172.16.0.0/12')
        ->compile();

    $results = $matcher->matchBatch(['10.1.2.3', '8.8.8.8', '172.20.0.1', '1.1.1.1']);

    expect($results)->toHaveCount(4)
        ->and($results[0]->matched)->toBeTrue()
        ->and($results[1]->matched)->toBeFalse()
        ->and($results[2]->matched)->toBeTrue()
        ->and($results[3]->matched)->toBeFalse();
});

it('filters to matched only', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->compile();

    $matched = $matcher->matchedOnly(['10.1.2.3', '8.8.8.8', '10.5.6.7']);

    expect($matched)->toHaveCount(2)
        ->and($matched[0]->ip)->toBe('10.1.2.3')
        ->and($matched[1]->ip)->toBe('10.5.6.7');
});

it('filters to unmatched only', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->compile();

    $unmatched = $matcher->unmatchedOnly(['10.1.2.3', '8.8.8.8', '1.1.1.1']);

    expect($unmatched)->toHaveCount(2)
        ->and($unmatched[0])->toBe('8.8.8.8')
        ->and($unmatched[1])->toBe('1.1.1.1');
});

it('handles invalid IPs gracefully', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->compile();

    expect($matcher->contains('not-an-ip'))->toBeFalse()
        ->and($matcher->find('garbage'))->toBeNull();
});

it('reports count correctly', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->addPrefix('172.16.0.0/12')
        ->addPrefix('2606:4700::/32');

    expect($matcher->count())->toBe(3);
});

it('flushes all ranges', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->compile();

    expect($matcher->contains('10.0.0.1'))->toBeTrue();

    $matcher->flush();

    expect($matcher->count())->toBe(0)
        ->and($matcher->contains('10.0.0.1'))->toBeFalse();
});

it('handles a large number of prefixes efficiently', function (): void {
    $matcher = new IpMatcher;

    // Simulate a large ASN with 500+ prefixes
    for ($i = 0; $i < 256; $i++) {
        $matcher->addPrefix("{$i}.0.0.0/16");
    }
    for ($i = 0; $i < 256; $i++) {
        $matcher->addPrefix("10.{$i}.0.0/24");
    }

    $matcher->compile();

    expect($matcher->count())->toBe(512)
        ->and($matcher->contains('10.50.0.1'))->toBeTrue()
        ->and($matcher->contains('200.0.0.1'))->toBeTrue();
});

it('handles overlapping ranges', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')     // Broad
        ->addPrefix('10.1.0.0/16')    // Narrower, overlapping
        ->compile();

    expect($matcher->contains('10.1.0.1'))->toBeTrue()
        ->and($matcher->contains('10.2.0.1'))->toBeTrue();
});

it('finds IP via neighbor check for overlapping ranges', function (): void {
    // Create scenario where binary search misses but neighbor check finds it:
    // Wide range followed by narrow ranges that shift the binary search target
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')      // 10.0.0.0 - 10.255.255.255
        ->addPrefix('10.128.0.0/16')   // 10.128.0.0 - 10.128.255.255 (subset)
        ->addPrefix('10.129.0.0/16')   // 10.129.0.0 - 10.129.255.255 (subset)
        ->compile();

    // IP in the broad /8 but after the /16s — binary search may land on /16 and miss
    expect($matcher->contains('10.200.0.1'))->toBeTrue();
});

it('bulk adds prefixes from collection', function (): void {
    $prefixes = collect([
        new Prefix('10.0.0.0/8'),
        new Prefix('172.16.0.0/12'),
    ]);

    $matcher = (new IpMatcher)->addPrefixes($prefixes)->compile();

    expect($matcher->count())->toBe(2)
        ->and($matcher->contains('10.0.0.1'))->toBeTrue();
});

it('auto-compiles in matchBatch if not compiled', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8');

    // No explicit compile()
    $results = $matcher->matchBatch(['10.1.2.3', '8.8.8.8']);

    expect($results)->toHaveCount(2)
        ->and($results[0]->matched)->toBeTrue()
        ->and($results[1]->matched)->toBeFalse();
});

it('separates v4 and v6 ranges', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/8')
        ->addPrefix('2606:4700::/32')
        ->compile();

    expect($matcher->v4Ranges())->toHaveCount(1)
        ->and($matcher->v6Ranges())->toHaveCount(1);
});
