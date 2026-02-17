<?php

declare(strict_types=1);

namespace Reno\ASN\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Reno\ASN\AsnManager;
use Reno\ASN\Data\IpRange;
use Reno\ASN\Data\Prefix;
use Reno\ASN\Enums\DatabaseDriver;
use Reno\ASN\IpMatcher;

/**
 * Registers Eloquent Builder macros for querying IP address columns
 * against CIDR ranges, ASN prefixes, and explicit IP lists.
 *
 * Generates driver-optimized SQL:
 *   - PostgreSQL: native inet type with <<= operator
 *   - MySQL/MariaDB: INET_ATON() for IPv4, HEX/UNHEX for IPv6
 *   - SQLite: fallback to range extraction with string comparison
 */
final class AsnQueryMacros
{
    public static function register(): void
    {
        self::registerWhereIpInRange();
        self::registerWhereIpInRanges();
        self::registerWhereIpInAsn();
        self::registerWhereIpNotInAsn();
        self::registerWhereIpInMatcher();
        self::registerWhereIpEquals();
    }

    /**
     * ->whereIpInRange('ip_column', '104.16.0.0/12')
     */
    private static function registerWhereIpInRange(): void
    {
        Builder::macro('whereIpInRange', function (string $column, string $cidr, string $boolean = 'and'): Builder {
            /** @var Builder $this */
            $driver = DatabaseDriver::fromConnection($this->getConnection());

            return match (true) {
                $driver->supportsNativeInet() => $this->whereRaw(
                    "CAST({$column} AS inet) <<= ?::inet",
                    [$cidr],
                    $boolean,
                ),
                $driver->supportsInetAton() => AsnQueryMacros::mysqlCidrWhere($this, $column, $cidr, $boolean),
                default => AsnQueryMacros::fallbackCidrWhere($this, $column, $cidr, $boolean),
            };
        });

        QueryBuilder::macro('whereIpInRange', function (string $column, string $cidr, string $boolean = 'and'): QueryBuilder {
            /** @var QueryBuilder $this */
            $driver = DatabaseDriver::fromConnection($this->connection);

            return match (true) {
                $driver->supportsNativeInet() => $this->whereRaw(
                    "CAST({$column} AS inet) <<= ?::inet",
                    [$cidr],
                    $boolean,
                ),
                $driver->supportsInetAton() => AsnQueryMacros::mysqlCidrWhereBase($this, $column, $cidr, $boolean),
                default => AsnQueryMacros::fallbackCidrWhereBase($this, $column, $cidr, $boolean),
            };
        });
    }

    /**
     * ->whereIpInRanges('ip_column', ['104.16.0.0/12', '172.64.0.0/13'])
     */
    private static function registerWhereIpInRanges(): void
    {
        Builder::macro('whereIpInRanges', function (string $column, array $cidrs, string $boolean = 'and'): Builder {
            /** @var Builder $this */
            $driver = DatabaseDriver::fromConnection($this->getConnection());

            if ($driver->supportsNativeInet()) {
                return $this->where(function (Builder $q) use ($column, $cidrs): void {
                    foreach ($cidrs as $cidr) {
                        $q->orWhereRaw("CAST({$column} AS inet) <<= ?::inet", [$cidr]);
                    }
                }, boolean: $boolean);
            }

            // MySQL/SQLite: use BETWEEN with computed ranges
            $ranges = collect($cidrs)->map(fn (string $cidr): IpRange => (new Prefix($cidr))->toRange());

            return $this->where(function (Builder $q) use ($column, $ranges, $driver): void {
                $ranges->each(function (IpRange $range) use ($q, $column, $driver): void {
                    match (true) {
                        ! $range->isIpv6 && $driver->supportsInetAton() => $q->orWhereRaw(
                            "INET_ATON({$column}) BETWEEN ? AND ?",
                            [$range->startLong(), $range->endLong()],
                        ),
                        ! $range->isIpv6 => $q->orWhereRaw(
                            AsnQueryMacros::sqliteIpToInt($column).' BETWEEN ? AND ?',
                            [$range->startLong(), $range->endLong()],
                        ),
                        default => $q->orWhereBetween($column, [
                            $range->startAddress(),
                            $range->endAddress(),
                        ]),
                    };
                });
            }, boolean: $boolean);
        });
    }

