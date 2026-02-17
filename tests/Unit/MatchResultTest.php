<?php

declare(strict_types=1);

use Reno\ASN\Data\IpRange;
use Reno\ASN\Data\MatchResult;
use Reno\ASN\Data\Prefix;

it('exposes match details', function (): void {
    $range = IpRange::fromPrefix(new Prefix('10.0.0.0/8', 'Private', asn: 64496));
    $result = new MatchResult(ip: '10.1.2.3', matched: true, range: $range);

    expect($result->matched)->toBeTrue()
        ->and($result->ip)->toBe('10.1.2.3')
        ->and($result->asn())->toBe(64496)
        ->and($result->prefix())->toBe('10.0.0.0/8')
        ->and($result->label())->toBe('Private');
});

it('returns nulls for unmatched result', function (): void {
    $result = new MatchResult(ip: '8.8.8.8', matched: false);

    expect($result->matched)->toBeFalse()
        ->and($result->asn())->toBeNull()
        ->and($result->prefix())->toBeNull()
        ->and($result->label())->toBeNull();
});

it('serializes to array', function (): void {
    $range = IpRange::fromPrefix(new Prefix('10.0.0.0/8', 'Test', asn: 100));
    $result = new MatchResult(ip: '10.1.2.3', matched: true, range: $range);

    $arr = $result->toArray();

    expect($arr)->toBe([
        'ip' => '10.1.2.3',
        'matched' => true,
        'prefix' => '10.0.0.0/8',
        'asn' => 100,
        'label' => 'Test',
    ]);
});
