<?php

declare(strict_types=1);

use Reno\ASN\AsnManager;
use Reno\ASN\DomainResolver;
use Reno\ASN\Facades\Asn;
use Reno\ASN\Facades\AsnDns;

it('Asn facade resolves to AsnManager', function (): void {
    expect(Asn::getFacadeRoot())->toBeInstanceOf(AsnManager::class);
});

it('AsnDns facade resolves to DomainResolver', function (): void {
    expect(AsnDns::getFacadeRoot())->toBeInstanceOf(DomainResolver::class);
});