    /**
     * ->whereIpInAsn('ip_column', 13335)
     * ->whereIpInAsn('ip_column', [13335, 15169])
     *
     * Resolves ASN prefixes via AsnManager, then generates range queries.
     */
    private static function registerWhereIpInAsn(): void
    {
        Builder::macro('whereIpInAsn', function (string $column, int|array $asns, string $boolean = 'and'): Builder {
            /** @var Builder $this */
            $manager = resolve(AsnManager::class);
            $asns = is_int($asns) ? [$asns] : $asns;

            $allCidrs = collect($asns)
                ->flatMap(fn (int $asn): Collection => $manager->getPrefixes($asn))
                ->map(fn (Prefix $p): string => $p->prefix)
                ->all();

            if ($allCidrs === []) {
                return $this->whereRaw('1 = 0', boolean: $boolean); // No prefixes = no matches
            }

            return $this->whereIpInRanges($column, $allCidrs, $boolean);
        });
    }

    /**
     * ->whereIpNotInAsn('ip_column', 7922) // Exclude Comcast IPs
     */
    private static function registerWhereIpNotInAsn(): void
    {
        Builder::macro('whereIpNotInAsn', function (string $column, int|array $asns, string $boolean = 'and'): Builder {
            /** @var Builder $this */
            $manager = resolve(AsnManager::class);
            $asns = is_int($asns) ? [$asns] : $asns;
            $driver = DatabaseDriver::fromConnection($this->getConnection());

            $allPrefixes = collect($asns)
                ->flatMap(fn (int $asn): Collection => $manager->getPrefixes($asn));

            if ($allPrefixes->isEmpty()) {
                return $this; // No prefixes to exclude
            }

            if ($driver->supportsNativeInet()) {
                return $this->where(function (Builder $q) use ($column, $allPrefixes): void {
                    $allPrefixes->each(function (Prefix $p) use ($q, $column): void {
                        $q->whereRaw("NOT (CAST({$column} AS inet) <<= ?::inet)", [$p->prefix]);
                    });
                }, boolean: $boolean);
            }

            $ranges = $allPrefixes->map(fn (Prefix $p): IpRange => $p->toRange());

            return $this->where(function (Builder $q) use ($column, $ranges, $driver): void {
                $ranges->each(function (IpRange $range) use ($q, $column, $driver): void {
                    match (true) {
                        ! $range->isIpv6 && $driver->supportsInetAton() => $q->whereRaw(
                            "INET_ATON({$column}) NOT BETWEEN ? AND ?",
                            [$range->startLong(), $range->endLong()],
                        ),
                        ! $range->isIpv6 => $q->whereRaw(
                            AsnQueryMacros::sqliteIpToInt($column).' NOT BETWEEN ? AND ?',
                            [$range->startLong(), $range->endLong()],
                        ),
                        default => $q->whereNotBetween($column, [
                            $range->startAddress(),
                            $range->endAddress(),
                        ]),
                    };
                });
            }, boolean: $boolean);
        });
    }

    /**
     * ->whereIpInMatcher('ip_column', $matcher)
     *
     * For pre-compiled IpMatcher instances (most efficient for complex blocklists).
     */
    private static function registerWhereIpInMatcher(): void
    {
        Builder::macro('whereIpInMatcher', function (string $column, IpMatcher $matcher, string $boolean = 'and'): Builder {
            /** @var Builder $this */
            $driver = DatabaseDriver::fromConnection($this->getConnection());

            return $this->where(function (Builder $q) use ($column, $matcher, $driver): void {
                foreach ($matcher->v4Ranges() as $range) {
                    match (true) {
                        $driver->supportsInetAton() => $q->orWhereRaw(
                            "INET_ATON({$column}) BETWEEN ? AND ?",
                            [$range->startLong(), $range->endLong()],
                        ),
                        $driver->supportsNativeInet() => $q->orWhereRaw(
                            "CAST({$column} AS inet) <<= ?::inet",
                            [$range->prefix],
                        ),
                        default => $q->orWhereRaw(
                            AsnQueryMacros::sqliteIpToInt($column).' BETWEEN ? AND ?',
                            [$range->startLong(), $range->endLong()],
                        ),
                    };
                }

                foreach ($matcher->v6Ranges() as $range) {
                    if ($driver->supportsNativeInet()) {
                        $q->orWhereRaw(
                            "CAST({$column} AS inet) <<= ?::inet",
                            [$range->prefix],
                        );
                    } else {
                        $q->orWhereBetween($column, [
                            $range->startAddress(),
                            $range->endAddress(),
                        ]);
                    }
                }
            }, boolean: $boolean);
        });
    }

