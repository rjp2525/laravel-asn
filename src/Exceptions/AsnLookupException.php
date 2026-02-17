<?php

declare(strict_types=1);

namespace Reno\ASN\Exceptions;

use RuntimeException;

final class AsnLookupException extends RuntimeException
{
    public static function ipNotFound(string $ip): self
    {
        return new self("No ASN information found for IP: {$ip}");
    }

    public static function asnNotFound(int $asn): self
    {
        return new self("No data found for ASN: {$asn}");
    }

    public static function requestFailed(string $url, int $status): self
    {
        return new self("API request to {$url} failed with status {$status}");
    }

    public static function invalidProvider(string $provider): self
    {
        return new self("Unsupported ASN provider: {$provider}. Check your asn.providers config.");
    }
}
