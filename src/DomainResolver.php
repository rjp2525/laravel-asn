<?php

declare(strict_types=1);

namespace Reno\ASN;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Exceptions\DomainResolutionException;

final class DomainResolver
{
    public function __construct(
        private readonly AsnManager $asnManager,
        private readonly CacheRepository $cache,
        private readonly bool $cacheEnabled,
        private readonly int $cacheTtl,
        private readonly string $cachePrefix,
        private readonly int $recordType = DNS_A,
    ) {}

    /**
     * Resolve a domain to its IPv4 address(es).
     *
     * @return string[]
     */
    public function resolveIps(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);

        /** @var string[] */
        return $this->cached("dns:{$domain}", function () use ($domain): array {
            $records = @dns_get_record($domain, $this->recordType);

            if ($records === false || $records === []) {
                throw DomainResolutionException::unresolvable($domain);
            }

            return array_values(array_unique(
                array_filter(array_column($records, 'ip'), fn (mixed $v): bool => is_string($v) && $v !== '')
            ));
        });
    }

    /**
     * Resolve a domain and look up the ASN for its first IP.
     */
    public function lookupAsn(string $domain): AsnInfo
    {
        $ips = $this->resolveIps($domain);

        if ($ips === []) {
            throw DomainResolutionException::unresolvable($domain);
        }

        return $this->asnManager->lookupIp($ips[0]);
    }

    /**
     * Check if a domain resolves to an IP within a specific ASN.
     */
    public function domainBelongsToAsn(string $domain, int $asn): bool
    {
        try {
            $ips = $this->resolveIps($domain);
        } catch (DomainResolutionException) {
            return false;
        }

        foreach ($ips as $ip) {
            if ($this->asnManager->ipBelongsToAsn($ip, $asn)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a domain resolves to an IP within any of the given ASNs.
     *
     * @param  int[]  $asns
     */
    public function domainMatchesAnyAsn(string $domain, array $asns): ?int
    {
        try {
            $ips = $this->resolveIps($domain);
        } catch (DomainResolutionException) {
            return null;
        }

        foreach ($ips as $ip) {
            $match = $this->asnManager->ipMatchesAnyAsn($ip, $asns);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Check a domain's IP(s) against a compiled IpMatcher.
     */
    public function domainMatchesRanges(string $domain, IpMatcher $matcher): bool
    {
        try {
            $ips = $this->resolveIps($domain);
        } catch (DomainResolutionException) {
            return false;
        }

        foreach ($ips as $ip) {
            if ($matcher->contains($ip)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = (string) preg_replace('#^https?://#', '', $domain);

        return rtrim(explode('/', $domain)[0], '.');
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
