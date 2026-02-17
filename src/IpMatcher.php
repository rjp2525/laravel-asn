<?php

declare(strict_types=1);

namespace Reno\ASN;

use Illuminate\Support\Collection;
use Reno\ASN\Data\IpRange;
use Reno\ASN\Data\MatchResult;
use Reno\ASN\Data\Prefix;

/**
 * High-performance IP matcher using sorted binary search.
 *
 * Compiles CIDR prefixes, explicit ranges, and single IPs into sorted
 * arrays for O(log n) lookup per IP. Handles both IPv4 and IPv6.
 *
 * Designed for checking millions of IPs against thousands of ranges
 * (e.g., Comcast AS7922 with 60+ pages of prefixes).
 */
final class IpMatcher
{
    /** @var IpRange[] Sorted IPv4 ranges */
    private array $v4Ranges = [];

    /** @var IpRange[] Sorted IPv6 ranges */
    private array $v6Ranges = [];

    private bool $compiled = false;

    /**
     * Add a single CIDR prefix.
     */
    public function addPrefix(Prefix|string $prefix): self
    {
        if (is_string($prefix)) {
            $prefix = new Prefix($prefix);
        }

        return $this->addRange($prefix->toRange());
    }

    /**
     * Add a collection of prefixes.
     *
     * @param  iterable<Prefix|string>  $prefixes
     */
    public function addPrefixes(iterable $prefixes): self
    {
        foreach ($prefixes as $prefix) {
            $this->addPrefix($prefix);
        }

        return $this;
    }

    /**
     * Add a compiled IpRange directly.
     */
    public function addRange(IpRange $range): self
    {
        $this->compiled = false;

        if ($range->isIpv6) {
            $this->v6Ranges[] = $range;
        } else {
            $this->v4Ranges[] = $range;
        }

        return $this;
    }

    /**
     * Add a single IP to block/allow (treated as /32 or /128).
     */
    public function addIp(string $ip, ?int $asn = null, ?string $label = null): self
    {
        return $this->addRange(IpRange::fromIp($ip, $asn, $label));
    }

    /**
     * Add an explicit start-end range.
     */
    public function addExplicitRange(string $startIp, string $endIp, ?int $asn = null, ?string $label = null): self
    {
        return $this->addRange(IpRange::fromRange($startIp, $endIp, $asn, $label));
    }

    /**
     * Compile (sort) ranges for binary search. Called automatically on first lookup.
     */
    public function compile(): self
    {
        usort($this->v4Ranges, static fn (IpRange $a, IpRange $b) => $a->startBin <=> $b->startBin);
        usort($this->v6Ranges, static fn (IpRange $a, IpRange $b) => $a->startBin <=> $b->startBin);

        $this->compiled = true;

        return $this;
    }

    /**
     * Check if an IP is contained in any loaded range.
     */
    public function contains(string $ip): bool
    {
        return $this->find($ip) !== null;
    }

    /**
     * Find the matching range for an IP, or null.
     */
    public function find(string $ip): ?IpRange
    {
        $ipBin = @inet_pton($ip);
        if ($ipBin === false) {
            return null;
        }

        if (! $this->compiled) {
            $this->compile();
        }

        $ranges = strlen($ipBin) === 4 ? $this->v4Ranges : $this->v6Ranges;

        return $this->binarySearch($ranges, $ipBin);
    }

    /**
     * Get detailed match result for an IP.
     */
    public function match(string $ip): MatchResult
    {
        $range = $this->find($ip);

        return new MatchResult(
            ip: $ip,
            matched: $range !== null,
            range: $range,
        );
    }

    /**
     * Batch check multiple IPs. Returns a Collection of MatchResult.
     *
     * @param  string[]  $ips
     * @return Collection<int, MatchResult>
     */
    public function matchBatch(array $ips): Collection
    {
        if (! $this->compiled) {
            $this->compile();
        }

        return collect($ips)->map(fn (string $ip) => $this->match($ip));
    }

    /**
     * Batch check — returns only matched IPs with their ranges.
     *
     * @param  string[]  $ips
     * @return Collection<int, MatchResult>
     */
    public function matchedOnly(array $ips): Collection
    {
        return $this->matchBatch($ips)->filter(fn (MatchResult $r) => $r->matched)->values();
    }

    /**
     * Batch check — returns only IPs that did NOT match.
     *
     * @param  string[]  $ips
     * @return Collection<int, string>
     */
    public function unmatchedOnly(array $ips): Collection
    {
        return $this->matchBatch($ips)
            ->reject(fn (MatchResult $r) => $r->matched)
            ->map(fn (MatchResult $r) => $r->ip)
            ->values();
    }

    /**
     * Get total number of loaded ranges.
     */
    public function count(): int
    {
        return count($this->v4Ranges) + count($this->v6Ranges);
    }

    /**
     * Get all loaded IPv4 ranges.
     *
     * @return IpRange[]
     */
    public function v4Ranges(): array
    {
        return $this->v4Ranges;
    }

    /**
     * Get all loaded IPv6 ranges.
     *
     * @return IpRange[]
     */
    public function v6Ranges(): array
    {
        return $this->v6Ranges;
    }

    /**
     * Clear all loaded ranges.
     */
    public function flush(): self
    {
        $this->v4Ranges = [];
        $this->v6Ranges = [];
        $this->compiled = false;

        return $this;
    }

    /**
     * Binary search for the range containing the given packed IP.
     *
     * @param  IpRange[]  $ranges  Sorted array of ranges
     * @param  string  $ipBin  Packed binary IP (inet_pton output)
     */
    private function binarySearch(array $ranges, string $ipBin): ?IpRange
    {
        $lo = 0;
        $hi = count($ranges) - 1;

        while ($lo <= $hi) {
            $mid = $lo + (($hi - $lo) >> 1);
            $range = $ranges[$mid];

            if ($ipBin < $range->startBin) {
                $hi = $mid - 1;
            } elseif ($ipBin > $range->endBin) {
                $lo = $mid + 1;
            } else {
                return $range;
            }
        }

        // Overlapping ranges: check neighbors (ranges may not be perfectly disjoint)
        // Walk backwards from insertion point to find containing range
        for ($i = max(0, $hi); $i >= max(0, $hi - 2); $i--) {
            if (isset($ranges[$i]) && $ranges[$i]->contains($ipBin)) {
                return $ranges[$i];
            }
        }

        return null;
    }
}
