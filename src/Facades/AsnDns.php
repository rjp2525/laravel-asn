<?php

declare(strict_types=1);

namespace Reno\ASN\Facades;

use Illuminate\Support\Facades\Facade;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\DomainResolver;
use Reno\ASN\IpMatcher;

/**
 * @method static string[] resolveIps(string $domain)
 * @method static AsnInfo lookupAsn(string $domain)
 * @method static bool domainBelongsToAsn(string $domain, int $asn)
 * @method static int|null domainMatchesAnyAsn(string $domain, int[] $asns)
 * @method static bool domainMatchesRanges(string $domain, IpMatcher $matcher)
 *
 * @see DomainResolver
 */
final class AsnDns extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DomainResolver::class;
    }
}
