<?php

declare(strict_types=1);

namespace Reno\ASN\Data;

final readonly class AsnInfo
{
    public function __construct(
        public int $asn,
        public string $name,
        public string $description,
        public ?string $country = null,
        public ?string $rir = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'asn' => $this->asn,
            'name' => $this->name,
            'description' => $this->description,
            'country' => $this->country,
            'rir' => $this->rir,
        ];
    }
}
