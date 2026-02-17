<?php

declare(strict_types=1);

namespace Reno\ASN\Data;

/**
 * Result of an IP match against compiled ranges.
 */
final readonly class MatchResult
{
    public function __construct(
        public string $ip,
        public bool $matched,
        public ?IpRange $range = null,
    ) {}

    public function asn(): ?int
    {
        return $this->range?->asn;
    }

    public function prefix(): ?string
    {
        return $this->range?->prefix;
    }

    public function label(): ?string
    {
        return $this->range?->label;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'matched' => $this->matched,
            'prefix' => $this->prefix(),
            'asn' => $this->asn(),
            'label' => $this->label(),
        ];
    }
}
