<?php

declare(strict_types=1);

namespace Reno\ASN\Rules;

use Attribute;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Reno\ASN\AsnManager;
use Reno\ASN\Exceptions\AsnLookupException;

/**
 * Validates that an IP address does NOT belong to any of the blocked ASNs.
 *
 * Usage:
 *   'ip' => ['required', 'ip', new IpNotInAsn(7922, 7018)] // Block Comcast, AT&T
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class IpNotInAsn implements ValidationRule
{
    /** @var int[] */
    private array $blockedAsns;

    public function __construct(int ...$blockedAsns)
    {
        $this->blockedAsns = $blockedAsns;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_IP) === false) {
            $fail('The :attribute must be a valid IP address.');

            return;
        }

        try {
            $manager = resolve(AsnManager::class);
            $matched = $manager->ipMatchesAnyAsn($value, $this->blockedAsns);

            if ($matched !== null) {
                $fail("The :attribute belongs to blocked ASN: AS{$matched}.");
            }
        } catch (AsnLookupException) {
            // If we can't verify, we allow it through — fail open
        }
    }
}
