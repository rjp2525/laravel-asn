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

final class BgpViewProvider implements AsnProvider
{
    private const BASE_URL = 'https://api.bgpview.io';

    public function __construct(
        private readonly HttpClient $http,
        private readonly int $timeout,
        private readonly int $retryTimes,
        private readonly int $retryDelay,
    ) {}

    public function lookupIp(string $ip): AsnInfo
    {
        $response = $this->request("/ip/{$ip}");

        /** @var array<string, mixed>|null $data */
        $data = $response['data'] ?? null;

        if ($data === null || ! isset($data['prefixes']) || ! is_array($data['prefixes']) || $data['prefixes'] === []) {
            throw AsnLookupException::ipNotFound($ip);
        }

        /** @var array<string, mixed> $prefix */
        $prefix = $data['prefixes'][0];

        /** @var array<string, mixed>|null $asnData */
        $asnData = $prefix['asn'] ?? null;

        if (! is_array($asnData) || ! isset($asnData['asn'])) {
            throw AsnLookupException::ipNotFound($ip);
        }

        return new AsnInfo(
            asn: (int) $asnData['asn'],
            name: (string) ($asnData['name'] ?? ''),
            description: (string) ($asnData['description'] ?? ''),
            country: isset($asnData['country_code']) ? (string) $asnData['country_code'] : null,
        );
    }

    public function getPrefixes(int $asn): Collection
    {
        $response = $this->request("/asn/{$asn}/prefixes");

        /** @var array<string, mixed>|null $data */
        $data = $response['data'] ?? null;

        if ($data === null) {
            throw AsnLookupException::asnNotFound($asn);
        }

        /** @var Collection<int, Prefix> $prefixes */
        $prefixes = collect();

        foreach (['ipv4_prefixes', 'ipv6_prefixes'] as $key) {
            /** @var array<int, array<string, mixed>> $entries */
            $entries = $data[$key] ?? [];

            foreach ($entries as $entry) {
                if (! is_array($entry) || ! isset($entry['prefix']) || ! is_string($entry['prefix'])) {
                    continue;
                }

                $prefixes->push(new Prefix(
                    prefix: $entry['prefix'],
                    name: isset($entry['name']) && is_string($entry['name']) ? $entry['name'] : null,
                    description: isset($entry['description']) && is_string($entry['description']) ? $entry['description'] : null,
                    country: isset($entry['country_code']) && is_string($entry['country_code']) ? $entry['country_code'] : null,
                    asn: $asn,
                ));
            }
        }

        return $prefixes;
    }

    public function getAsn(int $asn): AsnResult
    {
        $response = $this->request("/asn/{$asn}");

        /** @var array<string, mixed>|null $data */
        $data = $response['data'] ?? null;

        if ($data === null) {
            throw AsnLookupException::asnNotFound($asn);
        }

        $info = new AsnInfo(
            asn: (int) ($data['asn'] ?? $asn),
            name: (string) ($data['name'] ?? ''),
            description: (string) ($data['description_short'] ?? $data['description_full'] ?? ''),
            country: isset($data['country_code']) && is_string($data['country_code']) ? $data['country_code'] : null,
            rir: isset($data['rir_allocation']) && is_array($data['rir_allocation']) && isset($data['rir_allocation']['rir_name']) && is_string($data['rir_allocation']['rir_name'])
                ? $data['rir_allocation']['rir_name']
                : null,
        );

        return new AsnResult(info: $info, prefixes: $this->getPrefixes($asn));
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $path): array
    {
        $response = $this->http
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelay)
            ->acceptJson()
            ->get(self::BASE_URL.$path);

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
