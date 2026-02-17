<?php

declare(strict_types=1);

namespace Reno\ASN\Contracts;

use Illuminate\Support\Collection;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Data\Prefix;

interface AsnProvider
{
    /**
     * Look up the ASN for a given IP address.
     */
    public function lookupIp(string $ip): AsnInfo;

    /**
     * Get all prefixes announced by an ASN.
     *
     * @return Collection<int, Prefix>
     */
    public function getPrefixes(int $asn): Collection;

    /**
     * Get full ASN details including all prefixes.
     */
    public function getAsn(int $asn): AsnResult;
}
