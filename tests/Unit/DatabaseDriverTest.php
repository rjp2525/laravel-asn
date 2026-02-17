<?php

declare(strict_types=1);

use Reno\ASN\Enums\DatabaseDriver;

it('resolves from string value', function (): void {
    expect(DatabaseDriver::from('mysql'))->toBe(DatabaseDriver::MySQL)
        ->and(DatabaseDriver::from('pgsql'))->toBe(DatabaseDriver::PostgreSQL)
        ->and(DatabaseDriver::from('sqlite'))->toBe(DatabaseDriver::SQLite)
        ->and(DatabaseDriver::from('mariadb'))->toBe(DatabaseDriver::MariaDB);
});

it('reports native inet support correctly', function (): void {
    expect(DatabaseDriver::PostgreSQL->supportsNativeInet())->toBeTrue()
        ->and(DatabaseDriver::MySQL->supportsNativeInet())->toBeFalse()
        ->and(DatabaseDriver::SQLite->supportsNativeInet())->toBeFalse()
        ->and(DatabaseDriver::MariaDB->supportsNativeInet())->toBeFalse();
});

it('reports INET_ATON support correctly', function (): void {
    expect(DatabaseDriver::MySQL->supportsInetAton())->toBeTrue()
        ->and(DatabaseDriver::MariaDB->supportsInetAton())->toBeTrue()
        ->and(DatabaseDriver::PostgreSQL->supportsInetAton())->toBeFalse()
        ->and(DatabaseDriver::SQLite->supportsInetAton())->toBeFalse();
});

it('defaults to MySQL for non-Connection ConnectionInterface', function (): void {
    $connection = mock(\Illuminate\Database\ConnectionInterface::class);

    $driver = DatabaseDriver::fromConnection($connection);

    expect($driver)->toBe(DatabaseDriver::MySQL);
});
