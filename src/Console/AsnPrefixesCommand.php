<?php

declare(strict_types=1);

namespace Reno\ASN\Console;

use Illuminate\Console\Command;
use Reno\ASN\AsnManager;
use Reno\ASN\Data\Prefix;
use Reno\ASN\Exceptions\AsnLookupException;

use function Laravel\Prompts\table;

final class AsnPrefixesCommand extends Command
{
    protected $signature = 'asn:prefixes
        {asn : The ASN number (e.g., 13335)}
        {--ipv4-only : Show only IPv4 prefixes}
        {--ipv6-only : Show only IPv6 prefixes}
        {--count : Only show the count}';

    protected $description = 'List all prefixes announced by an ASN';

    public function handle(AsnManager $manager): int
    {
        $asn = (int) $this->argument('asn');

        try {
            $prefixes = $manager->getPrefixes($asn);

            if ($this->option('ipv4-only') === true) {
                $prefixes = $prefixes->reject(fn (Prefix $p): bool => $p->isIpv6);
            }

            if ($this->option('ipv6-only') === true) {
                $prefixes = $prefixes->filter(fn (Prefix $p): bool => $p->isIpv6);
            }

            if ($this->option('count') === true) {
                $this->components->info("AS{$asn} announces {$prefixes->count()} prefixes.");

                return self::SUCCESS;
            }

            $this->components->info("AS{$asn} — {$prefixes->count()} prefixes:");

            table(
                headers: ['Prefix', 'CIDR', 'Name', 'Country'],
                rows: $prefixes->map(fn (Prefix $p): array => [
                    $p->prefix,
                    "/{$p->cidr}",
                    $p->name ?? '-',
                    $p->country ?? '-',
                ])->all(),
            );

            return self::SUCCESS;
        } catch (AsnLookupException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
