<?php

declare(strict_types=1);

namespace Reno\ASN\Enums;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

enum DatabaseDriver: string
{
    case MySQL = 'mysql';
    case PostgreSQL = 'pgsql';
    case SQLite = 'sqlite';
    case MariaDB = 'mariadb';

    /**
     * Resolve from a Laravel DB connection.
     */
    public static function fromConnection(ConnectionInterface|Connection|null $connection = null): self
    {
        if ($connection === null) {
            $connection = DB::connection();
        }

        if ($connection instanceof Connection) {
            return self::tryFrom($connection->getDriverName()) ?? self::MySQL;
        }

        return self::MySQL;
    }

    public function supportsNativeInet(): bool
    {
        return match ($this) {
            self::PostgreSQL => true,
            default => false,
        };
    }

    public function supportsInetAton(): bool
    {
        return match ($this) {
            self::MySQL, self::MariaDB => true,
            default => false,
        };
    }
}
