<?php

declare(strict_types=1);

namespace Reno\ASN\Data;

use InvalidArgumentException;
use RuntimeException;

/**
 * Compiled IP range for O(log n) binary search lookups.
 *
 * Stores start/end as packed binary strings (4 bytes for IPv4, 16 bytes for IPv6)
 * for memory-efficient comparison without GMP.
 */
final readonly class IpRange
{
    /**
     * @param  string  $startBin  Packed binary start address (inet_pton output)
     * @param  string  $endBin  Packed binary end address
     * @param  string  $prefix  Original CIDR notation
     * @param  int|null  $asn  Owning ASN number
     * @param  string|null  $label  Human-readable label
     */
    public function __construct(
        public string $startBin,
        public string $endBin,
        public bool $isIpv6,
        public string $prefix,
        public ?int $asn = null,
        public ?string $label = null,
    ) {}

    public static function fromPrefix(Prefix $prefix): self
    {
        $networkBin = inet_pton($prefix->network);

        if ($networkBin === false) {
            throw new InvalidArgumentException("Invalid network address: {$prefix->network}");
        }

        $totalBits = strlen($networkBin) * 8;
        $hostBits = $totalBits - $prefix->cidr;

        // Calculate end address
        $endBin = $networkBin;
        for ($i = strlen($endBin) - 1; $i >= 0 && $hostBits > 0; $i--) {
            $bits = min($hostBits, 8);
            $endBin[$i] = chr(ord($endBin[$i]) | ((1 << $bits) - 1));
            $hostBits -= $bits;
        }

        // Apply mask to get clean start
        $mask = Prefix::buildMask($totalBits, $prefix->cidr);
        $startBin = $networkBin & $mask;

        return new self(
            startBin: $startBin,
            endBin: $endBin,
            isIpv6: $prefix->isIpv6,
            prefix: $prefix->prefix,
            asn: $prefix->asn,
            label: $prefix->name,
        );
    }

    /**
     * Create from a single IP (treated as /32 or /128).
     */
    public static function fromIp(string $ip, ?int $asn = null, ?string $label = null): self
    {
        $bin = inet_pton($ip);

        if ($bin === false) {
            throw new InvalidArgumentException("Invalid IP address: {$ip}");
        }

        $isV6 = strlen($bin) === 16;
        $cidr = $isV6 ? 128 : 32;

        return new self(
            startBin: $bin,
            endBin: $bin,
            isIpv6: $isV6,
            prefix: "{$ip}/{$cidr}",
            asn: $asn,
            label: $label,
        );
    }

    /**
     * Create from explicit start/end IPs.
     */
    public static function fromRange(string $startIp, string $endIp, ?int $asn = null, ?string $label = null): self
    {
        $startBin = inet_pton($startIp);
        $endBin = inet_pton($endIp);

        if ($startBin === false || $endBin === false) {
            throw new InvalidArgumentException("Invalid IP range: {$startIp} - {$endIp}");
        }

        return new self(
            startBin: $startBin,
            endBin: $endBin,
            isIpv6: strlen($startBin) === 16,
            prefix: "{$startIp}-{$endIp}",
            asn: $asn,
            label: $label,
        );
    }

    public function contains(string $ipBin): bool
    {
        return $ipBin >= $this->startBin && $ipBin <= $this->endBin;
    }

    public function startAddress(): string
    {
        $result = inet_ntop($this->startBin);

        if ($result === false) {
            throw new RuntimeException('Failed to convert binary IP to string.');
        }

        return $result;
    }

    public function endAddress(): string
    {
        $result = inet_ntop($this->endBin);

        if ($result === false) {
            throw new RuntimeException('Failed to convert binary IP to string.');
        }

        return $result;
    }

    /**
     * For IPv4 only — returns start as unsigned long for DB queries.
     */
    public function startLong(): int
    {
        if ($this->isIpv6) {
            throw new RuntimeException('Cannot convert IPv6 range to long. Use binary comparison.');
        }

        /** @var array<int, int> $unpacked */
        $unpacked = unpack('N', $this->startBin);

        return $unpacked[1];
    }

    /**
     * For IPv4 only — returns end as unsigned long for DB queries.
     */
    public function endLong(): int
    {
        if ($this->isIpv6) {
            throw new RuntimeException('Cannot convert IPv6 range to long. Use binary comparison.');
        }

        /** @var array<int, int> $unpacked */
        $unpacked = unpack('N', $this->endBin);

        return $unpacked[1];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'prefix' => $this->prefix,
            'start' => $this->startAddress(),
            'end' => $this->endAddress(),
            'is_ipv6' => $this->isIpv6,
            'asn' => $this->asn,
            'label' => $this->label,
        ];
    }
}
