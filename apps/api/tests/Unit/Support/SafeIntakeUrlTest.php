<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Domains\Intake\Exceptions\UnsafeIntakeUrl;
use App\Domains\Intake\Support\SafeIntakeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

final class SafeIntakeUrlTest extends TestCase
{
    public function test_public_https_hosts_pass_and_return_resolved_addresses(): void
    {
        $guard = new SafeIntakeUrl(new FakeHostResolver(['university.example.edu' => ['93.184.216.34']]));

        $safe = $guard->assertSafe('https://university.example.edu/syllabus?week=3');

        self::assertSame('university.example.edu', $safe['host']);
        self::assertSame(['93.184.216.34'], $safe['addresses']);
    }

    #[DataProvider('unsafeUrlProvider')]
    public function test_unsafe_urls_are_rejected(string $url, array $hostMap): void
    {
        $guard = new SafeIntakeUrl(new FakeHostResolver($hostMap));

        $this->expectException(UnsafeIntakeUrl::class);

        $guard->assertSafe($url);
    }

    /** @return iterable<string, array{string, array<string, list<string>>}> */
    public static function unsafeUrlProvider(): iterable
    {
        yield 'plain http' => ['http://university.example.edu/x', []];
        yield 'ftp scheme' => ['ftp://university.example.edu/x', []];
        yield 'credentials' => ['https://user:pw@university.example.edu/x', []];
        yield 'non-default port' => ['https://university.example.edu:8443/x', []];
        yield 'ipv4 literal' => ['https://93.184.216.34/x', []];
        yield 'ipv6 literal' => ['https://[2001:db8::1]/x', []];
        yield 'no dot host' => ['https://localhost/x', []];
        yield 'control characters' => ["https://university.example.edu/\x01x", []];
        yield 'loopback dns' => ['https://loop.example.edu/x', ['loop.example.edu' => ['127.0.0.1']]];
        yield 'private 10.x dns' => ['https://internal.example.edu/x', ['internal.example.edu' => ['10.0.0.5']]];
        yield 'private 192.168 dns' => ['https://lan.example.edu/x', ['lan.example.edu' => ['192.168.1.10']]];
        yield 'link-local metadata' => ['https://meta.example.edu/x', ['meta.example.edu' => ['169.254.169.254']]];
        yield 'carrier-grade nat' => ['https://cgn.example.edu/x', ['cgn.example.edu' => ['100.64.0.9']]];
        yield 'benchmarking range' => ['https://bench.example.edu/x', ['bench.example.edu' => ['198.18.0.7']]];
        yield 'multicast' => ['https://cast.example.edu/x', ['cast.example.edu' => ['224.0.0.1']]];
        yield 'ipv6 unique local' => ['https://ula.example.edu/x', ['ula.example.edu' => ['fd12:3456:789a::1']]];
        yield 'ipv6 link local' => ['https://ll.example.edu/x', ['ll.example.edu' => ['fe80::1']]];
        yield 'nat64 mapped ipv4' => ['https://nat64.example.edu/x', ['nat64.example.edu' => ['64:ff9b::a00:1']]];
        yield 'ipv4 mapped ipv6' => ['https://mapped.example.edu/x', ['mapped.example.edu' => ['::ffff:10.0.0.1']]];
        yield 'mixed public and private' => ['https://mixed.example.edu/x', ['mixed.example.edu' => ['93.184.216.34', '10.0.0.5']]];
        yield 'unresolvable host' => ['https://ghost.example.edu/x', ['ghost.example.edu' => []]];
    }
}