    /**
     * ->whereIpEquals('ip_column', '104.16.0.1')
     *
     * Normalized IP comparison (handles leading zeros, IPv6 expansion).
     */
    private static function registerWhereIpEquals(): void
    {
        Builder::macro('whereIpEquals', function (string $column, string $ip, string $boolean = 'and'): Builder {
            /** @var Builder $this */
            $driver = DatabaseDriver::fromConnection($this->getConnection());

            if ($driver->supportsNativeInet()) {
                return $this->whereRaw(
                    "CAST({$column} AS inet) = ?::inet",
                    [$ip],
                    $boolean,
                );
            }

            if ($driver->supportsInetAton() && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $this->whereRaw(
                    "INET_ATON({$column}) = INET_ATON(?)",
                    [$ip],
                    $boolean,
                );
            }

            // Normalize and direct compare
            $packed = inet_pton($ip);
            $normalized = ($packed !== false) ? (string) inet_ntop($packed) : $ip;

            return $this->where($column, '=', $normalized, $boolean);
        });
    }

    /**
     * MySQL CIDR WHERE clause using INET_ATON BETWEEN.
     */
    /** @internal */
    public static function mysqlCidrWhere(Builder $query, string $column, string $cidr, string $boolean): Builder
    {
        $prefix = new Prefix($cidr);
        $range = $prefix->toRange();

        if (! $prefix->isIpv6) {
            return $query->whereRaw(
                "INET_ATON({$column}) BETWEEN ? AND ?",
                [$range->startLong(), $range->endLong()],
                $boolean,
            );
        }

        return $query->whereBetween($column, [
            $range->startAddress(),
            $range->endAddress(),
        ], $boolean);
    }

    /**
     * MySQL base query builder CIDR WHERE.
     */
    /** @internal */
    public static function mysqlCidrWhereBase(QueryBuilder $query, string $column, string $cidr, string $boolean): QueryBuilder
    {
        $prefix = new Prefix($cidr);
        $range = $prefix->toRange();

        if (! $prefix->isIpv6) {
            return $query->whereRaw(
                "INET_ATON({$column}) BETWEEN ? AND ?",
                [$range->startLong(), $range->endLong()],
                $boolean,
            );
        }

        return $query->whereBetween($column, [
            $range->startAddress(),
            $range->endAddress(),
        ], $boolean);
    }

    /**
     * Fallback for SQLite: use numeric comparison via octet extraction.
     */
    /** @internal */
    public static function fallbackCidrWhere(Builder $query, string $column, string $cidr, string $boolean): Builder
    {
        $prefix = new Prefix($cidr);
        $range = $prefix->toRange();

        if (! $prefix->isIpv6) {
            $ipToInt = self::sqliteIpToInt($column);

            return $query->whereRaw(
                "{$ipToInt} BETWEEN ? AND ?",
                [$range->startLong(), $range->endLong()],
                $boolean,
            );
        }

        return $query->whereBetween(
            $column,
            [$range->startAddress(), $range->endAddress()],
            $boolean,
        );
    }

    /**
     * Fallback for SQLite: base query builder variant.
     */
    /** @internal */
    public static function fallbackCidrWhereBase(QueryBuilder $query, string $column, string $cidr, string $boolean): QueryBuilder
    {
        $prefix = new Prefix($cidr);
        $range = $prefix->toRange();

        if (! $prefix->isIpv6) {
            $ipToInt = self::sqliteIpToInt($column);

            return $query->whereRaw(
                "{$ipToInt} BETWEEN ? AND ?",
                [$range->startLong(), $range->endLong()],
                $boolean,
            );
        }

        return $query->whereBetween(
            $column,
            [$range->startAddress(), $range->endAddress()],
            $boolean,
        );
    }

    /**
     * SQLite-compatible expression to convert an IPv4 dotted string to integer.
     */
    /** @internal */
    public static function sqliteIpToInt(string $column): string
    {
        return <<<SQL
        (
            CAST(SUBSTR({$column}, 1, INSTR({$column}, '.') - 1) AS INTEGER) * 16777216 +
            CAST(SUBSTR({$column}, INSTR({$column}, '.') + 1, INSTR(SUBSTR({$column}, INSTR({$column}, '.') + 1), '.') - 1) AS INTEGER) * 65536 +
            CAST(SUBSTR(SUBSTR({$column}, INSTR({$column}, '.') + 1), INSTR(SUBSTR({$column}, INSTR({$column}, '.') + 1), '.') + 1, INSTR(SUBSTR(SUBSTR({$column}, INSTR({$column}, '.') + 1), INSTR(SUBSTR({$column}, INSTR({$column}, '.') + 1), '.') + 1), '.') - 1) AS INTEGER) * 256 +
            CAST(SUBSTR(SUBSTR(SUBSTR({$column}, INSTR({$column}, '.') + 1), INSTR(SUBSTR({$column}, INSTR({$column}, '.') + 1), '.') + 1), INSTR(SUBSTR(SUBSTR({$column}, INSTR({$column}, '.') + 1), INSTR(SUBSTR({$column}, INSTR({$column}, '.') + 1), '.') + 1), '.') + 1) AS INTEGER)
        )
        SQL;
    }
}
