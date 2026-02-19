# Changelog

All notable changes to `laravel-asn` will be documented in this file.

## v1.1.0 - 2026-02-19

### Changed

- **Default provider switched from BgpView to RipeStat.** BgpView (`api.bgpview.io`) shut down in November 2025 and no longer resolves. Existing apps relying on the default will now use RipeStat automatically.

### Added

- **IPinfo provider** — a new provider backed by the [IPinfo](https://ipinfo.io) API. Supports IP lookups, ASN details, and prefix resolution. Requires an API token via the `ASN_IPINFO_TOKEN` environment variable.

### Removed

- **BgpView provider** — removed entirely since `api.bgpview.io` no longer resolves. If your config explicitly set `ASN_PROVIDER=bgpview`, switch to `ripestat` or `ipinfo`.

## v1.0.0 - 2026-02-16 - Initial Release

### IP & ASN Lookups

- Look up any IP address to get its ASN, network name, description, and country
- Fetch all prefixes announced by an ASN (IPv4 and IPv6)
- Check if an IP belongs to a specific ASN, or match it against multiple ASNs at once
- Compare two IPs to see if they share the same ASN
- Batch check lists of IPs against lists of ASNs in a single call

### High-Performance IP Matcher

- Binary search-based matcher built for checking IPs against thousands of CIDR ranges at scale
- Add prefixes, single IPs, or explicit start-end ranges — then compile once and query in O(log n)
- Build matchers directly from ASN numbers with `Asn::buildMatcher()`
- Separate IPv4 and IPv6 range access for driver-optimized queries

### Domain Resolution

- Resolve domains to IPs and look up their ASN in one step via the `AsnDns` facade
- Check if a domain belongs to a specific ASN or matches any from a list
- Test domain IPs against a compiled `IpMatcher` for complex allowlists/blocklists
- Automatic domain normalization — strips protocols, paths, trailing dots, and lowercases
- Separate DNS cache with its own TTL

### Validation Rules

- `IpInAsn` — validate that an IP belongs to one or more ASNs
- `IpNotInAsn` — reject IPs from specific ASNs (fails open on lookup errors)
- `IpInRange` — restrict IPs to specific CIDR ranges
- `DomainInAsn` — validate that a domain resolves to an IP within specified ASNs
- All four rules work as PHP 8 Attributes for use on DTOs

### Eloquent Query Macros

- `whereIpInRange`, `whereIpInRanges`, `whereIpInAsn`, `whereIpNotInAsn`, `whereIpInMatcher`, `whereIpEquals`
- Driver-optimized SQL: native `inet` operators on PostgreSQL, `INET_ATON()` on MySQL/MariaDB, numeric extraction on SQLite
- Works on both Eloquent Builder and the base Query Builder

### Artisan Commands

- `asn:lookup {ip}` — display full ASN details and prefix table for an IP
- `asn:check {ip} {asn}` — verify if an IP belongs to an ASN
- `asn:prefixes {asn}` — list all announced prefixes with `--ipv4-only`, `--ipv6-only`, and `--count` options
- `asn:domain {domain}` — resolve a domain and show its ASN, with optional `--check-asn` validation

### Providers

- **RipeStat** (default) and **IPinfo** providers included out of the box
- Pluggable provider contract — implement `AsnProvider` to bring your own data source
- Register custom providers via config with full HTTP client and config passthrough

### Caching & Configuration

- Built-in caching for IP lookups, ASN prefixes, and DNS resolution — each with independent TTLs
- Configurable cache store, HTTP timeouts, retry strategy, and batch chunk sizes
- Manual cache flushing per-ASN or per-IP via `Asn::flushAsn()` and `Asn::flushIp()`

### Developer Experience

- `Asn` and `AsnDns` facades for clean, expressive usage
- Full IPv4 and IPv6 support throughout the entire API
- Typed DTOs (`AsnInfo`, `AsnResult`, `Prefix`, `IpRange`, `MatchResult`) with `toArray()` support
- Structured exceptions: `AsnLookupException` and `DomainResolutionException`
- 191 tests, 94%+ code coverage
- Requires PHP 8.4+ and Laravel 11/12
