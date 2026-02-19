<?php

declare(strict_types=1);

namespace Reno\ASN\Providers;

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Collection;
use Reno\ASN\Contracts\AsnProvider;
use Reno\ASN\Data\AsnInfo;
use Reno\ASN\Data\AsnResult;
use Reno\ASN\Data\Prefix;
use Reno\ASN\Exceptions\AsnLookupException;

final class IpInfoProvider implements AsnProvider
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly int $timeout,
        private readonly int $retryTimes,
        private readonly int $retryDelay,
        private readonly string $token,
    ) {}

    public function lookupIp(string $ip): AsnInfo
    {
        $response = $this->request("https://api.ipinfo.io/lite/{$ip}", [
            'token' => $this->token,
        ]);

        $rawAsn = $response['asn'] ?? null;

        if (! is_string($rawAsn) || $rawAsn === '') {
            throw AsnLookupException::ipNotFound($ip);
        }

        $asn = (int) ltrim($rawAsn, 'AS');

        $name = is_string($response['as_name'] ?? null) ? $response['as_name'] : '';

        return new AsnInfo(
            asn: $asn,
            name: $name,
            description: $name,
            country: isset($response['country_code']) && is_string($response['country_code']) ? $response['country_code'] : null,
        );
    }

    public function getPrefixes(int $asn): Collection
    {
        $response = $this->request("https://ipinfo.io/AS{$asn}/json", [
            'token' => $this->token,
        ]);

        /** @var list<array<string, mixed>> $ipv4 */
        $ipv4 = is_array($response['prefixes'] ?? null) ? $response['prefixes'] : [];

        /** @var list<array<string, mixed>> $ipv6 */
        $ipv6 = is_array($response['prefixes6'] ?? null) ? $response['prefixes6'] : [];

        $all = array_merge($ipv4, $ipv6);

        if ($all === []) {
            throw AsnLookupException::asnNotFound($asn);
        }

        return collect($all)->map(fn (array $entry): Prefix => new Prefix(
            prefix: is_string($entry['netblock'] ?? null) ? $entry['netblock'] : '0.0.0.0/0',
            name: is_string($entry['name'] ?? null) ? $entry['name'] : null,
            description: is_string($entry['id'] ?? null) ? $entry['id'] : null,
            country: is_string($entry['country'] ?? null) ? $entry['country'] : null,
            asn: $asn,
        ))->filter(fn (Prefix $p): bool => $p->prefix !== '0.0.0.0/0')->values();
    }

    public function getAsn(int $asn): AsnResult
    {
        $response = $this->request("https://ipinfo.io/AS{$asn}/json", [
            'token' => $this->token,
        ]);

        if ($response === []) {
            throw AsnLookupException::asnNotFound($asn);
        }

        $name = is_string($response['name'] ?? null) ? $response['name'] : '';

        $info = new AsnInfo(
            asn: $asn,
            name: $name,
            description: $name,
            country: isset($response['country']) && is_string($response['country']) ? $response['country'] : null,
            rir: isset($response['registry']) && is_string($response['registry']) ? $response['registry'] : null,
        );

        /** @var list<array<string, mixed>> $ipv4 */
        $ipv4 = is_array($response['prefixes'] ?? null) ? $response['prefixes'] : [];

        /** @var list<array<string, mixed>> $ipv6 */
        $ipv6 = is_array($response['prefixes6'] ?? null) ? $response['prefixes6'] : [];

        $all = array_merge($ipv4, $ipv6);

        $prefixes = collect($all)->map(fn (array $entry): Prefix => new Prefix(
            prefix: is_string($entry['netblock'] ?? null) ? $entry['netblock'] : '0.0.0.0/0',
            name: is_string($entry['name'] ?? null) ? $entry['name'] : null,
            description: is_string($entry['id'] ?? null) ? $entry['id'] : null,
            country: is_string($entry['country'] ?? null) ? $entry['country'] : null,
            asn: $asn,
        ))->filter(fn (Prefix $p): bool => $p->prefix !== '0.0.0.0/0')->values();

        return new AsnResult(info: $info, prefixes: $prefixes);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $url, array $query = []): array
    {
        $response = $this->http
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelay)
            ->acceptJson()
            ->get($url, $query);

        if ($response->failed()) {
            throw AsnLookupException::requestFailed($url, $response->status());
        }

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }
}
