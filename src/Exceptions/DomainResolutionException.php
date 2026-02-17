<?php

declare(strict_types=1);

namespace Reno\ASN\Exceptions;

use RuntimeException;

final class DomainResolutionException extends RuntimeException
{
    public static function unresolvable(string $domain): self
    {
        return new self("Unable to resolve domain to an IP address: {$domain}");
    }
}
