<?php

declare(strict_types=1);

namespace Reno\ASN\Rules;

use Attribute;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Reno\ASN\DomainResolver;
use Reno\ASN\Exceptions\DomainResolutionException;

/**
 * Validates that a domain resolves to an IP within one of the specified ASNs.
 *
 * Usage:
 *   'domain' => ['required', 'string', new DomainInAsn(13335)] // Must be Cloudflare
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class DomainInAsn implements ValidationRule
{
    /** @var int[] */
    private array $asns;

    public function __construct(int ...$asns)
    {
        $this->asns = $asns;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid domain name.');

            return;
        }

        try {
            $resolver = resolve(DomainResolver::class);
            $matched = $resolver->domainMatchesAnyAsn($value, $this->asns);

            if ($matched === null) {
                $asnList = implode(', ', array_map(fn (int $a): string => "AS{$a}", $this->asns));
                $fail("The :attribute does not resolve to any of the following ASNs: {$asnList}.");
            }
        } catch (DomainResolutionException) {
            $fail('The :attribute could not be resolved to an IP address.');
        }
    }
}
