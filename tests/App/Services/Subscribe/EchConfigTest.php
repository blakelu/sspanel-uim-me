<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use PHPUnit\Framework\TestCase;

final class EchConfigTest extends TestCase
{
    private const KEY = 'AEX+DQBBBwAgACA0ZfO9G0mfh7bIMcIFxViIDwERQ/Cfw2FNVgylWE7yNAAEAAEAAQASY2xvdWRmbGFyZS1lY2guY29tAAA=';

    public function testParsesCloudflareAndQuotedDNSAnswers(): void
    {
        foreach (['ech=' . self::KEY, 'ech="' . self::KEY . '"'] as $parameter) {
            $response = ['Status' => 0, 'Answer' => [
                ['type' => 1, 'data' => '104.21.42.5'],
                ['type' => 65, 'data' => '1 . alpn=h3,h2 ' . $parameter . ' ipv4hint=104.21.42.5'],
            ]];
            $this->assertSame(self::KEY, EchConfig::fromDNSResponse($response));
        }
    }

    public function testRejectsFailedDNSResponsesAndMalformedECHLists(): void
    {
        $this->assertSame('', EchConfig::fromDNSResponse(['Status' => 3, 'Answer' => []]));
        $this->assertSame('', EchConfig::fromDNSResponse(['Status' => 0, 'Answer' => [['type' => 65, 'data' => '1 . alpn=h2']]]));
        foreach (['true', 'auto', 'https://dns.example/dns-query', base64_encode("\x00\x05\xfe\x0d\x00\x01"), base64_encode("\x00\x05\x00\x01\x00\x01x")] as $invalid) {
            $this->assertFalse(EchConfig::isValid($invalid));
        }
        $this->assertTrue(EchConfig::isValid(self::KEY));
    }

    public function testOptInAndFallbackKeepLegacyNodesUnchanged(): void
    {
        $this->assertNull(EchConfig::getOptions('de.cf.example.com', []));
        $this->assertNull(EchConfig::getOptions('de.cf.example.com', ['ech-opts' => ['enable' => 'false', 'config' => self::KEY]]));
        $this->assertSame(['enable' => true], EchConfig::getOptions('de.cf.example.com', ['ech-opts' => ['enable' => true]]));
        $config = ['ech_auto' => true, 'ech-opts' => ['enable' => true, 'config' => self::KEY]];
        // Invalid DNS names never make an outbound request; keep the saved key on failure.
        $this->assertSame(['enable' => true, 'config' => self::KEY], EchConfig::getOptions('invalid/name', $config));
    }

    public function testFreshCacheAvoidsDNSRequests(): void
    {
        $cached = ['config' => self::KEY, 'fetched_at' => 1000];
        $query = static function (): array {
            self::fail('A fresh ECH cache should avoid a DNS request');
        };
        $this->assertSame($cached, $this->fetch($cached, $query, 1059));
    }

    public function testFailedPrimaryResolverUsesSecondaryAndRefreshesCache(): void
    {
        $providers = [];
        $query = static function (string $provider, string $hostname) use (&$providers): array {
            self::assertSame('de.cf.example.com', $hostname);
            $providers[] = $provider;
            return count($providers) === 1 ? ['Status' => 2] : [
                'Status' => 0, 'Answer' => [['type' => 65, 'data' => '1 . ech=' . self::KEY]],
            ];
        };
        $this->assertSame(['config' => self::KEY, 'fetched_at' => 1060], $this->fetch(null, $query, 1060));
        $this->assertSame(['https://cloudflare-dns.com/dns-query', 'https://dns.google/resolve'], $providers);
    }

    public function testResolverOutageKeepsRecentSuccessfulConfigButRejectsExpiredCache(): void
    {
        $calls = 0;
        $query = static function () use (&$calls): array {
            $calls++;
            return [];
        };
        $cached = ['config' => self::KEY, 'fetched_at' => 1000];
        $this->assertSame($cached, $this->fetch($cached, $query, 1060));
        $this->assertSame(2, $calls);
        $this->assertNull($this->fetch($cached, $query, 87400));
        $this->assertNull($this->fetch($cached, $query, 999));
        $this->assertNull($this->fetch(['config' => 'invalid', 'fetched_at' => 1060], $query, 1060));
    }

    private function fetch(?array $cached, callable $query, int $now): ?array
    {
        $method = new \ReflectionMethod(EchConfig::class, 'fetch');

        return $method->invoke(null, 'de.cf.example.com', $cached, $query, $now);
    }
}
