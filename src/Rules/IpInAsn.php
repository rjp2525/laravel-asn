<?php

declare(strict_types=1);

namespace Reno\ASN\Rules;

use Attribute;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Reno\ASN\AsnManager;
use Reno\ASN\Exceptions\AsnLookupException;

/**
 * Validates that an IP address belongs to one of the specified ASNs.
 *
 * Usage in FormRequest:
 *   'ip' => ['required', 'ip', new IpInAsn(13335, 15169)]
 *
 * Usage as attribute on DTO:
 *   #[IpInAsn(13335)]
 *   public string $ip;
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class IpInAsn implements ValidationRule
{
    /** @var int[] */
    private array $asns;

    public function __construct(int ...$asns)
    {
        $this->asns = $asns;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_IP) === false) {
            $fail('The :attribute must be a valid IP address.');

            return;
        }

        try {
            $manager = resolve(AsnManager::class);
            $matched = $manager->ipMatchesAnyAsn($value, $this->asns);

            if ($matched === null) {
                $asnList = implode(', ', array_map(fn (int $a): string => "AS{$a}", $this->asns));
                $fail("The :attribute must belong to one of the following ASNs: {$asnList}.");
            }
        } catch (AsnLookupException $e) {
            $fail("Unable to verify ASN for :attribute: {$e->getMessage()}");
        }
    }
}
