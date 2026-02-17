<?php

declare(strict_types=1);

namespace Reno\ASN\Console;

use Illuminate\Console\Command;
use Reno\ASN\DomainResolver;
use Reno\ASN\Exceptions\AsnLookupException;
use Reno\ASN\Exceptions\DomainResolutionException;

final class AsnDomainCommand extends Command
{
    protected $signature = 'asn:domain
        {domain : The domain name to look up}
        {--check-asn= : Optional ASN to check against}';

    protected $description = 'Resolve a domain and look up its ASN information';

    public function handle(DomainResolver $resolver): int
    {
        /** @var string $domain */
        $domain = $this->argument('domain');

        try {
            $ips = $resolver->resolveIps($domain);
            $this->components->info("Domain: {$domain}");
            $this->components->info('Resolved IPs: '.implode(', ', $ips));

            $info = $resolver->lookupAsn($domain);
            $this->newLine();
            $this->components->info("ASN: AS{$info->asn}");
            $this->components->info("Name: {$info->name}");
            $this->components->info("Description: {$info->description}");
            $this->components->info("Country: {$info->country}");

            /** @var string|null $checkAsn */
            $checkAsn = $this->option('check-asn');

            if ($checkAsn !== null) {
                $this->newLine();
                $belongs = $resolver->domainBelongsToAsn($domain, (int) $checkAsn);
                $word = $belongs ? 'belongs to' : 'does NOT belong to';
                $this->components->info("{$domain} {$word} AS{$checkAsn}");

                return $belongs ? self::SUCCESS : self::FAILURE;
            }

            return self::SUCCESS;
        } catch (DomainResolutionException|AsnLookupException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
