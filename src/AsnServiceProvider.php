<?php

declare(strict_types=1);

namespace Reno\ASN;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpClient;
use Reno\ASN\Console\AsnCheckCommand;
use Reno\ASN\Console\AsnDomainCommand;
use Reno\ASN\Console\AsnLookupCommand;
use Reno\ASN\Console\AsnPrefixesCommand;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Eloquent\AsnQueryMacros;
use Reno\ASN\Exceptions\AsnLookupException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AsnServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-asn')
            ->hasConfigFile('asn')
            ->hasCommands(
                AsnLookupCommand::class,
                AsnCheckCommand::class,
                AsnPrefixesCommand::class,
                AsnDomainCommand::class,
            );
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(AsnProvider::class, function ($app): AsnProvider {
            /** @var string $provider */
            $provider = config('asn.provider', 'ripestat');

            /** @var HttpClient $http */
            $http = $app->make(HttpClient::class);

            /** @var int $timeout */
            $timeout = config('asn.http.timeout', 15);

            /** @var int $retryTimes */
            $retryTimes = config('asn.http.retry_times', 3);

            /** @var int $retryDelay */
            $retryDelay = config('asn.http.retry_delay', 500);

            /** @var array<string, class-string<AsnProvider>> $providerMap */
            $providerMap = config('asn.providers', []);

            $class = $providerMap[$provider] ?? throw AsnLookupException::invalidProvider($provider);

            return match ($provider) {
                'ipinfo' => new $class($http, $timeout, $retryTimes, $retryDelay, is_string($token = config('asn.ipinfo.token')) ? $token : ''),
                default => new $class($http, $timeout, $retryTimes, $retryDelay),
            };
        });

        $this->app->singleton(AsnManager::class, function ($app): AsnManager {
            /** @var CacheRepository $cache */
            $cache = $this->resolveCache($app);

            /** @var bool $cacheEnabled */
            $cacheEnabled = config('asn.cache.enabled', true);

            /** @var int $cacheTtl */
            $cacheTtl = config('asn.cache.ttl', 86400);

            /** @var string $cachePrefix */
            $cachePrefix = config('asn.cache.prefix', 'reno:asn:');

            return new AsnManager(
                provider: $app->make(AsnProvider::class),
                cache: $cache,
                cacheEnabled: $cacheEnabled,
                cacheTtl: $cacheTtl,
                cachePrefix: $cachePrefix,
            );
        });

        $this->app->singleton(DomainResolver::class, function ($app): DomainResolver {
            /** @var CacheRepository $cache */
            $cache = $this->resolveCache($app);

            /** @var bool $cacheEnabled */
            $cacheEnabled = config('asn.cache.enabled', true);

            /** @var int $dnsCacheTtl */
            $dnsCacheTtl = config('asn.dns.cache_ttl', 3600);

            /** @var string $cachePrefix */
            $cachePrefix = config('asn.cache.prefix', 'reno:asn:');

            /** @var int $recordType */
            $recordType = config('asn.dns.record_type', DNS_A);

            return new DomainResolver(
                asnManager: $app->make(AsnManager::class),
                cache: $cache,
                cacheEnabled: $cacheEnabled,
                cacheTtl: $dnsCacheTtl,
                cachePrefix: $cachePrefix,
                recordType: $recordType,
            );
        });

        $this->app->alias(AsnManager::class, 'asn');
        $this->app->alias(DomainResolver::class, 'asn.dns');
    }

    public function packageBooted(): void
    {
        AsnQueryMacros::register();
    }

    private function resolveCache(Application $app): CacheRepository
    {
        /** @var string|null $store */
        $store = config('asn.cache.store');

        if ($store !== null && $store !== '') {
            /** @var CacheManager $cacheManager */
            $cacheManager = $app->make('cache');

            return $cacheManager->store($store);
        }

        return $app->make(CacheRepository::class);
    }
}
