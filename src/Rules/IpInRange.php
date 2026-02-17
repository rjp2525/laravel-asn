<?php

declare(strict_types=1);

namespace Reno\ASN\Rules;

use Attribute;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Reno\ASN\Data\Prefix;

/**
 * Validates that an IP falls within one of the given CIDR ranges.
 *
 * Usage:
 *   'ip' => ['required', 'ip', new IpInRange('10.0.0.0/8', '172.16.0.0/12')]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class IpInRange implements ValidationRule
{
    /** @var string[] */
    private array $ranges;

    public function __construct(string ...$ranges)
    {
        $this->ranges = $ranges;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_IP) === false) {
            $fail('The :attribute must be a valid IP address.');

            return;
        }

        foreach ($this->ranges as $range) {
            $prefix = new Prefix($range);
            if ($prefix->contains($value)) {
                return;
            }
        }

        $fail('The :attribute is not within any of the allowed IP ranges.');
    }
}
