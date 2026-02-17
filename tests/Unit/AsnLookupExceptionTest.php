<?php

declare(strict_types=1);

use Reno\ASN\Exceptions\AsnLookupException;

it('creates ip not found exception', function (): void {
    $e = AsnLookupException::ipNotFound('1.2.3.4');
    expect($e->getMessage())->toBe('No ASN information found for IP: 1.2.3.4');
});

it('creates asn not found exception', function (): void {
    $e = AsnLookupException::asnNotFound(99999);
    expect($e->getMessage())->toBe('No data found for ASN: 99999');
});

it('creates request failed exception', function (): void {
    $e = AsnLookupException::requestFailed('https://api.example.com/test', 500);
    expect($e->getMessage())->toBe('API request to https://api.example.com/test failed with status 500');
});

it('creates invalid provider exception', function (): void {
    $e = AsnLookupException::invalidProvider('foo');
    expect($e->getMessage())->toBe('Unsupported ASN provider: foo. Check your asn.providers config.');
});
