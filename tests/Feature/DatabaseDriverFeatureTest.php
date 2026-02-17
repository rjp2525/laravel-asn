<?php

declare(strict_types=1);

use Reno\ASN\Enums\DatabaseDriver;

it('resolves from null connection using default', function (): void {
    $driver = DatabaseDriver::fromConnection(null);

    // Default test connection is SQLite
    expect($driver)->toBe(DatabaseDriver::SQLite);
});

it('resolves from a Connection instance', function (): void {
    $connection = resolve('db')->connection();

    $driver = DatabaseDriver::fromConnection($connection);

    expect($driver)->toBe(DatabaseDriver::SQLite);
});
