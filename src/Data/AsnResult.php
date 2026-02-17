<?php

declare(strict_types=1);

namespace Reno\ASN\Data;

use Illuminate\Support\Collection;

final readonly class AsnResult
{
    /**
     * @param  Collection<int, Prefix>  $prefixes
     */
    public function __construct(
        public AsnInfo $info,
        public Collection $prefixes,
    ) {}

    /**
     * Check if an IP belongs to any prefix in this ASN.
     */
    public function containsIp(string $ip): bool
    {
        return $this->prefixes->contains(fn (Prefix $prefix) => $prefix->contains($ip));
    }

    /**
     * Find the specific prefix an IP belongs to (or null).
     */
    public function findPrefixForIp(string $ip): ?Prefix
    {
        return $this->prefixes->first(fn (Prefix $prefix) => $prefix->contains($ip));
    }

    /**
     * Filter to only IPv4 prefixes.
     *
     * @return Collection<int, Prefix>
     */
    public function ipv4Prefixes(): Collection
    {
        return $this->prefixes->reject(fn (Prefix $p): bool => $p->isIpv6)->values();
    }

    /**
     * Filter to only IPv6 prefixes.
     *
     * @return Collection<int, Prefix>
     */
    public function ipv6Prefixes(): Collection
    {
        return $this->prefixes->filter(fn (Prefix $p): bool => $p->isIpv6)->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'info' => $this->info->toArray(),
            'prefixes' => $this->prefixes->map(fn (Prefix $p) => $p->toArray())->all(),
        ];
    }
}
