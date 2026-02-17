<?php

declare(strict_types=1);

namespace Reno\ASN\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Reno\ASN\AsnServiceProvider;
use Reno\ASN\Facades\Asn;
use Reno\ASN\Facades\AsnDns;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AsnServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Asn' => Asn::class,
            'AsnDns' => AsnDns::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('asn.cache.enabled', false);
    }
}
