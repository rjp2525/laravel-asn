<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Reno\ASN\AsnManager;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Data\Prefix;
use Reno\ASN\IpMatcher;

class TestDevice extends Model
{
    protected $table = 'test_devices';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function (): void {
    Schema::create('test_devices', function (Blueprint $table) {
        $table->id();
        $table->string('ip_address');
        $table->string('name')->nullable();
    });

    TestDevice::insert([
        ['ip_address' => '10.0.0.1', 'name' => 'Private A'],
        ['ip_address' => '10.0.0.50', 'name' => 'Private B'],
        ['ip_address' => '10.1.0.1', 'name' => 'Private C'],
        ['ip_address' => '172.16.0.1', 'name' => 'Private D'],
        ['ip_address' => '8.8.8.8', 'name' => 'Google DNS'],
        ['ip_address' => '1.1.1.1', 'name' => 'Cloudflare DNS'],
        ['ip_address' => '192.168.1.1', 'name' => 'Home Router'],
    ]);
});

afterEach(function (): void {
    Schema::dropIfExists('test_devices');
});

it('filters IPs within a single CIDR range', function (): void {
    $results = TestDevice::whereIpInRange('ip_address', '10.0.0.0/24')->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->all())->toContain('Private A', 'Private B');
});

it('filters IPs within multiple CIDR ranges', function (): void {
    $results = TestDevice::whereIpInRanges('ip_address', [
        '10.0.0.0/8',
        '172.16.0.0/12',
    ])->get();

    expect($results)->toHaveCount(4)
        ->and($results->pluck('name')->all())->toContain('Private A', 'Private B', 'Private C', 'Private D');
});

it('filters IPs by ASN prefixes', function (): void {
    // Mock ASN manager
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')
        ->with(64496)
        ->andReturn(collect([
            new Prefix('10.0.0.0/24', asn: 64496),
            new Prefix('192.168.1.0/24', asn: 64496),
        ]));

    $manager = new AsnManager(
        provider: $provider,
        cache: new CacheRepository(new ArrayStore),
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    $this->app->instance(AsnManager::class, $manager);

    $results = TestDevice::whereIpInAsn('ip_address', 64496)->get();

    expect($results)->toHaveCount(3)
        ->and($results->pluck('name')->all())->toContain('Private A', 'Private B', 'Home Router');
});

it('excludes IPs by ASN prefixes', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')
        ->with(64496)
        ->andReturn(collect([
            new Prefix('10.0.0.0/8', asn: 64496),
        ]));

    $manager = new AsnManager(
        provider: $provider,
        cache: new CacheRepository(new ArrayStore),
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    $this->app->instance(AsnManager::class, $manager);

    $results = TestDevice::whereIpNotInAsn('ip_address', 64496)->get();

    // Should exclude 10.0.0.1, 10.0.0.50, 10.1.0.1
    expect($results)->toHaveCount(4);
});

it('filters using a pre-compiled IpMatcher', function (): void {
    $matcher = (new IpMatcher)
        ->addPrefix('10.0.0.0/24')
        ->addIp('1.1.1.1')
        ->compile();

    $results = TestDevice::whereIpInMatcher('ip_address', $matcher)->get();

    expect($results)->toHaveCount(3)
        ->and($results->pluck('name')->all())->toContain('Private A', 'Private B', 'Cloudflare DNS');
});

it('handles normalized IP comparison', function (): void {
    $results = TestDevice::whereIpEquals('ip_address', '8.8.8.8')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Google DNS');
});

it('returns empty when ASN has no prefixes', function (): void {
    $provider = mock(AsnProvider::class);
    $provider->shouldReceive('getPrefixes')->with(99999)->andReturn(collect());

    $manager = new AsnManager(
        provider: $provider,
        cache: new CacheRepository(new ArrayStore),
        cacheEnabled: false,
        cacheTtl: 0,
        cachePrefix: 'test:',
    );
    $this->app->instance(AsnManager::class, $manager);

    $results = TestDevice::whereIpInAsn('ip_address', 99999)->get();

    expect($results)->toHaveCount(0);
});

it('chains IP queries with other conditions', function (): void {
    $results = TestDevice::where('name', 'like', 'Private%')
        ->whereIpInRange('ip_address', '10.0.0.0/24')
        ->get();

    expect($results)->toHaveCount(2);
});

it('works with the base query builder', function (): void {
    $results = DB::table('test_devices')
        ->whereIpInRange('ip_address', '10.0.0.0/8')
        ->get();

    expect($results)->toHaveCount(3);
});
