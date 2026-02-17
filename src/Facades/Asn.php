<?php

declare(strict_types=1);

namespace Reno\ASN\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Reno\ASN\AsnManager;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Data\MatchResult;
use Reno\ASN\Data\Prefix;
use Reno\ASN\IpMatcher;

/**
 * @method static AsnInfo lookupIp(string $ip)
 * @method static Collection<int, Prefix> getPrefixes(int $asn)
 * @method static AsnResult getAsn(int $asn)
 * @method static bool ipBelongsToAsn(string $ip, int $asn)
 * @method static bool ipBelongsToSameAsn(string $sourceIp, string $targetIp)
 * @method static int|null ipMatchesAnyAsn(string $ip, int[] $asns)
 * @method static IpMatcher buildMatcher(int|int[] $asns)
 * @method static Collection<int, MatchResult> batchCheck(string[] $ips, int[] $asns)
 * @method static void flushAsn(int $asn)
 * @method static void flushIp(string $ip)
 *
 * @see AsnManager
 */
final class Asn extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AsnManager::class;
    }
}
