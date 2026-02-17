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

final class RipeStatProvider implements AsnProvider
{
    private const BASE_URL = 'https://stat.ripe.net/data';

    public function __construct(
        private readonly HttpClient $http,
        private readonly int $timeout,
        private readonly int $retryTimes,
        private readonly int $retryDelay,
    ) {}

    public function lookupIp(string $ip): AsnInfo
    {
        $response = $this->request('/network-info/data.json', [
            'resource' => $ip,
        ]);

        /** @var array<string, mixed>|null $data */
        $data = $response['data'] ?? null;

        /** @var list<string|int> $asns */
        $asns = is_array($data) ? ($data['asns'] ?? []) : [];

        if ($asns === []) {
            throw AsnLookupException::ipNotFound($ip);
        }

        $asn = (int) $asns[0];

        // Fetch full ASN details
        $asnResponse = $this->request('/as-overview/data.json', [
            'resource' => "AS{$asn}",
        ]);

        /** @var array<string, mixed> $asnData */
        $asnData = $asnResponse['data'] ?? [];

        return new AsnInfo(
            asn: $asn,
            name: is_string($asnData['holder'] ?? null) ? $asnData['holder'] : '',
            description: is_string($asnData['holder'] ?? null) ? $asnData['holder'] : '',
            country: isset($asnData['resource_country']) && is_string($asnData['resource_country']) ? $asnData['resource_country'] : null,
            rir: isset($asnData['block']) && is_array($asnData['block']) && isset($asnData['block']['resource']) && is_string($asnData['block']['resource'])
                ? $asnData['block']['resource']
                : null,
        );
    }

    public function getPrefixes(int $asn): Collection
    {
        $response = $this->request('/announced-prefixes/data.json', [
            'resource' => "AS{$asn}",
        ]);

        /** @var array<string, mixed>|null $data */
        $data = $response['data'] ?? null;

        /** @var list<array<string, mixed>> $announced */
        $announced = is_array($data) ? ($data['prefixes'] ?? []) : [];

        if ($announced === []) {
            throw AsnLookupException::asnNotFound($asn);
        }

        return collect($announced)->map(fn (array $entry): Prefix => new Prefix(
            prefix: is_string($entry['prefix'] ?? null) ? $entry['prefix'] : '0.0.0.0/0',
            asn: $asn,
        ))->filter(fn (Prefix $p): bool => $p->prefix !== '0.0.0.0/0')->values();
    }

    public function getAsn(int $asn): AsnResult
    {
        $asnResponse = $this->request('/as-overview/data.json', [
            'resource' => "AS{$asn}",
        ]);

        /** @var array<string, mixed>|null $asnData */
        $asnData = $asnResponse['data'] ?? null;

        if ($asnData === null) {
            throw AsnLookupException::asnNotFound($asn);
        }

        $info = new AsnInfo(
            asn: $asn,
            name: is_string($asnData['holder'] ?? null) ? $asnData['holder'] : '',
            description: is_string($asnData['holder'] ?? null) ? $asnData['holder'] : '',
            country: isset($asnData['resource_country']) && is_string($asnData['resource_country']) ? $asnData['resource_country'] : null,
        );

        $prefixes = $this->getPrefixes($asn);

        return new AsnResult(info: $info, prefixes: $prefixes);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query = []): array
    {
        $response = $this->http
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelay)
            ->acceptJson()
            ->get(self::BASE_URL.$path, $query);

        if ($response->failed()) {
            throw AsnLookupException::requestFailed(
                self::BASE_URL.$path,
                $response->status(),
            );
        }

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }
}
