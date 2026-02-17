<?php

declare(strict_types=1);

namespace Reno\ASN;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Data\MatchResult;
use Reno\ASN\Data\Prefix;

final class AsnManager
{
    public function __construct(
        private readonly AsnProvider $provider,
        private readonly CacheRepository $cache,
        private readonly bool $cacheEnabled,
        private readonly int $cacheTtl,
        private readonly string $cachePrefix,
    ) {}

    /**
     * Look up which ASN owns an IP address.
     */
    public function lookupIp(string $ip): AsnInfo
    {
        /** @var AsnInfo */
        return $this->cached("ip:{$ip}", fn (): AsnInfo => $this->provider->lookupIp($ip));
    }

    /**
     * Get all prefixes announced by an ASN.
     *
     * @return Collection<int, Prefix>
     */
    public function getPrefixes(int $asn): Collection
    {
        /** @var Collection<int, Prefix> */
        return $this->cached(
            "prefixes:{$asn}",
            fn (): Collection => $this->provider->getPrefixes($asn),
        );
    }

    /**
     * Get full ASN info with all prefixes.
     */
    public function getAsn(int $asn): AsnResult
    {
        /** @var AsnResult */
        return $this->cached(
            "asn:{$asn}",
            fn (): AsnResult => $this->provider->getAsn($asn),
        );
    }

    /**
     * Check if an IP belongs to a specific ASN.
     */
    public function ipBelongsToAsn(string $ip, int $asn): bool
    {
        return $this->getPrefixes($asn)
            ->contains(fn (Prefix $prefix): bool => $prefix->contains($ip));
    }

    /**
     * Resolve an IP → ASN → check if target IP is in same ASN.
     */
    public function ipBelongsToSameAsn(string $sourceIp, string $targetIp): bool
    {
        $info = $this->lookupIp($sourceIp);

        return $this->ipBelongsToAsn($targetIp, $info->asn);
    }

    /**
     * Check an IP against multiple ASNs. Returns the matching ASN or null.
     *
     * @param  int[]  $asns
     */
    public function ipMatchesAnyAsn(string $ip, array $asns): ?int
    {
        foreach ($asns as $asn) {
            if ($this->ipBelongsToAsn($ip, $asn)) {
                return $asn;
            }
        }

        return null;
    }

    /**
     * Build a compiled IpMatcher for one or more ASNs.
     *
     * Use this when checking many IPs against the same ASN(s).
     * Loads all prefixes once, compiles for O(log n) binary search.
     *
     * @param  int|int[]  $asns
     */
    public function buildMatcher(int|array $asns): IpMatcher
    {
        $matcher = new IpMatcher;
        $asns = is_int($asns) ? [$asns] : $asns;

        foreach ($asns as $asn) {
            $prefixes = $this->getPrefixes($asn);
            $matcher->addPrefixes($prefixes);
        }

        return $matcher->compile();
    }

    /**
     * Check multiple IPs against multiple ASNs efficiently.
     *
     * @param  string[]  $ips
     * @param  int[]  $asns
     * @return Collection<int, MatchResult>
     */
    public function batchCheck(array $ips, array $asns): Collection
    {
        $matcher = $this->buildMatcher($asns);

        return $matcher->matchBatch($ips);
    }

    /**
     * Flush all ASN cache entries for a given ASN.
     */
    public function flushAsn(int $asn): void
    {
        $this->cache->forget($this->cachePrefix."prefixes:{$asn}");
        $this->cache->forget($this->cachePrefix."asn:{$asn}");
    }

    /**
     * Flush the entire ASN cache (by prefix).
     */
    public function flushIp(string $ip): void
    {
        $this->cache->forget($this->cachePrefix."ip:{$ip}");
    }

    /**
     * Get the underlying provider instance.
     */
    public function provider(): AsnProvider
    {
        return $this->provider;
    }

    private function cached(string $key, \Closure $callback): mixed
    {
        if (! $this->cacheEnabled) {
            return $callback();
        }

        return $this->cache->remember(
            $this->cachePrefix.$key,
            $this->cacheTtl,
            $callback,
        );
    }
}
