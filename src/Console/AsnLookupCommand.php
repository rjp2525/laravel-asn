<?php

declare(strict_types=1);

namespace Reno\ASN\Console;

use Illuminate\Console\Command;
use Reno\ASN\AsnManager;
use Reno\ASN\Data\Prefix;
use Reno\ASN\Exceptions\AsnLookupException;

use function Laravel\Prompts\table;

final class AsnLookupCommand extends Command
{
    protected $signature = 'asn:lookup {ip : The IP address to look up}';

    protected $description = 'Look up ASN information and prefixes for an IP address';

    public function handle(AsnManager $asn): int
    {
        /** @var string $ip */
        $ip = $this->argument('ip');

        try {
            $info = $asn->lookupIp($ip);

            $this->components->info("IP: {$ip}");
            $this->components->info("ASN: AS{$info->asn}");
            $this->components->info("Name: {$info->name}");
            $this->components->info("Description: {$info->description}");
            $this->components->info("Country: {$info->country}");

            $prefixes = $asn->getPrefixes($info->asn);

            $this->newLine();
            $this->components->info("Announced Prefixes ({$prefixes->count()}):");

            table(
                headers: ['Prefix', 'Name', 'Country'],
                rows: $prefixes->map(fn (Prefix $p): array => [$p->prefix, $p->name ?? '-', $p->country ?? '-'])->all(),
            );

            return self::SUCCESS;
        } catch (AsnLookupException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
