<?php

declare(strict_types=1);

namespace Reno\ASN\Console;

use Illuminate\Console\Command;
use Reno\ASN\AsnManager;
use Reno\ASN\Exceptions\AsnLookupException;

final class AsnCheckCommand extends Command
{
    protected $signature = 'asn:check
        {ip : The IP address to check}
        {asn : The ASN number to check against (e.g., 13335)}';

    protected $description = 'Check if an IP address belongs to a specific ASN';

    public function handle(AsnManager $asn): int
    {
        /** @var string $ip */
        $ip = $this->argument('ip');
        $asnNumber = (int) $this->argument('asn');

        try {
            $belongs = $asn->ipBelongsToAsn($ip, $asnNumber);

            if ($belongs) {
                $this->components->info("{$ip} belongs to AS{$asnNumber}");
                $result = $asn->getAsn($asnNumber);
                $prefix = $result->findPrefixForIp($ip);

                if ($prefix !== null) {
                    $this->components->info("  Matched prefix: {$prefix->prefix}");
                }

                return self::SUCCESS;
            }

            $this->components->warn("{$ip} does NOT belong to AS{$asnNumber}");

            return self::FAILURE;
        } catch (AsnLookupException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
