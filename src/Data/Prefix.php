<?php

declare(strict_types=1);

namespace Reno\ASN\Data;

use InvalidArgumentException;

final readonly class Prefix
{
    public string $network;

    public int $cidr;

    public bool $isIpv6;

    public function __construct(
        public string $prefix,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $country = null,
        public ?int $asn = null,
    ) {
        $parts = explode('/', $this->prefix);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Invalid CIDR notation: {$this->prefix}");
        }

        $this->network = $parts[0];
        $this->cidr = (int) $parts[1];
        $this->isIpv6 = str_contains($this->network, ':');
    }

    /**
     * Check if a given IP address falls within this prefix.
     */
    public function contains(string $ip): bool
    {
        $networkBin = @inet_pton($this->network);
        $ipBin = @inet_pton($ip);

        if ($networkBin === false || $ipBin === false) {
            return false;
        }

        if (strlen($networkBin) !== strlen($ipBin)) {
            return false;
        }

        $mask = self::buildMask(strlen($networkBin) * 8, $this->cidr);

        return ($ipBin & $mask) === ($networkBin & $mask);
    }

    /**
     * Convert to a compiled IpRange for batch lookups.
     */
    public function toRange(): IpRange
    {
        return IpRange::fromPrefix($this);
    }

    /**
     * Get the broadcast/end address of this prefix.
     */
    public function endAddress(): string
    {
        $networkBin = inet_pton($this->network);

        if ($networkBin === false) {
            throw new InvalidArgumentException("Invalid network address: {$this->network}");
        }

        $totalBits = strlen($networkBin) * 8;
        $hostBits = $totalBits - $this->cidr;

        $end = $networkBin;
        for ($i = strlen($end) - 1; $i >= 0 && $hostBits > 0; $i--) {
            $bits = min($hostBits, 8);
            $end[$i] = chr(ord($end[$i]) | ((1 << $bits) - 1));
            $hostBits -= $bits;
        }

        $result = inet_ntop($end);

        if ($result === false) {
            throw new InvalidArgumentException("Failed to compute end address for: {$this->prefix}");
        }

        return $result;
    }

    public static function buildMask(int $totalBits, int $prefixLength): string
    {
        $mask = str_repeat("\xff", intdiv($prefixLength, 8));

        $remainder = $prefixLength % 8;
        if ($remainder > 0) {
            $mask .= chr((0xFF << (8 - $remainder)) & 0xFF);
        }

        return str_pad($mask, intdiv($totalBits, 8), "\x00");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'prefix' => $this->prefix,
            'network' => $this->network,
            'cidr' => $this->cidr,
            'is_ipv6' => $this->isIpv6,
            'name' => $this->name,
            'description' => $this->description,
            'country' => $this->country,
            'asn' => $this->asn,
        ];
    }
}
