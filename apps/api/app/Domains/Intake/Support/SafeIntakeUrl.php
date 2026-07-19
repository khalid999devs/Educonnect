<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use App\Domains\Intake\Contracts\HostResolver;
use App\Domains\Intake\Exceptions\UnsafeIntakeUrl;

/**
 * SSRF guard for user-provided intake links: exact https scheme, default port
 * only, no credentials or control characters, and every resolved address must
 * be a public unicast IP. Each redirect hop must re-pass this guard.
 */
final readonly class SafeIntakeUrl
{
    public function __construct(private HostResolver $resolver) {}

    /**
     * @return array{url: string, host: string, addresses: list<string>}
     */
    public function assertSafe(string $url): array
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($url === ''
            || strlen($url) > 2048
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || preg_match('/[\x00-\x1F\x7F]/u', $url) === 1) {
            throw new UnsafeIntakeUrl('malformed URL');
        }

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new UnsafeIntakeUrl('only https is allowed');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeIntakeUrl('credentials are not allowed');
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw new UnsafeIntakeUrl('only the default https port is allowed');
        }

        $host = strtolower(trim((string) ($parts['host'] ?? '')));

        if ($host === '' || str_ends_with($host, '.')) {
            throw new UnsafeIntakeUrl('missing host');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            || (str_starts_with($host, '[') && str_ends_with($host, ']'))) {
            throw new UnsafeIntakeUrl('IP-literal hosts are not allowed');
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || ! str_contains($host, '.')) {
            throw new UnsafeIntakeUrl('invalid hostname');
        }

        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new UnsafeIntakeUrl('host does not resolve');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicUnicast($address)) {
                throw new UnsafeIntakeUrl('host resolves to a blocked network');
            }
        }

        return ['url' => $url, 'host' => $host, 'addresses' => $addresses];
    }

    /**
     * Build the cURL connection-pin options for a validated result so the TCP
     * connection dials only the addresses that just passed this guard. Without
     * it, libcurl re-resolves the hostname independently at connect time, so a
     * low-TTL attacker domain could pass the guard on a public IP and then
     * resolve to a private/link-local IP (e.g. a cloud metadata endpoint) - 
     * a DNS-rebinding/TOCTOU SSRF. The https default port (443) is enforced by
     * assertSafe(), so the pin is fixed to it.
     *
     * @param  array{url: string, host: string, addresses: list<string>}  $safe
     * @return array<int, array<int, string>>
     */
    public static function curlPinOptions(array $safe): array
    {
        return [
            CURLOPT_RESOLVE => [$safe['host'].':443:'.implode(',', $safe['addresses'])],
        ];
    }

    private function isPublicUnicast(string $address): bool
    {
        if (filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE,
        ) === false) {
            return false;
        }

        // Reject additional non-global ranges PHP's filter flags let through.
        $blockedCidrs = [
            '100.64.0.0/10',    // carrier-grade NAT
            '169.254.0.0/16',   // IPv4 link-local
            '192.0.0.0/24',     // IETF protocol assignments
            '198.18.0.0/15',    // benchmarking
            '224.0.0.0/3',      // multicast and reserved
            '64:ff9b::/96',     // NAT64 of arbitrary IPv4
            'fc00::/7',         // IPv6 unique-local
            'fe80::/10',        // IPv6 link-local
            'ff00::/8',         // IPv6 multicast
            '::ffff:0:0/96',    // IPv4-mapped IPv6
        ];

        foreach ($blockedCidrs as $cidr) {
            if ($this->inCidr($address, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function inCidr(string $address, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $addressBinary = @inet_pton($address);
        $subnetBinary = @inet_pton($subnet);

        if (! is_string($addressBinary) || ! is_string($subnetBinary)
            || strlen($addressBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($addressBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainder) & 0xFF;

        return (ord($addressBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
    }
}
