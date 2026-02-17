<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Reno\ASN\AsnManager;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Eloquent\AsnQueryMacros;

it('mysqlCidrWhere generates INET_ATON for IPv4', function (): void {
    $query = TestDevice::query();

    $result = AsnQueryMacros::mysqlCidrWhere($query, 'ip_address', '10.0.0.0/24', 'and');

    expect($result->toSql())->toContain('INET_ATON(ip_address) BETWEEN ? AND ?');
});

it('mysqlCidrWhere generates BETWEEN for IPv6', function (): void {
    $query = TestDevice::query();

    $result = AsnQueryMacros::mysqlCidrWhere($query, 'ip_address', '2606:4700::/32', 'and');

    expect($result->toSql())->toContain('between');
});

it('mysqlCidrWhereBase generates INET_ATON for IPv4', function (): void {
    $query = DB::table('test_devices');

    $result = AsnQueryMacros::mysqlCidrWhereBase($query, 'ip_address', '10.0.0.0/24', 'and');

    expect($result->toSql())->toContain('INET_ATON(ip_address) BETWEEN ? AND ?');
});

it('mysqlCidrWhereBase generates BETWEEN for IPv6', function (): void {
    $query = DB::table('test_devices');

    $result = AsnQueryMacros::mysqlCidrWhereBase($query, 'ip_address', '2606:4700::/32', 'and');

    expect($result->toSql())->toContain('between');
});

it('fallbackCidrWhere generates SQLite expression for IPv4', function (): void {
    $query = TestDevice::query();

    $result = AsnQueryMacros::fallbackCidrWhere($query, 'ip_address', '10.0.0.0/24', 'and');

    expect($result->toSql())->toContain('CAST(SUBSTR(ip_address');
});

it('fallbackCidrWhere generates BETWEEN for IPv6', function (): void {
    $query = TestDevice::query();

    $result = AsnQueryMacros::fallbackCidrWhere($query, 'ip_address', '2606:4700::/32', 'and');

    $sql = $result->toSql();
    expect($sql)->toContain('between')
        ->and($sql)->not->toContain('CAST(SUBSTR');
});

it('fallbackCidrWhereBase generates SQLite expression for IPv4', function (): void {
    $query = DB::table('test_devices');

    $result = AsnQueryMacros::fallbackCidrWhereBase($query, 'ip_address', '10.0.0.0/24', 'and');

    expect($result->toSql())->toContain('CAST(SUBSTR(ip_address');
});

it('fallbackCidrWhereBase generates BETWEEN for IPv6', function (): void {
    $query = DB::table('test_devices');

    $result = AsnQueryMacros::fallbackCidrWhereBase($query, 'ip_address', '2606:4700::/32', 'and');

    $sql = $result->toSql();
    expect($sql)->toContain('between')
        ->and($sql)->not->toContain('CAST(SUBSTR');
});

it('sqliteIpToInt generates correct expression', function (): void {
    $expr = AsnQueryMacros::sqliteIpToInt('ip');

    expect($expr)->toContain('CAST(SUBSTR(ip')
        ->and($expr)->toContain('16777216')
        ->and($expr)->toContain('65536')
        ->and($expr)->toContain('256');
});

it('whereIpNotInAsn returns unmodified query when no prefixes', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')->with(99999)->andReturn(collect());

    $manager = new AsnManager(
        provider: $provider,
        cache: new CacheRepository(new ArrayStore),
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    app()->instance(AsnManager::class, $manager);

    $query = TestDevice::whereIpNotInAsn('ip_address', 99999);

    // No WHERE clause added when there are no prefixes to exclude
    expect($query->toSql())->not->toContain('BETWEEN');
});

it('whereIpEquals handles IPv6 normalization', function (): void {
    $query = TestDevice::whereIpEquals('ip_address', '2606:4700::1');

    // SQLite fallback uses normalized comparison
    expect($query->toSql())->toContain('=');
});
